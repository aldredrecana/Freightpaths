<?php

namespace App\Services;

use App\Models\LocationPing;
use App\Models\RestPeriod;
use App\Models\Schedule;

/** Works out where a trip's driver is and what the driver is doing, from GPS points and rests. */
class TripTracker
{
    private const POINT_COLUMNS = ['latitude', 'longitude', 'accuracy', 'speed', 'recorded_at'];
    
    public function __construct(private RoadSnapper $snapper) {}

    // How many of the newest points we look at to find the direction of travel.
    private const HEADING_POINTS = 8;

    // Two points closer than this are GPS jitter, not movement (meters).
    private const HEADING_MIN_METERS = 15;

    // Status and last position only (cheap enough for a list of trips).
    public function summary(Schedule $schedule): array
    {
        // The newest few points: the last one is the position, the others give the direction.
        $recent = $schedule->locationPings()
            ->orderByDesc('recorded_at')
            ->limit(self::HEADING_POINTS)
            ->get(self::POINT_COLUMNS)
            ->reverse()
            ->values();

        $lastRest = $schedule->restPeriods()
            ->reorder('started_at', 'desc')
            ->first();

        return $this->status($schedule, $recent->last(), $lastRest, $this->heading($recent));
    }

    // Status plus the full route and every rest (for the map of one trip).
    public function details(Schedule $schedule): array
    {
        $pings = $schedule->locationPings()
            ->orderBy('recorded_at')
            ->get(self::POINT_COLUMNS);

        $rests = $schedule->restPeriods;

        return $this->status($schedule, $pings->last(), $rests->last(), $this->heading($pings->slice(-self::HEADING_POINTS)->values())) + [
            'path' => $pings->map(fn ($p) => [$p->latitude, $p->longitude])->values(), // raw GPS points
            'segments' => $this->snapper->segments($pings), // the same route drawn on the roads
            'rests' => $rests->map(fn ($r) => [
                'lat' => $r->latitude,
                'lng' => $r->longitude,
                'from' => $r->started_at->format('M d, g:i A'),
                'to' => $r->ended_at->format('g:i A'),
                'minutes' => intdiv($r->duration_seconds, 60),
            ])->values(),
        ];
    }

    // Direction of travel in degrees (0 = north, 90 = east), from the newest point back to the
    // nearest older point that is far enough away. Null when the truck has not really moved.
    private function heading($points): ?float
    {
        $last = $points->last();

        if (! $last) {
            return null;
        }

        foreach ($points->reverse()->skip(1) as $p) {
            if ($this->meters($p, $last) >= self::HEADING_MIN_METERS) {
                $lat1 = deg2rad($p->latitude);
                $lat2 = deg2rad($last->latitude);
                $dLng = deg2rad($last->longitude - $p->longitude);

                $y = sin($dLng) * cos($lat2);
                $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLng);

                return round(fmod(rad2deg(atan2($y, $x)) + 360, 360), 1);
            }
        }

        return null;
    }

    // Distance in meters between two GPS points (haversine formula).
    private function meters(LocationPing $a, LocationPing $b): float
    {
        $dLat = deg2rad($b->latitude - $a->latitude);
        $dLng = deg2rad($b->longitude - $a->longitude);
        $h = sin($dLat / 2) ** 2
            + cos(deg2rad($a->latitude)) * cos(deg2rad($b->latitude)) * sin($dLng / 2) ** 2;

        return 2 * 6371000 * asin(min(1, sqrt($h)));
    }

        // The truck marker sits on the nearest road when one is a few meters away.
    private function lastPoint(LocationPing $last): array
    {
        [$lat, $lng] = $this->snapper->nearest($last->latitude, $last->longitude, $last->accuracy);

        return ['lat' => $lat, 'lng' => $lng];
    }

    private function status(Schedule $schedule, ?LocationPing $last, ?RestPeriod $lastRest, ?float $heading = null): array
    {
        $secondsAgo = $last ? max(0, now()->timestamp - $last->recorded_at->timestamp) : null;
        $maxGap = config('freightpath.rest_max_gap_minutes') * 60;

        if (in_array($schedule->status, ['delivered', 'cancelled'], true)) {
            $state = 'finished';
            $label = 'Trip finished';
            $message = $last
                ? 'Showing the route recorded during this trip.'
                : 'No GPS points were recorded for this trip.';
        } elseif ($schedule->status !== 'en_route') {
            $state = 'waiting';
            $label = 'Not started';
            $message = 'Tracking starts when the driver starts the trip.';
        } elseif (! $last) {
            $state = 'waiting';
            $label = 'Waiting for GPS';
            $message = "Waiting for the driver's phone to send its first GPS point. Make sure the phone is on, has signal, and location is allowed for the app.";
        } elseif ($secondsAgo > $maxGap) {
            $state = 'offline';
            $label = 'No signal';
            $message = 'No GPS point for ' . intdiv($secondsAgo, 60) . ' minutes. The phone may be off or without signal. Last seen at ' . $last->recorded_at->format('g:i A') . '.';
        } elseif ($lastRest && $lastRest->ended_at->timestamp >= $last->recorded_at->timestamp - 120) {
            $state = 'resting';
            $label = 'Resting';
            $message = 'Resting for ' . intdiv($last->recorded_at->timestamp - $lastRest->started_at->timestamp, 60) . ' minutes (since ' . $lastRest->started_at->format('g:i A') . ').';
        } else {
            $state = 'tracking';
            $label = 'On the road';
            $message = 'Last GPS point ' . now()->subSeconds($secondsAgo)->diffForHumans() . '.';
        }

        return [
            'state' => $state,
            'label' => $label,
            'message' => $message,
                        // While resting, the truck marker stays where the truck is parked,
            // even if the driver walks around with the phone.
            'last' => $last ? ($state === 'resting'
                ? ['lat' => round((float) $lastRest->latitude, 6), 'lng' => round((float) $lastRest->longitude, 6)]
                : $this->lastPoint($last)) + [
            'accuracy' => $last->accuracy,
            'heading' => $heading,
            'seconds_ago' => $secondsAgo,
         // The phone reports meters per second; negative means "unknown".
            'speed_kmh' => $last->speed !== null && $last->speed >= 0 ? (int) round($last->speed * 3.6) : null,
            ] : null,
        ];
    }
}