<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LocationPing;
use App\Models\Schedule;
use App\Services\RestDetector;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{
    // The phone sends a batch of GPS points for one trip.
    public function store(Request $request)
    {
        $data = $request->validate([
            'schedule_id' => ['required', 'integer'],
            'pings' => ['required', 'array', 'min:1', 'max:200'],
            'pings.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'pings.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'pings.*.accuracy' => ['nullable', 'numeric', 'min:0'],
            'pings.*.speed' => ['nullable', 'numeric'],
            'pings.*.recorded_at' => ['required', 'date'],
        ]);

        $schedule = Schedule::with('driver')->findOrFail($data['schedule_id']);

        // Drivers can only send locations for their own trips.
        abort_unless($schedule->driver->user_id === $request->user()->id, 403);

        // Only trips on the road (or just delivered, for late uploads) are tracked.
        if (! in_array($schedule->status, ['en_route', 'delivered'], true)) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Location is only recorded while the trip is on the way.',
            ]);
        }

        $tz = config('app.timezone');
        $now = now();

        $rows = collect($data['pings'])->map(fn ($p) => [
            'schedule_id' => $schedule->id,
            'driver_id' => $schedule->driver_id,
            'latitude' => $p['lat'],
            'longitude' => $p['lng'],
            'accuracy' => $p['accuracy'] ?? null,
            'speed' => $p['speed'] ?? null,
            'recorded_at' => Carbon::parse($p['recorded_at'])->timezone($tz),
            'created_at' => $now,
        ])->all();

        $stored = LocationPing::insertOrIgnore($rows);

        RestDetector::fromConfig()->recompute($schedule);

        return response()->json([
            'received' => count($rows),
            'stored' => $stored,
        ]);
    }
}