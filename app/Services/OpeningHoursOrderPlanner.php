<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use UnexpectedValueException;

class OpeningHoursOrderPlanner
{
    /** Compare candidate orders using route-matrix estimates, then validate actual journeys during generation. */
    public function plan(array $places, array $metrics, array $initialPath, string $preference, CarbonImmutable $start, string $dailyStart, ?string $dailyEnd, ?CarbonImmutable $lastDate): array
    {
        $cache = [];
        $evaluate = function (array $path) use ($places, $metrics, $preference, $start, $dailyStart, $dailyEnd, $lastDate, &$cache): ?array {
            $key = implode(',', $path);
            if (array_key_exists($key, $cache)) return $cache[$key];
            $stops = array_map(fn ($index) => $places[$index] + ['matrix_index' => $index], $path);
            try {
                $schedule = app(DailyItineraryScheduler::class)->schedule($stops, $start, $dailyStart, $dailyEnd, $lastDate,
                    fn ($from, $to, $departure) => [
                        'duration_seconds' => $metrics['durations'][$from['matrix_index']][$to['matrix_index']] ?? 14400,
                        'departure_time' => $departure->format('g:i A'),
                    ]);
            } catch (UnexpectedValueException $exception) {
                return $cache[$key] = null;
            }
            $waiting = 0;
            foreach ($schedule['opening_conflicts'] as $conflict) {
                $waiting += CarbonImmutable::parse($conflict['arrival_at'])->diffInSeconds(CarbonImmutable::parse($conflict['available_at']));
            }
            $field = match ($preference) { 'shortest' => 'distances', 'lowest_cost' => 'fares', default => 'durations' };
            $cost = 0;
            $unknown = 0;
            for ($i = 1; $i < count($path); $i++) {
                $value = $metrics[$field][$path[$i - 1]][$path[$i]];
                if ($value === null) $unknown++;
                else $cost += $value;
            }
            return $cache[$key] = ['path' => $path, 'score' => [$waiting, $unknown, $cost], 'conflicts' => $schedule['opening_conflicts']];
        };
        $original = $evaluate($initialPath);
        $best = $original;
        $candidates = [$initialPath, app(StopOrderOptimizer::class)->optimize($metrics, $preference)];
        $attempts = 0;
        // Move each stop to another position; keep network requests out of this search.
        foreach ($candidates as $base) {
            for ($from = 0; $from < count($base); $from++) {
                for ($to = 0; $to < count($base); $to++) {
                    if (++$attempts > 256) break 3;
                    $path = $base;
                    $moved = array_splice($path, $from, 1);
                    array_splice($path, $to, 0, $moved);
                    $candidate = $evaluate($path);
                    if ($candidate && (!$best || $candidate['score'] < $best['score'])) $best = $candidate;
                }
            }
        }
        return ['original' => $original, 'best' => $best];
    }
}
