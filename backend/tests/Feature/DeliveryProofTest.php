<?php

namespace Tests\Feature;

use App\Models\Schedule;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Proof of delivery: photo + receiver's signature from the app, required before Delivered. */
class DeliveryProofTest extends TestCase
{
    private const JPEG = "\xFF\xD8\xFF\xE0fake-jpeg-body";
    private const PNG = "\x89PNG\r\n\x1a\nfake-png-body";

    private function onTheRoad(): Schedule
    {
        return $this->makeTrip($this->makeDriver(), $this->makeTruck(), ['status' => 'en_route']);
    }

    private function proof(array $overrides = []): array
    {
        return $overrides + [
            'photo' => base64_encode(self::JPEG),
            'signature' => 'data:image/png;base64,'.base64_encode(self::PNG),
            'received_by' => 'Ana Reyes',
            'lat' => 14.07,
            'lng' => 121.32,
        ];
    }

    public function test_delivered_needs_the_proof_first(): void
    {
        $trip = $this->onTheRoad();
        Sanctum::actingAs($trip->driver->user);

        $this->postJson("/api/trips/{$trip->id}/status", ['status' => 'delivered'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->assertSame('en_route', $trip->fresh()->status);

        $this->postJson("/api/trips/{$trip->id}/proof", $this->proof())->assertCreated();
        $this->postJson("/api/trips/{$trip->id}/status", ['status' => 'delivered'])->assertOk();

        $this->assertSame('delivered', $trip->fresh()->status);
        $this->getJson("/api/trips/{$trip->id}")->assertJsonPath('data.proof.received_by', 'Ana Reyes');
    }

    public function test_bad_pictures_and_a_missing_name_are_rejected(): void
    {
        $trip = $this->onTheRoad();
        Sanctum::actingAs($trip->driver->user);

        $this->postJson("/api/trips/{$trip->id}/proof", $this->proof(['photo' => base64_encode('not a picture')]))
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->postJson("/api/trips/{$trip->id}/proof", $this->proof(['signature' => 'not base64!!']))
            ->assertUnprocessable()->assertJsonValidationErrors('signature');
        $this->postJson("/api/trips/{$trip->id}/proof", $this->proof(['received_by' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors('received_by');

        $this->assertNull($trip->proof()->first());
    }

    public function test_only_the_trips_driver_and_only_while_on_the_road(): void
    {
        $accepted = $this->makeTrip($this->makeDriver(), $this->makeTruck(), ['status' => 'accepted']);
        Sanctum::actingAs($accepted->driver->user);
        $this->postJson("/api/trips/{$accepted->id}/proof", $this->proof())->assertStatus(422);

        $other = $this->onTheRoad();
        $this->postJson("/api/trips/{$other->id}/proof", $this->proof())->assertForbidden();
    }

    public function test_staff_see_the_photo_and_signature(): void
    {
        $trip = $this->onTheRoad();
        Sanctum::actingAs($trip->driver->user);
        $this->postJson("/api/trips/{$trip->id}/proof", $this->proof());

        $dispatcher = $this->makeUser('dispatcher');
        $this->actingAs($dispatcher)->get(route('schedules.show', $trip))
            ->assertSee('Proof of delivery')
            ->assertSee('Ana Reyes');

        $photo = $this->get(route('schedules.proof', [$trip, 'photo']))->assertOk();
        $this->assertSame('image/jpeg', $photo->headers->get('Content-Type'));
        $this->assertSame(self::JPEG, $photo->getContent());

        $signature = $this->get(route('schedules.proof', [$trip, 'signature']))->assertOk();
        $this->assertSame(self::PNG, $signature->getContent());

        $this->get(route('schedules.proof', [$trip, 'other']))->assertNotFound();
    }
}