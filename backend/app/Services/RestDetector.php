<?php

namespace App\Services;

use App\Models\RestPeriod;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RestDetector
{
    public function __construct(
        private float $radiusMeters,
        private int $minSeconds,
        private int $maxGapSeconds,
        private float $maxSpeedMs = 2.0,      // faster than this (m/s) = the truck is moving
        private int $mergeGapSeconds = 600,   // two rests closer in time than this are joined...
        private float $mergeDistanceMeters = 200, // ...if their centers are this close
        private float $walkRadiusMeters = 300,    // a resting driver may walk this far from the truck
        private float $walkMaxSpeedMs = 2.8,      // the phone says "walking" and GPS is this slow = the driver, not the truck
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (float) config('freightpath.rest_radius_m'),
            (int) config('freightpath.rest_min_minutes') * 60,
            (int) config('freightpath.rest_max_gap_minutes') * 60,
            (float) config('freightpath.rest_max_speed_ms', 2.0),
            (int) config('freightpath.rest_merge_gap_minutes', 10) * 60,
            (float) config('freightpath.rest_merge_distance_m', 200),
            (float) config('freightpath.rest_walk_radius_m', 300),
            (float) config('freightpath.walk_max_speed_ms', 2.8),
        );
    }

    /**
     * @param  array<int, array{lat: float, lng: float, time: int, speed?: float|null}>  $points  oldest first, time in unix seconds
     * @return array<int, array{started_at: int, ended_at: int, lat: float, lng: float, count: int}>
     */
    public function detect(array $points): array
    {
        $points = array_values($points);
        $stays = [];
        $n = count($points);
        $i = 0;

        while ($i < $n) {
            // Clearly driving here, so a rest cannot start on this point.
            if ($this->isMoving($points, $i)) {
                $i++;

                continue;
            }

            $anchor = $points[$i];
            $sumLat = $anchor['lat'];
            $sumLng = $anchor['lng'];
            $count = 1;  // points that set the rest's center
            $total = 1;  // all points in the rest (also the walking ones)
            $j = $i;

            // Grow the window. The circle is centered on the average of the points so far
            // (so slow GPS drift is forgiven), but a point must also stay within twice the
            // radius of the first point (so the circle cannot crawl away with a slow truck).
            while ($j + 1 < $n) {
                $next = $points[$j + 1];
                $gap = $next['time'] - $points[$j]['time'];
                $center = ['lat' => $sumLat / $count, 'lng' => $sumLng / $count];

                if ($gap > $this->maxGapSeconds || $this->isMoving($points, $j + 1)) {
                    break;
                }

                $outside = $this->distance($center, $next) > $this->radiusMeters
                    || $this->distance($anchor, $next) > 2 * $this->radiusMeters;

                                if ($outside) {
                    // The driver walking around near the parked truck (to a store or restroom),
                    // within the walking distance. The phone's Activity Recognition saying
                    // "walking" is enough on its own; with only the GPS speed (no activity),
                    // the rest must already have lasted the minimum time.
                    $restedLongEnough = $points[$j]['time'] - $anchor['time'] >= $this->minSeconds;
                    $slow = isset($next['speed']) && $next['speed'] !== null
                        && $next['speed'] >= 0 && $next['speed'] <= $this->maxSpeedMs;
                    $walking = $this->isWalking($next) || ($restedLongEnough && $slow);

                    if (! $walking || $this->distance($center, $next) > $this->walkRadiusMeters) {
                        break;
                    }
                } else {
                    // Only points near the truck move the rest's center.
                    $sumLat += $next['lat'];
                    $sumLng += $next['lng'];
                    $count++;
                }

                $total++;
                $j++;
            }

            if ($points[$j]['time'] - $anchor['time'] >= $this->minSeconds) {
                $stays[] = [
                    'started_at' => $anchor['time'],
                    'ended_at' => $points[$j]['time'],
                    'lat' => $sumLat / $count,
                    'lng' => $sumLng / $count,
                    'count' => $total,
                ];

                $i = $j + 1;
            } else {
                $i++;
            }
        }

        return $this->merge($stays);
    }

    /**
     * Fast = speed above the limit. One noisy reading is ignored: a point only counts as
     * moving if the next point is fast too (or it is the last point). Unknown speed
     * (null or negative, which phones use for "no reading") counts as standing still.
     */
    private function isMoving(array $points, int $k): bool
    {
        if (! $this->isFast($points[$k])) {
            return false;
        }

        return ! isset($points[$k + 1]) || $this->isFast($points[$k + 1]);
    }

        private function isFast(array $point): bool
    {
        $speed = $point['speed'] ?? null;

        // A little too fast for walking, but the phone says walking: GPS noise, not the truck.
        if ($this->isWalking($point)) {
            return false;
        }

        return $speed !== null && $speed > $this->maxSpeedMs;
    }

    /** The phone says walking (or running) and the GPS speed is low: the driver, not the truck. */
    private function isWalking(array $point): bool
    {
        $speed = $point['speed'] ?? null;

        return in_array($point['activity'] ?? null, ['walking', 'running'], true)
            && ($speed === null || $speed < 0 || $speed <= $this->walkMaxSpeedMs);
    }

    /** Joins rests that are close in time and place (e.g. the driver walked to a store and came back). */
    private function merge(array $stays): array
    {
        $merged = [];

        foreach ($stays as $stay) {
            $last = $merged ? $merged[count($merged) - 1] : null;

            if ($last
                && $stay['started_at'] - $last['ended_at'] <= $this->mergeGapSeconds
                && $this->distance($last, $stay) <= $this->mergeDistanceMeters) {
                $total = $last['count'] + $stay['count'];

                $merged[count($merged) - 1] = [
                    'started_at' => $last['started_at'],
                    'ended_at' => $stay['ended_at'],
                    'lat' => ($last['lat'] * $last['count'] + $stay['lat'] * $stay['count']) / $total,
                    'lng' => ($last['lng'] * $last['count'] + $stay['lng'] * $stay['count']) / $total,
                    'count' => $total,
                ];
            } else {
                $merged[] = $stay;
            }
        }

        return $merged;
    }

    /** Re-reads all of a trip's points and saves its rests (safe to run again and again). */
    public function recompute(Schedule $schedule): void
    {
        $points = $schedule->locationPings()
            ->where(function ($q) {
                $q->whereNull('accuracy')
                    ->orWhere('accuracy', '<=', config('freightpath.rest_max_accuracy_m'));
            })
            ->orderBy('recorded_at')
            ->get(['latitude', 'longitude', 'speed', 'activity', 'recorded_at'])
            ->map(fn ($p) => [
                'lat' => (float) $p->latitude,
                'lng' => (float) $p->longitude,
                'speed' => $p->speed === null ? null : (float) $p->speed,
                'activity' => $p->activity,
                'time' => $p->recorded_at->getTimestamp(),
            ])
            ->all();

        $rests = $this->detect($points);
        $tz = config('app.timezone');

        DB::transaction(function () use ($schedule, $rests, $tz) {
            RestPeriod::where('schedule_id', $schedule->id)->delete();

            foreach ($rests as $rest) {
                RestPeriod::create([
                    'schedule_id' => $schedule->id,
                    'started_at' => Carbon::createFromTimestamp($rest['started_at'], $tz),
                    'ended_at' => Carbon::createFromTimestamp($rest['ended_at'], $tz),
                    'duration_seconds' => $rest['ended_at'] - $rest['started_at'],
                    'latitude' => $rest['lat'],
                    'longitude' => $rest['lng'],
                    'ping_count' => $rest['count'],
                ]);
            }
        });
    }

    /** Distance in meters between two GPS points (haversine formula). */
    private function distance(array $a, array $b): float
    {
        $earth = 6371000;
        $dLat = deg2rad($b['lat'] - $a['lat']);
        $dLng = deg2rad($b['lng'] - $a['lng']);

        $h = sin($dLat / 2) ** 2
            + cos(deg2rad($a['lat'])) * cos(deg2rad($b['lat'])) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1, sqrt($h)));
    }
}