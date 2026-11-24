<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** The app sends the proof of delivery (photo + signature) before the trip can be marked Delivered. */
class DeliveryProofController extends Controller
{
    private const MAX_PHOTO_BYTES = 2_000_000;   // 2 MB after decoding
    private const MAX_SIGNATURE_BYTES = 300_000; // 300 KB

    public function store(Request $request, Schedule $schedule)
    {
        abort_unless($schedule->driver->user_id === $request->user()->id, 403);

        if ($schedule->status !== 'en_route') {
            return response()->json(['message' => 'Proof of delivery can be added only while the trip is on the way.'], 422);
        }

        $data = $request->validate([
            'photo' => ['required', 'string'],
            'signature' => ['required', 'string'],
            'received_by' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:300'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
        ], [
            'received_by.required' => 'Type the name of the person who received the goods.',
        ]);

        $photo = $this->image($data['photo'], "\xFF\xD8\xFF", self::MAX_PHOTO_BYTES, 'photo', 'The photo must be a JPEG picture.');
        $signature = $this->image($data['signature'], "\x89PNG", self::MAX_SIGNATURE_BYTES, 'signature', 'The signature could not be read. Please sign again.');

        // Sending again (for example after a lost connection) replaces the earlier proof.
        $schedule->proof()->updateOrCreate([], [
            'photo' => base64_encode($photo),
            'signature' => base64_encode($signature),
            'received_by' => $data['received_by'],
            'note' => $data['note'] ?? null,
            'latitude' => $data['lat'] ?? null,
            'longitude' => $data['lng'] ?? null,
            'captured_at' => now(),
        ]);

        return response()->json(['message' => 'Proof of delivery saved.'], 201);
    }

    // Decodes "data:image/...;base64,AAAA" or plain base64, and checks that it really is the expected picture type.
    private function image(string $value, string $magic, int $maxBytes, string $field, string $message): string
    {
        $base64 = preg_replace('/^data:image\/[a-z]+;base64,/i', '', trim($value));
        $bytes = base64_decode($base64, true);

        if ($bytes === false || ! str_starts_with($bytes, $magic)) {
            throw ValidationException::withMessages([$field => $message]);
        }
        if (strlen($bytes) > $maxBytes) {
            throw ValidationException::withMessages([$field => 'The '.$field.' is too large. Please take it again.']);
        }

        return $bytes;
    }
}