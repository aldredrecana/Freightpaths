<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'origin_address' => $this->origin_address,
            'destination_address' => $this->destination_address,
            'departure_at' => $this->departure_at->toIso8601String(),
            'expected_arrival_at' => $this->expected_arrival_at->toIso8601String(),
            'urgency' => $this->urgency,
            'truck' => $this->whenLoaded('truck', fn () => [
                'plate_no' => $this->truck->plate_no,
                'model' => $this->truck->model,
            ]),
            // What this driver may do next, so the app never copies the rules.
            'allowed_next' => $this->allowedNext($request->user()),
            'can_decline' => in_array($this->status, ['pending', 'accepted'], true),
            'history' => $this->whenLoaded('statusLogs', fn () => $this->statusLogs->map(fn ($log) => [
                'status' => $log->status,
                'remarks' => $log->remarks,
                'logged_at' => $log->logged_at->toIso8601String(),
                'by' => $log->loggedBy?->name,
            ])),
                "rests" => $this->whenLoaded("restPeriods", fn () => $this->restPeriods->map(fn ($r) => [
                "started_at" => $r->started_at->toIso8601String(),
                "ended_at" => $r->ended_at->toIso8601String(),
                "minutes" => intdiv($r->duration_seconds, 60),
            ])),
        ];
    }
}
