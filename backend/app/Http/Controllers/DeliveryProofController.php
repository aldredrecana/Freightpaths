<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;

/** Shows the proof-of-delivery pictures to staff (and to the trip's own driver). */
class DeliveryProofController extends Controller
{
    public function show(Request $request, Schedule $schedule, string $kind)
    {
        $user = $request->user();
        abort_unless($user->hasRole('admin') || $user->hasRole('dispatcher') || $schedule->driver->user_id === $user->id, 403);
        abort_unless(in_array($kind, ['photo', 'signature'], true), 404);

        $proof = $schedule->proof()->firstOrFail();

        return response(base64_decode($proof->{$kind}), 200, [
            'Content-Type' => $kind === 'photo' ? 'image/jpeg' : 'image/png',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}