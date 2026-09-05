<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use UnexpectedValueException;

class PlaceOpeningHours
{
    /** Null means unverified, an empty array means closed. */
    public function intervals(?string $hours, CarbonImmutable $date): ?array
    {
        if (!trim((string) $hours)) return null;
        $text = preg_replace('/[\p{Z}\t]+/u', ' ', $hours);
        $days = 'Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday';
        if (preg_match('/\b('.$days.')\s*:/i', $text)) {
            if (!preg_match('/\b'.$date->format('l').'\s*:\s*(.*?)(?=\b(?:'.$days.')\s*:|[\r\n]|$)/i', $text, $match)) return null;
            $text = trim($match[1]);
        }
        if (preg_match('/^(closed|temporarily closed|permanently closed)$/i', trim($text))) return [];
        if (preg_match('/^(open\s*)?24\s*(hours|hrs)|^24\/7$/i', trim($text))) {
            return [[$date->startOfDay(), $date->startOfDay()->addDay()]];
        }
        // Extract paired clock tokens, tolerating imported Unicode dash/spacing characters.
        preg_match_all('/\b(\d{1,2})(?::(\d{2}))?\s*(AM|PM)?\b/i', $text, $matches, PREG_SET_ORDER);
        if (!$matches || count($matches) % 2 !== 0) return null;
        $remainder = preg_replace('/\b\d{1,2}(?::\d{2})?\s*(AM|PM)?\b/i', '', $text);
        $remainder = preg_replace('/\b(daily|every day|open|to)\b/i', '', $remainder);
        if (preg_match('/[\p{L}\p{N}]/u', $remainder)) return null;
        $intervals = [];
        for ($i = 0; $i < count($matches); $i += 2) {
            if (empty($matches[$i][3]) !== empty($matches[$i + 1][3])) return null;
            $times = [];
            foreach ([$matches[$i], $matches[$i + 1]] as $token) {
                $hour = (int) $token[1];
                $minute = (int) ($token[2] ?? 0);
                $suffix = strtoupper($token[3] ?? '');
                if ($minute > 59 || ($suffix ? ($hour < 1 || $hour > 12) : $hour > 23)) return null;
                if ($suffix) $hour = ($hour % 12) + ($suffix === 'PM' ? 12 : 0);
                $times[] = $date->startOfDay()->setTime($hour, $minute);
            }
            if ($times[1]->lessThanOrEqualTo($times[0])) $times[1] = $times[1]->addDay();
            $intervals[] = $times;
        }
        return $intervals;
    }

    public function nextVisit(array $stop, CarbonImmutable $arrival, int $seconds, string $dailyStart, ?string $dailyEnd, ?CarbonImmutable $lastDate): array
    {
        $hours = $stop['operating_hours'] ?? null;
        $limit = $arrival->startOfDay()->addDays(8);
        if ($lastDate && $lastDate->lessThan($limit)) $limit = $lastDate->startOfDay();
        for ($day = $arrival->startOfDay(); $day->lessThanOrEqualTo($limit); $day = $day->addDay()) {
            $start = $dailyEnd === null ? $day : $day->setTimeFromTimeString($dailyStart);
            $end = $dailyEnd === null ? $day->addDays(2) : $day->setTimeFromTimeString($dailyEnd);
            if ($lastDate) $end = $end->min($lastDate->startOfDay()->addDay());
            $intervals = $this->intervals($hours, $day);
            $verified = $intervals !== null;
            if ($intervals === null) $intervals = [[$start, $end]];
            // Include the previous day's late-night opening period.
            foreach ($this->intervals($hours, $day->subDay()) ?? [] as $period) {
                if ($period[1]->greaterThan($day)) $intervals[] = $period;
            }
            if ($dailyEnd === null && $verified) {
                $intervals = [...$intervals, ...($this->intervals($hours, $day->addDay()) ?? [])];
            }
            usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
            $merged = [];
            foreach ($intervals as $period) {
                $last = array_key_last($merged);
                if ($last !== null && $period[0]->lessThanOrEqualTo($merged[$last][1])) {
                    $merged[$last][1] = $merged[$last][1]->max($period[1]);
                } else {
                    $merged[] = $period;
                }
            }
            $intervals = $merged;
            foreach ($intervals as [$opens, $closes]) {
                $candidate = $arrival->max($start)->max($opens);
                if ($candidate->addSeconds($seconds)->lessThanOrEqualTo($end->min($closes))) {
                    return ['start' => $candidate, 'verified' => $verified];
                }
            }
        }
        throw new UnexpectedValueException(__('schedule.no_open_slot', ['place' => $stop['name']]));
    }
}
