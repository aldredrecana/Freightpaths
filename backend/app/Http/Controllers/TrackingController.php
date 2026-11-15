<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Services\TripTracker;

class TrackingController extends Controller
{
    // JSON for the map on one trip.
    public function show(Schedule $schedule, TripTracker $tracker)
    {
        return response()->json($tracker->details($schedule));
    }
}