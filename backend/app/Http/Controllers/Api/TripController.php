<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TripResource;
use App\Models\Schedule;
use Illuminate\Http\Request;
use App\Services\RoadSnapper;

class TripController extends Controller
{
    // The logged-in driver's own trips (same order as the My Deliveries page).
    public function index(Request $request)
    {
        $trips = Schedule::with('truck')
            ->whereHas(
                'driver',
                fn ($q) => $q->where('user_id', $request->user()->id),
            )
            ->orderByRaw(
                "CASE WHEN status IN ('delivered', 'cancelled', 'needs_reassignment') THEN 1 ELSE 0 END",
            )
            ->orderByDesc('departure_at')
            ->paginate(20);

        return TripResource::collection($trips);
    }

    public function show(Request $request, Schedule $schedule)
    {
        abort_unless($schedule->driver->user_id === $request->user()->id, 403);

        $schedule->load(["truck", "statusLogs.loggedBy", "restPeriods"]);

        return new TripResource($schedule);
    }
        // The route the driver has driven so far (for the map in the app).
       public function track(Request $request, Schedule $schedule, RoadSnapper $snapper)
    {
        abort_unless($schedule->driver->user_id === $request->user()->id, 403);

        $pings = $schedule->locationPings()
            ->orderBy('recorded_at')
            ->get(['latitude', 'longitude', 'accuracy', 'recorded_at']);

        return response()->json(['path' => $snapper->flatten($snapper->segments($pings))]);
    }

    
    // The road route from pickup (A) to drop-off (B), for the Route card in the app.
    public function route(Request $request, Schedule $schedule, RoadSnapper $snapper)
    {
        abort_unless($schedule->driver->user_id === $request->user()->id, 403);

        $plan = $schedule->routePlan();
        if (! $plan['origin'] || ! $plan['destination']) {
            return response()->json(['points' => [], 'km' => null, 'minutes' => null, 'on_roads' => false]);
        }

        return response()->json($snapper->roadRoute($plan['origin'], $plan['destination']));
    }
}
