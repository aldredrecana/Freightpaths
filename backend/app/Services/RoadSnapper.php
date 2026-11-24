<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Turns a trip's raw GPS points into lines that follow the roads.
 *
 * 1. Rough points (poor accuracy) and impossible jumps are left out of the line.
 * 2. The points are split where the phone lost signal (a long silence over a long distance).
 * 3. Each driven part is "map matched" by OSRM: it returns the road the truck was on.
 * 4. Each lost-signal part gets OSRM's most likely road route, drawn dashed on the map.
 *
 * Answers are cached, and if OSRM is slow or down the raw GPS line is used instead.
 */
class RoadSnapper
{
    private const CHUNK = 100;        // OSRM accepts at most 100 points per request
    private const MAX_SPEED_MS = 45;  // ~160 km/h: faster "jumps" are GPS errors
    private const BUDGET_SECONDS = 8; // stop asking OSRM after this much time in one page load

    private float $startedAt;

    
    public function __construct(private PathSmoother $smoother) {}

    /**
     * @param  Collection  $pings  LocationPing models in time order (latitude, longitude, accuracy, recorded_at)
     * @return array<int, array{type: string, points: array<int, array{0: float, 1: float}>}>
     */
    public function segments(Collection $pings): array
    {
        $this->startedAt = microtime(true);
        $runs = $this->runs($this->clean($pings));
        if (config('freightpath.smooth_path')) {
            // Smooth each part on its own, so nothing is ever smoothed across a lost-signal gap.
     $runs = array_map(fn ($run) => $this->smoother->smooth($run), $runs);
        }
        $segments = [];

        foreach ($runs as $i => $run) {
            if ($i > 0) {
                $from = end($segments[count($segments) - 1]['points']);
                $to = $this->first($run);
                $segments[] = ['type' => 'gap', 'points' => $this->route($from, $to)];
            }
            $segments[] = ['type' => 'driven', 'points' => $this->match($run)];
        }

        return $segments;
    }

    // One flat line (for the phone app), without repeated points.
    public function flatten(array $segments): array
    {
        $line = [];
        foreach ($segments as $segment) {
            foreach ($segment['points'] as $p) {
                if (! $line || end($line) !== $p) {
                    $line[] = $p;
                }
            }
        }

        return $line;
    }

    // The nearest road point for a truck marker, if a road is close enough (else the GPS point).
    public function nearest(float $lat, float $lng, ?float $accuracy): array
    {
        $max = config('freightpath.path_max_accuracy_m');
        if (! $this->enabled() || ($accuracy !== null && $accuracy > $max)) {
            return [$lat, $lng];
        }

        $key = 'osrm:nearest:'.round($lat, 6).','.round($lng, 6);
        $hit = $this->ask($key, "/nearest/v1/driving/{$lng},{$lat}", ['number' => 1]);
        $wp = $hit['waypoints'][0] ?? null;

        // Only move the marker a short way: a truck in a parking lot stays where it is.
        if ($wp && ($wp['distance'] ?? INF) <= 50) {
            return [round($wp['location'][1], 6), round($wp['location'][0], 6)];
        }

        return [$lat, $lng];
    }

    // ---- Steps ----

    private function clean(Collection $pings): array
    {
        $max = config('freightpath.path_max_accuracy_m');
        $kept = [];

        foreach ($pings as $p) {
            if ($p->accuracy !== null && $p->accuracy > $max) {
                continue; // rough point (often a Wi-Fi/cell-tower fix)
            }
            $prev = end($kept);
            if ($prev) {
                $seconds = $p->recorded_at->timestamp - $prev->recorded_at->timestamp;
                if ($seconds < 1) {
                    continue; // same second as the point before (OSRM needs increasing times)
                }
                if ($this->meters($prev, $p) / $seconds > self::MAX_SPEED_MS) {
                    continue; // impossible jump
                }
            }
            $kept[] = $p;
        }

        // A trip with only rough points still shows something.
        return $kept ?: $pings->all();
    }

    // Splits the points where the signal was lost.
    private function runs(array $points): array
    {
        $runs = [];
        $run = [];

        foreach ($points as $p) {
            $prev = end($run);
            if ($prev
                && $p->recorded_at->timestamp - $prev->recorded_at->timestamp > config('freightpath.path_gap_seconds')
                && $this->meters($prev, $p) > config('freightpath.path_gap_meters')) {
                $runs[] = $run;
                $run = [];
            }
            $run[] = $p;
        }

        if ($run) {
            $runs[] = $run;
        }

        return $runs;
    }

    // Driven part: map matching, 100 points at a time (each chunk starts on the last point of the one before).
    private function match(array $run): array
    {
        $raw = array_map(fn ($p) => $this->point($p), $run);
        if (count($run) < 2 || ! $this->enabled()) {
            return $raw;
        }

        $line = [];
        for ($start = 0; $start < count($run) - 1; $start += self::CHUNK - 1) {
            $chunk = array_slice($run, $start, self::CHUNK);
            $coords = implode(';', array_map(fn ($p) => $p->longitude.','.$p->latitude, $chunk));
            $hit = $this->ask('osrm:match:'.md5($coords), "/match/v1/driving/{$coords}", [
                'geometries' => 'geojson',
                'overview' => 'full',
                'gaps' => 'ignore',
                'tidy' => 'true',
                'timestamps' => implode(';', array_map(fn ($p) => $p->recorded_at->timestamp, $chunk)),
                'radiuses' => implode(';', array_map(fn ($p) => (int) min(50, max(10, $p->accuracy ?? 25)), $chunk)),
            ]);

            $part = [];
            foreach ($hit['matchings'] ?? [] as $m) {
                foreach ($m['geometry']['coordinates'] as [$lng, $lat]) {
                    $part[] = [round($lat, 6), round($lng, 6)];
                }
            }

            $line = array_merge($line, $part ?: array_map(fn ($p) => $this->point($p), $chunk));
        }

        return $line;
    }

        // Lost-signal part: the most likely road route between the two ends.
    private function route(array $from, array $to): array
    {
        return $this->roadRoute($from, $to)['points'];
    }

    /**
     * The road route between two points (also used for the planned route A -> B).
     * Falls back to a straight line when OSRM is off or cannot be reached.
     *
     * @return array{points: array, km: float|null, minutes: int|null, on_roads: bool}
     */
    public function roadRoute(array $from, array $to): array
    {
        $straight = ['points' => [$from, $to], 'km' => null, 'minutes' => null, 'on_roads' => false];
        if (! $this->enabled()) {
            return $straight;
        }

        $coords = "{$from[1]},{$from[0]};{$to[1]},{$to[0]}";
        $hit = $this->ask('osrm:route:'.md5($coords), "/route/v1/driving/{$coords}", [
            'geometries' => 'geojson',
            'overview' => 'full',
        ]);

        $route = $hit['routes'][0] ?? null;
        if (! $route || empty($route['geometry']['coordinates'])) {
            return $straight;
        }

        return [
            'points' => array_map(fn ($c) => [round($c[1], 6), round($c[0], 6)], $route['geometry']['coordinates']),
            'km' => isset($route['distance']) ? round($route['distance'] / 1000, 1) : null,
            'minutes' => isset($route['duration']) ? (int) round($route['duration'] / 60) : null,
            'on_roads' => true,
        ];
    }

    // ---- OSRM requests (cached) ----

    private function ask(string $key, string $path, array $query): ?array
    {
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached ?: null; // [] means "failed recently"
        }
        if (microtime(true) - ($this->startedAt ?? microtime(true)) > self::BUDGET_SECONDS) {
            return null; // out of time for this page load; try again on the next refresh
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'FreightPath/1.0 (capstone project)'])
                ->get(rtrim(config('freightpath.osrm_url'), '/').$path, $query);

            if ($response->ok() && $response->json('code') === 'Ok') {
                Cache::put($key, $response->json(), now()->addDays(7));

                return $response->json();
            }
        } catch (Throwable) {
            // no connection: fall through
        }

        Cache::put($key, [], now()->addMinute()); // don't ask again for a minute

        return null;
    }

    // ---- Helpers ----

    private function enabled(): bool
    {
        return (bool) config('freightpath.snap_to_roads');
    }

    private function point($p): array
    {
        return [(float) $p->latitude, (float) $p->longitude];
    }

    private function first(array $run): array
    {
        return $this->point($run[0]);
    }

    private function meters($a, $b): float
    {
        $dLat = deg2rad($b->latitude - $a->latitude);
        $dLng = deg2rad($b->longitude - $a->longitude);
        $h = sin($dLat / 2) ** 2
            + cos(deg2rad($a->latitude)) * cos(deg2rad($b->latitude)) * sin($dLng / 2) ** 2;

        return 2 * 6371000 * asin(min(1, sqrt($h)));
    }
}