<?php

namespace App\Services;

/**
 * Smooths a run of GPS points before they are drawn (data smoothing).
 *
 * 1. Kalman filter: each new point is blended with the position we already believe in.
 *    How much the new point counts depends on its accuracy (a 5 m point counts a lot,
 *    a 40 m point only a little) and on how far the truck could have moved since the
 *    last point (fast truck = trust the new point; slow truck = smooth more).
 * 2. Parked hold: while the truck is not moving, small GPS wandering is ignored, so a
 *    parked truck does not draw a messy blob on the map.
 *
 * The saved GPS points are never changed; only copies are smoothed for the map line.
 */
class PathSmoother
{
    private const MIN_SPEED_MS = 1.0;     // the truck may always have moved this fast (m/s)
    private const STILL_SPEED_MS = 1.0;   // slower than this (as the phone reports) = standing still
    private const HOLD_METERS = 25;       // ...and wandering inside this circle is ignored
    private const DEFAULT_ACCURACY = 25;  // when the phone did not say how accurate a point is

    /**
     * @param  array  $points  LocationPing models of ONE run (no lost-signal gap inside), oldest first
     * @return array copies of the points with smoothed latitude/longitude
     */
    public function smooth(array $points): array
    {
        $out = [];
        $lat = $lng = null;   // our current belief of where the truck is
        $variance = 0.0;      // how unsure we are about it (meters squared)
        $prevTime = null;
        $held = null;         // the spot where the truck is parked, if it is

        foreach ($points as $p) {
            $acc = max(3.0, (float) ($p->accuracy ?? self::DEFAULT_ACCURACY));
            $time = $p->recorded_at->timestamp;
            $rawLat = (float) $p->latitude;
            $rawLng = (float) $p->longitude;

            if ($lat === null) {
                // First point: start from it.
                [$lat, $lng, $variance] = [$rawLat, $rawLng, $acc * $acc];
            } else {
                $seconds = max(1, $time - $prevTime);
                $jump = $this->meters($lat, $lng, $rawLat, $rawLng);

                // How fast could the truck be going? Use the phone's speed, or the jump itself.
                $speed = ($p->speed !== null && $p->speed >= 0) ? (float) $p->speed : $jump / $seconds;
                $speed = max(self::MIN_SPEED_MS, $speed);

                // Predict: the longer and faster, the less sure we are where the truck is now.
                $variance += $seconds * $speed * $speed;

                // Correct: blend in the new point. K near 1 = trust the new point; near 0 = keep ours.
                $k = $variance / ($variance + $acc * $acc);
                $lat += $k * ($rawLat - $lat);
                $lng += $k * ($rawLng - $lng);
                $variance = (1 - $k) * $variance;
            }

            // Parked hold: the phone says "not moving" and we are still near the parked spot.
            $still = $p->speed !== null && $p->speed >= 0 && $p->speed < self::STILL_SPEED_MS;
            if ($still && $held && $this->meters($held[0], $held[1], $lat, $lng) <= max(self::HOLD_METERS, $acc)) {
                [$lat, $lng] = $held;
            } else {
                $held = $still ? [$lat, $lng] : null;
            }

            $copy = clone $p;
            $copy->latitude = round($lat, 6);
            $copy->longitude = round($lng, 6);
            $out[] = $copy;
            $prevTime = $time;
        }

        return $out;
    }

    private function meters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * 6371000 * asin(min(1, sqrt($h)));
    }
}