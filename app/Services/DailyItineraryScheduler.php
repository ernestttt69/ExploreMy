<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use UnexpectedValueException;

class DailyItineraryScheduler
{
    /** Schedule complete visits and journeys; refresh journey data after an overnight break. */
    public function schedule(array $stops, CarbonImmutable $start, string $dailyStart, ?string $dailyEnd, ?CarbonImmutable $lastDate, callable $journey): array
    {
        if ($dailyEnd !== null && $dailyEnd <= $dailyStart) {
            throw new UnexpectedValueException(__('schedule.invalid_window'));
        }
        $cursor = $start;
        $legs = [];
        $openingConflicts = [];
        $fit = function (CarbonImmutable $at, int $seconds) use ($dailyStart, $dailyEnd, $lastDate): CarbonImmutable {
            if ($dailyEnd === null) {
                if ($lastDate && $at->addSeconds($seconds)->greaterThan($lastDate->startOfDay()->addDay())) {
                    throw new UnexpectedValueException(__('schedule.trip_full'));
                }
                return $at;
            }
            $opening = $at->startOfDay()->setTimeFromTimeString($dailyStart);
            $closing = $at->startOfDay()->setTimeFromTimeString($dailyEnd);
            if ($seconds > $opening->diffInSeconds($closing)) {
                throw new UnexpectedValueException(__('schedule.activity_too_long'));
            }
            if ($at->lessThan($opening)) {
                $at = $opening;
            }
            if ($at->addSeconds($seconds)->greaterThan($closing)) {
                $at = $opening->addDay();
            }
            if ($lastDate && $at->startOfDay()->greaterThan($lastDate->startOfDay())) {
                throw new UnexpectedValueException(__('schedule.trip_full'));
            }
            return $at;
        };

        foreach ($stops as $index => &$stop) {
            $visitSeconds = (int) $stop['suggested_visit_minutes'] * 60;
            $cursor = $fit($cursor, $visitSeconds);
            $arrival = $cursor;
            $slot = app(PlaceOpeningHours::class)->nextVisit($stop, $cursor, $visitSeconds, $dailyStart, $dailyEnd, $lastDate);
            $cursor = $slot['start'];
            $stop['opening_hours_verified'] = $slot['verified'];
            if ($cursor->greaterThan($arrival)) {
                $conflict = [
                    'place' => $stop['name'], 'arrival_at' => $arrival->toIso8601String(),
                    'available_at' => $cursor->toIso8601String(),
                ];
                $openingConflicts[] = $conflict;
                $stop['opening_conflict'] = $conflict;
            }
            $stop['visit_start_at'] = $cursor->toIso8601String();
            $cursor = $cursor->addSeconds($visitSeconds);
            $stop['visit_end_at'] = $cursor->toIso8601String();
            if ($index === count($stops) - 1) {
                continue;
            }

            // Public transport departures depend on the date, so re-query after moving a journey.
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $leg = $journey($stop, $stops[$index + 1], $cursor, $index);
                $departure = $fit($cursor, (int) ceil($leg['duration_seconds']));
                if ($departure->equalTo($cursor)) {
                    break;
                }
                $cursor = $departure;
            }
            if (! $departure->equalTo($cursor) || $attempt === 3) {
                throw new UnexpectedValueException(__('schedule.activity_too_long'));
            }
            $leg['departure_at'] = $cursor->toIso8601String();
            $leg['trip_date'] = $cursor->toDateString();
            $leg['trip_date_display'] = $cursor->locale(app()->getLocale())->translatedFormat('D, d M Y');
            $leg['visit_start_time'] = CarbonImmutable::parse($stop['visit_start_at'])->format('g:i A');
            $leg['visit_end_time'] = CarbonImmutable::parse($stop['visit_end_at'])->format('g:i A');
            $leg['visit_duration_minutes'] = $stop['suggested_visit_minutes'];
            $leg['visit_duration_display'] = $stop['suggested_visit_display'];
            $cursor = $cursor->addSeconds((int) ceil($leg['duration_seconds']));
            $leg['arrival_at'] = $cursor->toIso8601String();
            $leg['arrival_time'] = $cursor->format('g:i A');
            $legs[] = $leg;
        }
        unset($stop);

        return ['stops' => $stops, 'transit_legs' => $legs, 'end' => $cursor, 'opening_conflicts' => $openingConflicts];
    }
}
