<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TripResource;
use App\Models\Schedule;
use Illuminate\Http\Request;

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
}
