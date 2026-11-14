<?php

namespace App\Http\Controllers;

use App\Models\Schedule;

class TrackingController extends Controller
{
    // JSON for the map on the staff trip page.
    public function show(Schedule $schedule)
    {
        $pings = $schedule->locationPings()
            ->orderBy('recorded_at')
            ->get(['latitude', 'longitude', 'accuracy', 'recorded_at']);

        $rests = $schedule->restPeriods;
        $last = $pings->last();
        $lastRest = $rests->last();

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
            $message = "Waiting for the driver's phone to send its first GPS point. The app must be open on the phone.";
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

        return response()->json([
            'state' => $state,
            'label' => $label,
            'message' => $message,
            'last' => $last ? [
                'lat' => $last->latitude,
                'lng' => $last->longitude,
                'accuracy' => $last->accuracy,
            ] : null,
            'path' => $pings->map(fn ($p) => [$p->latitude, $p->longitude])->values(),
            'rests' => $rests->map(fn ($r) => [
                'lat' => $r->latitude,
                'lng' => $r->longitude,
                'from' => $r->started_at->format('M d, g:i A'),
                'to' => $r->ended_at->format('g:i A'),
                'minutes' => intdiv($r->duration_seconds, 60),
            ])->values(),
        ]);
    }
}