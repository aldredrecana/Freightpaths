<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Services\RoadSnapper;
use App\Services\TripTracker;
use Illuminate\Http\Request;

class MapController extends Controller
{
    public function index()
    {
        return view('map.index');
    }

    // Every trip that is on the road, with where its driver was last seen.
    public function data(TripTracker $tracker)
    {
        $trips = Schedule::with(['driver.user', 'truck', 'incidents' => fn ($q) => $q->whereNull('resolved_at')])
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
                // An open problem report (breakdown, accident...), shown in red on the map list.
                'incident' => $s->incidents->first()?->label,
            ] + $tracker->summary($s))->values(),
        ]);
    }

    
    // The road route between pickup (A) and drop-off (B), for the trip form and trip page.
    public function route(Request $request, RoadSnapper $snapper)
    {
        $data = $request->validate([
            'from_lat' => ['required', 'numeric', 'between:-90,90'],
            'from_lng' => ['required', 'numeric', 'between:-180,180'],
            'to_lat' => ['required', 'numeric', 'between:-90,90'],
            'to_lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return response()->json($snapper->roadRoute(
            [round((float) $data['from_lat'], 6), round((float) $data['from_lng'], 6)],
            [round((float) $data['to_lat'], 6), round((float) $data['to_lng'], 6)],
        ));
    }
}