<?php

namespace App\Services;

class StopOrderOptimizer
{
    /** Find an open route with unrestricted start/end; unknown prices are never treated as free. */
    public function optimize(array $metrics, string $preference): array
    {
        $count = count($metrics['durations']);
        if ($count < 2) {
            return array_keys($metrics['durations']);
        }
        $field = match ($preference) {
            'shortest' => 'distances', 'lowest_cost' => 'fares', default => 'durations',
        };
        $edge = fn ($a, $b) => [
            is_numeric($metrics[$field][$a][$b]) ? 0 : 1,
            (float) ($metrics[$field][$a][$b] ?? 0),
            (float) ($metrics['durations'][$a][$b] ?? 86400),
        ];
        $add = fn ($a, $b) => [$a[0] + $b[0], $a[1] + $b[1], $a[2] + $b[2]];
        // Exact dynamic programming for small trips; multi-start greedy for large collections.
        if ($count <= 11) {
            $states = [];
            for ($i = 0; $i < $count; $i++) {
                $states[1 << $i][$i] = ['cost' => [0, 0, 0], 'path' => [$i]];
            }
            $full = (1 << $count) - 1;
            for ($mask = 1; $mask <= $full; $mask++) {
                foreach ($states[$mask] ?? [] as $last => $state) {
                    for ($next = 0; $next < $count; $next++) {
                        if ($mask & (1 << $next)) continue;
                        $nextMask = $mask | (1 << $next);
                        $cost = $add($state['cost'], $edge($last, $next));
                        if (!isset($states[$nextMask][$next]) || $cost < $states[$nextMask][$next]['cost']) {
                            $states[$nextMask][$next] = ['cost' => $cost, 'path' => [...$state['path'], $next]];
                        }
                    }
                }
            }
            $routes = array_values($states[$full]);
        } else {
            $routes = [];
            for ($start = 0; $start < $count; $start++) {
                $path = [$start];
                $cost = [0, 0, 0];
                while (count($path) < $count) {
                    $remaining = array_values(array_diff(range(0, $count - 1), $path));
                    $last = end($path);
                    usort($remaining, fn ($a, $b) => $edge($last, $a) <=> $edge($last, $b));
                    $next = $remaining[0];
                    $cost = $add($cost, $edge($last, $next));
                    $path[] = $next;
                }
                $routes[] = compact('cost', 'path');
            }
        }
        usort($routes, fn ($a, $b) => ($a['cost'] <=> $b['cost']) ?: ($a['path'] <=> $b['path']));
        return $routes[0]['path'];
    }
}
