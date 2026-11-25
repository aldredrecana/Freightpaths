<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverBreak;
use App\Models\Schedule;
use Illuminate\Http\Request;

/** The driver's "Take a break" / "End break" buttons in the app. */
class BreakController extends Controller
{
    public function start(Request $request, Schedule $schedule)
    {
        abort_unless($schedule->driver->user_id === $request->user()->id, 403);

        if ($schedule->status !== 'en_route') {
            return response()->json(['message' => 'You can take a break only on a trip that is on the way.'], 422);
        }

        // Tapping twice does not start two breaks.
        $break = $schedule->openBreak() ?? DriverBreak::create([
            'schedule_id' => $schedule->id,
            'driver_id' => $schedule->driver_id,
            'started_at' => now(),
        ]);

        return response()->json(['on_break_since' => $break->started_at->toIso8601String()]);
    }

    public function end(Request $request, Schedule $schedule)
    {
        abort_unless($schedule->driver->user_id === $request->user()->id, 403);

        $break = $schedule->openBreak();
        $break?->update(['ended_at' => now()]);

        return response()->json([
            'on_break_since' => null,
            'minutes' => $break?->minutes(),
        ]);
    }
}