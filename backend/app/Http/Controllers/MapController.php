<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Services\TripTracker;

class MapController extends Controller
{
    public function index()
    {
        return view('map.index');
    }

    // Every trip that is on the road, with where its driver was last seen.
    public function data(TripTracker $tracker)
    {
        $trips = Schedule::with(['driver.user', 'truck'])
            ->where('status', 'en_route')
            ->orderBy('departure_at')
            ->get();

        return response()->json([
            'trips' => $trips->map(fn (Schedule $s) => [
                'id' => $s->id,
                'reference' => $s->reference,
                'driver' => $s->driver->user->name,
                'truck' => $s->truck->plate_no,
                'origin' => $s->origin_address,
                'destination' => $s->destination_address,
                'url' => route('schedules.show', $s),
                'tracking_url' => route('schedules.tracking', $s),
            ] + $tracker->summary($s))->values(),
        ]);
    }
}