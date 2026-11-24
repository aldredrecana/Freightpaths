<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The driver reports a problem on the road (breakdown, flat tire, accident...) from the app. */
class IncidentController extends Controller
{
    // The reports for one trip, newest first.
    public function index(Request $request, Schedule $schedule)
    {
        abort_unless($schedule->driver->user_id === $request->user()->id, 403);

        return response()->json([
            'incidents' => $schedule->incidents()->limit(10)->get()->map->toAppArray(),
        ]);
    }

    public function store(Request $request, Schedule $schedule)
    {
        abort_unless($schedule->driver->user_id === $request->user()->id, 403);

        if (! in_array($schedule->status, ['accepted', 'en_route'], true)) {
            return response()->json(['message' => 'You can report a problem only on a trip you accepted or started.'], 422);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Incident::TYPES))],
            'note' => ['nullable', 'string', 'max:500', 'required_if:type,other'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ], [
            'note.required_if' => 'Please describe the problem.',
        ]);

        // No location from the phone: use the last GPS point of the trip, if any.
        $lat = $data['lat'] ?? null;
        $lng = $data['lng'] ?? null;
        if ($lat === null && ($last = $schedule->locationPings()->latest('recorded_at')->first())) {
            [$lat, $lng] = [$last->latitude, $last->longitude];
        }

        $incident = Incident::create([
            'schedule_id' => $schedule->id,
            'driver_id' => $schedule->driver_id,
            'type' => $data['type'],
            'note' => $data['note'] ?? null,
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => $data['accuracy'] ?? null,
            'reported_at' => now(),
        ]);

        return response()->json(['incident' => $incident->toAppArray()], 201);
    }
}