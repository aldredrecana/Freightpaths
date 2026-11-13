<?php

namespace App\Services;

use App\Models\RestPeriod;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Finds rest stops in a trip's GPS points.
 * A rest = the phone stays inside a small circle for a minimum time.
 */
class RestDetector
{
    public function __construct(
        private float $radiusMeters,
        private int $minSeconds,
        private int $maxGapSeconds,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (float) config('freightpath.rest_radius_m'),
            (int) config('freightpath.rest_min_minutes') * 60,
            (int) config('freightpath.rest_max_gap_minutes') * 60,
        );
    }

    /**
     * @param  array<int, array{lat: float, lng: float, time: int}>  $points  oldest first, time in unix seconds
     * @return array<int, array{started_at: int, ended_at: int, lat: float, lng: float, count: int}>
     */
    public function detect(array $points): array
    {
        $rests = [];
        $n = count($points);
        $i = 0;

        while ($i < $n) {
            $anchor = $points[$i];
            $j = $i;

            // Grow the window while points stay in the circle and the phone keeps reporting.
            while ($j + 1 < $n) {
                $next = $points[$j + 1];
                $gap = $next['time'] - $points[$j]['time'];

                if ($gap > $this->maxGapSeconds || $this->distance($anchor, $next) > $this->radiusMeters) {
                    break;
                }

                $j++;
            }

            if ($points[$j]['time'] - $anchor['time'] >= $this->minSeconds) {
                $window = array_slice($points, $i, $j - $i + 1);

                $rests[] = [
                    'started_at' => $anchor['time'],
                    'ended_at' => $points[$j]['time'],
                    'lat' => array_sum(array_column($window, 'lat')) / count($window),
                    'lng' => array_sum(array_column($window, 'lng')) / count($window),
                    'count' => count($window),
                ];

                $i = $j + 1;
            } else {
                $i++;
            }
        }

        return $rests;
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
            ->get(['latitude', 'longitude', 'recorded_at'])
            ->map(fn ($p) => [
                'lat' => (float) $p->latitude,
                'lng' => (float) $p->longitude,
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