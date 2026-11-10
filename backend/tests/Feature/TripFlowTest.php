<?php

namespace Tests\Feature;

use Tests\TestCase;

/** A trip's life: accept, start, deliver, cancel, decline, and reassignment. */
class TripFlowTest extends TestCase
{
    public function test_a_driver_takes_a_trip_from_pending_to_delivered(): void
    {
        $driver = $this->makeDriver();
        $truck = $this->makeTruck();
        $trip = $this->makeTrip($driver, $truck);

        $this->actingAs($driver->user)->post(route('schedules.status', $trip), ['status' => 'accepted'])
            ->assertSessionHasNoErrors();
        $this->assertSame('accepted', $trip->fresh()->status);
        $this->assertSame('available', $truck->fresh()->status); // not on the road yet

        $this->actingAs($driver->user)->post(route('schedules.status', $trip), ['status' => 'en_route']);
        $this->assertSame('en_route', $trip->fresh()->status);
        $this->assertSame('on_trip', $truck->fresh()->status);
        $this->assertSame('on_trip', $driver->fresh()->status);

        $this->actingAs($driver->user)->post(route('schedules.status', $trip), ['status' => 'delivered']);
        $this->assertSame('delivered', $trip->fresh()->status);
        $this->assertSame('available', $truck->fresh()->status);
        $this->assertSame('available', $driver->fresh()->status);

        // pending + accepted + en_route + delivered
        $this->assertSame(4, $trip->statusLogs()->count());
    }

    public function test_a_status_cannot_skip_steps(): void
    {
        $driver = $this->makeDriver();
        $trip = $this->makeTrip($driver, $this->makeTruck());

        $this->actingAs($driver->user)->post(route('schedules.status', $trip), ['status' => 'delivered'])
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $trip->fresh()->status);
    }

    public function test_staff_can_cancel_but_cannot_move_a_trip_forward(): void
    {
        $dispatcher = $this->makeUser('dispatcher');
        $trip = $this->makeTrip($this->makeDriver(), $this->makeTruck());

        $this->actingAs($dispatcher)->post(route('schedules.status', $trip), ['status' => 'accepted'])
            ->assertSessionHasErrors('status');
        $this->assertSame('pending', $trip->fresh()->status);

        $this->actingAs($dispatcher)->post(route('schedules.status', $trip), ['status' => 'cancelled', 'remarks' => 'Customer cancelled']);
        $this->assertSame('cancelled', $trip->fresh()->status);
    }

    public function test_drivers_cannot_cancel_a_trip(): void
    {
        $driver = $this->makeDriver();
        $trip = $this->makeTrip($driver, $this->makeTruck());

        $this->actingAs($driver->user)->post(route('schedules.status', $trip), ['status' => 'cancelled'])
            ->assertSessionHasErrors('status');
    }

    public function test_a_driver_or_truck_cannot_be_on_two_trips_at_once(): void
    {
        $driver = $this->makeDriver();
        $other = $this->makeDriver();
        $truck = $this->makeTruck();

        $this->makeTrip($driver, $truck, ['status' => 'en_route']);
        $secondForSameDriver = $this->makeTrip($driver, $this->makeTruck(), [
            'status' => 'accepted',
            'departure_at' => now()->addDays(5)->setTime(8, 0),
            'expected_arrival_at' => now()->addDays(5)->setTime(12, 0),
        ]);
        $secondForSameTruck = $this->makeTrip($other, $truck, [
            'status' => 'accepted',
            'departure_at' => now()->addDays(6)->setTime(8, 0),
            'expected_arrival_at' => now()->addDays(6)->setTime(12, 0),
        ]);

        $this->actingAs($driver->user)->post(route('schedules.status', $secondForSameDriver), ['status' => 'en_route'])
            ->assertSessionHasErrors('status');
        $this->actingAs($other->user)->post(route('schedules.status', $secondForSameTruck), ['status' => 'en_route'])
            ->assertSessionHasErrors('status');

        $this->assertSame('accepted', $secondForSameDriver->fresh()->status);
        $this->assertSame('accepted', $secondForSameTruck->fresh()->status);
    }

    public function test_a_trip_cannot_start_with_a_truck_in_maintenance_or_a_driver_off_duty(): void
    {
        $driver = $this->makeDriver();
        $truck = $this->makeTruck();
        $trip = $this->makeTrip($driver, $truck, ['status' => 'accepted']);

        $truck->update(['status' => 'maintenance']);
        $this->actingAs($driver->user)->post(route('schedules.status', $trip), ['status' => 'en_route'])
            ->assertSessionHasErrors('status');
        $this->assertSame('maintenance', $truck->fresh()->status);

        $truck->update(['status' => 'available']);
        $driver->update(['status' => 'off_duty']);
        $this->actingAs($driver->user)->post(route('schedules.status', $trip), ['status' => 'en_route'])
            ->assertSessionHasErrors('status');
        $this->assertSame('off_duty', $driver->fresh()->status);
        $this->assertSame('accepted', $trip->fresh()->status);
    }

    public function test_finishing_a_trip_never_overrides_maintenance_or_off_duty(): void
    {
        $driver = $this->makeDriver(['status' => 'off_duty']);
        $truck = $this->makeTruck(['status' => 'maintenance']);
        $trip = $this->makeTrip($driver, $truck);

        $this->actingAs($this->makeUser('dispatcher'))->post(route('schedules.status', $trip), ['status' => 'cancelled']);

        $this->assertSame('cancelled', $trip->fresh()->status);
        $this->assertSame('off_duty', $driver->fresh()->status);
        $this->assertSame('maintenance', $truck->fresh()->status);
    }

    public function test_cancelling_a_trip_on_the_road_frees_the_driver_and_truck(): void
    {
        $driver = $this->makeDriver(['status' => 'on_trip']);
        $truck = $this->makeTruck(['status' => 'on_trip']);
        $trip = $this->makeTrip($driver, $truck, ['status' => 'en_route']);

        $this->actingAs($this->makeUser('admin'))->post(route('schedules.status', $trip), ['status' => 'cancelled']);

        $this->assertSame('available', $driver->fresh()->status);
        $this->assertSame('available', $truck->fresh()->status);
    }

    public function test_declining_takes_effect_immediately_and_flags_late_declines(): void
    {
        $driver = $this->makeDriver();
        $soon = $this->makeTrip($driver, $this->makeTruck(), [
            'departure_at' => now()->addHour(),
            'expected_arrival_at' => now()->addHours(5),
        ]);
        $later = $this->makeTrip($driver, $this->makeTruck(), [
            'departure_at' => now()->addDays(4)->setTime(8, 0),
            'expected_arrival_at' => now()->addDays(4)->setTime(12, 0),
        ]);

        $this->actingAs($driver->user)->post(route('schedules.decline', $soon), ['reason' => 'sick'])
            ->assertSessionHasNoErrors();
        $this->actingAs($driver->user)->post(route('schedules.decline', $later), ['reason' => 'emergency']);

        $this->assertSame('needs_reassignment', $soon->fresh()->status);
        $this->assertTrue($soon->statusLogs()->first()->is_late);
        $this->assertSame('Sick', $soon->statusLogs()->first()->remarks);
        $this->assertFalse($later->statusLogs()->first()->is_late);
    }

    public function test_declining_needs_a_reason_and_details_for_other(): void
    {
        $driver = $this->makeDriver();
        $trip = $this->makeTrip($driver, $this->makeTruck());

        $this->actingAs($driver->user)->post(route('schedules.decline', $trip), [])
            ->assertSessionHasErrors('reason');
        $this->actingAs($driver->user)->post(route('schedules.decline', $trip), ['reason' => 'other'])
            ->assertSessionHasErrors('details');

        $this->assertSame('pending', $trip->fresh()->status);
    }

    public function test_only_the_assigned_driver_can_decline(): void
    {
        $trip = $this->makeTrip($this->makeDriver(), $this->makeTruck());

        $this->actingAs($this->makeDriver()->user)->post(route('schedules.decline', $trip), ['reason' => 'sick'])
            ->assertForbidden();
        $this->actingAs($this->makeUser('dispatcher'))->post(route('schedules.decline', $trip), ['reason' => 'sick'])
            ->assertForbidden();
    }

    public function test_a_declined_trip_is_reassigned_to_an_eligible_driver(): void
    {
        $decliner = $this->makeDriver();
        $replacement = $this->makeDriver();
        $trip = $this->makeTrip($decliner, $this->makeTruck());
        $dispatcher = $this->makeUser('dispatcher');

        $this->actingAs($decliner->user)->post(route('schedules.decline', $trip), ['reason' => 'sick']);

        // The driver who declined is not offered the trip again.
        $this->actingAs($dispatcher)->post(route('schedules.reassign', $trip), ['driver_id' => $decliner->id])
            ->assertSessionHasErrors('driver_id');

        $this->actingAs($dispatcher)->post(route('schedules.reassign', $trip), ['driver_id' => $replacement->id, 'note' => 'Called him first'])
            ->assertSessionHasNoErrors();

        $trip->refresh();
        $this->assertSame($replacement->id, $trip->driver_id);
        $this->assertSame('pending', $trip->status); // the new driver must accept it
        $this->assertStringContainsString('Reassigned from', $trip->statusLogs()->first()->remarks);

        // The new driver can now accept; the old one can no longer open it.
        $this->actingAs($replacement->user)->post(route('schedules.status', $trip), ['status' => 'accepted']);
        $this->assertSame('accepted', $trip->fresh()->status);
        $this->actingAs($decliner->user)->get(route('schedules.show', $trip))->assertForbidden();
    }

    public function test_reassignment_rejects_off_duty_busy_and_expired_license_drivers(): void
    {
        $original = $this->makeDriver();
        $trip = $this->makeTrip($original, $this->makeTruck());
        $dispatcher = $this->makeUser('dispatcher');

        $offDuty = $this->makeDriver(['status' => 'off_duty']);
        $expired = $this->makeDriver(['license_expiry' => now()->subDay()->toDateString()]);
        $busy = $this->makeDriver();
        $this->makeTrip($busy, $this->makeTruck()); // same time window as $trip

        foreach ([$offDuty, $expired, $busy] as $candidate) {
            $this->actingAs($dispatcher)->post(route('schedules.reassign', $trip), ['driver_id' => $candidate->id])
                ->assertSessionHasErrors('driver_id');
        }

        $this->assertSame($original->id, $trip->fresh()->driver_id);
    }

    public function test_drivers_cannot_reassign_trips(): void
    {
        $driver = $this->makeDriver();
        $trip = $this->makeTrip($driver, $this->makeTruck());

        $this->actingAs($driver->user)->post(route('schedules.reassign', $trip), ['driver_id' => $this->makeDriver()->id])
            ->assertForbidden();
    }

    public function test_my_deliveries_lists_only_the_drivers_own_trips(): void
    {
        $mine = $this->makeDriver();
        $other = $this->makeDriver();
        $myTrip = $this->makeTrip($mine, $this->makeTruck(), ['destination_address' => 'Mine Destination']);
        $this->makeTrip($other, $this->makeTruck(), ['destination_address' => 'Their Destination']);

        $this->actingAs($mine->user)->get('/my-deliveries')
            ->assertOk()->assertSee('Mine Destination')->assertDontSee('Their Destination');

        $this->actingAs($mine->user)->get(route('schedules.show', $myTrip))
            ->assertOk()->assertSee('Accept trip');
    }
}
