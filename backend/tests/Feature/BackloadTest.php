<?php

namespace Tests\Feature;

use App\Models\Schedule;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Backload: a return load offered to the same driver and truck, which the driver may accept or decline. */
class BackloadTest extends TestCase
{
    private function firstTrip(string $status = 'en_route'): Schedule
    {
        return $this->makeTrip($this->makeDriver(), $this->makeTruck(['status' => 'on_trip']), [
            'status' => $status,
            'origin_address' => 'Calamba Warehouse',
            'destination_address' => 'Santa Rosa Plant',
            'departure_at' => now()->subHour(),
            'expected_arrival_at' => now()->addHour(),
        ]);
    }

    public function test_the_form_is_filled_with_the_return_route(): void
    {
        $trip = $this->firstTrip();

        $this->actingAs($this->makeUser('dispatcher'))
            ->get(route('schedules.create', ['backload_of' => $trip->id]))
            ->assertOk()
            ->assertSee('Offer backload')
            ->assertSee('Backload for '.$trip->reference)
            ->assertSee('name="backload_of_id" value="'.$trip->id.'"', false)
            ->assertSee($trip->truck->plate_no) // the busy truck is still in the list
            ->assertSee('Santa Rosa Plant');
    }

    public function test_a_backload_is_saved_linked_to_its_trip_and_the_driver_sees_it(): void
    {
        $trip = $this->firstTrip();
        $start = $trip->expected_arrival_at->copy()->addMinutes(30);

        $this->actingAs($this->makeUser('dispatcher'))->post(route('schedules.store'), [
            'driver_id' => $trip->driver_id,
            'truck_id' => $trip->truck_id,
            'origin_address' => 'Santa Rosa Plant',
            'destination_address' => 'Calamba Warehouse',
            'departure_at' => $start->format('Y-m-d\TH:i'),
            'expected_arrival_at' => $start->copy()->addHours(2)->format('Y-m-d\TH:i'),
            'backload_of_id' => $trip->id,
        ])->assertRedirect();

        $back = Schedule::where('backload_of_id', $trip->id)->sole();
        $this->assertSame('pending', $back->status);
        $this->assertStringContainsString('Backload after '.$trip->reference, $back->statusLogs()->first()->remarks);

        $this->get(route('schedules.show', $trip))->assertSee('Backload offered')->assertSee($back->reference);
        $this->get(route('schedules.index'))->assertSee('Backload');

        // The driver gets it like any trip, labelled as a backload, and may decline it.
        Sanctum::actingAs($trip->driver->user);
        $this->getJson("/api/trips/{$back->id}")
            ->assertJsonPath('data.is_backload', true)
            ->assertJsonPath('data.backload_of', $trip->reference)
            ->assertJsonPath('data.can_decline', true);
    }

    public function test_no_backload_for_a_trip_that_is_pending_or_cancelled(): void
    {
        $pending = $this->firstTrip('pending');
        $dispatcher = $this->makeUser('dispatcher');

        $this->actingAs($dispatcher)->get(route('schedules.show', $pending))->assertDontSee('Offer backload');
        $this->get(route('schedules.create', ['backload_of' => $pending->id]))
            ->assertOk()
            ->assertDontSee('Backload for');
    }
}