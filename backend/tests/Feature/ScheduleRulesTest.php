<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Schedule;
use App\Models\Truck;
use Tests\TestCase;

/** Planning trips: validation and the scheduling rules (overlaps, licenses, availability). */
class ScheduleRulesTest extends TestCase
{
    /** Form data for a trip on the given day (day 1 = tomorrow), 08:00 to 12:00 unless overridden. */
    private function payload(Driver $driver, Truck $truck, array $overrides = [], int $day = 1): array
    {
        return $overrides + [
            'driver_id' => $driver->id,
            'truck_id' => $truck->id,
            'origin_address' => 'Calauan, Laguna',
            'destination_address' => 'Lipa City, Batangas',
            'departure_at' => now()->addDays($day)->setTime(8, 0)->format('Y-m-d\TH:i'),
            'expected_arrival_at' => now()->addDays($day)->setTime(12, 0)->format('Y-m-d\TH:i'),
        ];
    }

    private function create(array $payload, string $role = 'dispatcher')
    {
        return $this->actingAs($this->makeUser($role))->post(route('schedules.store'), $payload);
    }

    public function test_a_valid_trip_is_created_as_pending_with_a_first_timeline_entry(): void
    {
        $driver = $this->makeDriver();
        $truck = $this->makeTruck();

        $this->create($this->payload($driver, $truck))->assertRedirect(route('schedules.index'));

        $trip = Schedule::firstOrFail();
        $this->assertSame('pending', $trip->status);
        $this->assertNotNull($trip->assigned_at);
        $this->assertSame(1, $trip->statusLogs()->count());
        $this->assertSame('FP-'.str_pad((string) $trip->id, 4, '0', STR_PAD_LEFT), $trip->reference);
    }

    public function test_the_departure_must_be_in_the_future(): void
    {
        $payload = $this->payload($this->makeDriver(), $this->makeTruck(), [
            'departure_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'expected_arrival_at' => now()->addHour()->format('Y-m-d\TH:i'),
        ]);

        $this->create($payload)->assertSessionHasErrors('departure_at');
        $this->assertSame(0, Schedule::count());
    }

    public function test_the_arrival_must_be_after_the_departure(): void
    {
        $payload = $this->payload($this->makeDriver(), $this->makeTruck(), [
            'expected_arrival_at' => now()->addDay()->setTime(7, 0)->format('Y-m-d\TH:i'),
        ]);

        $this->create($payload)->assertSessionHasErrors('expected_arrival_at');
    }

    public function test_required_fields_are_validated(): void
    {
        $this->create([])->assertSessionHasErrors(['driver_id', 'truck_id', 'origin_address', 'destination_address', 'departure_at', 'expected_arrival_at']);
    }

    public function test_off_duty_drivers_and_trucks_in_maintenance_are_rejected(): void
    {
        $this->create($this->payload($this->makeDriver(['status' => 'off_duty']), $this->makeTruck()))
            ->assertSessionHasErrors('driver_id');

        $this->create($this->payload($this->makeDriver(), $this->makeTruck(['status' => 'maintenance'])))
            ->assertSessionHasErrors('truck_id');

        $this->assertSame(0, Schedule::count());
    }

    public function test_a_license_that_expires_before_departure_is_rejected(): void
    {
        $driver = $this->makeDriver(['license_expiry' => now()->subDay()->toDateString()]);

        $this->create($this->payload($driver, $this->makeTruck()))->assertSessionHasErrors('driver_id');
    }

    public function test_overlapping_trips_for_the_same_driver_or_truck_are_rejected(): void
    {
        $driverA = $this->makeDriver();
        $driverB = $this->makeDriver();
        $truck1 = $this->makeTruck();
        $truck2 = $this->makeTruck();
        $this->makeTrip($driverA, $truck1); // tomorrow 08:00-12:00

        $overlap = ['departure_at' => now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i'), 'expected_arrival_at' => now()->addDay()->setTime(14, 0)->format('Y-m-d\TH:i')];

        // Same driver, different truck.
        $this->create($this->payload($driverA, $truck2, $overlap))->assertSessionHasErrors('driver_id');
        // Same truck, different driver.
        $this->create($this->payload($driverB, $truck1, $overlap))->assertSessionHasErrors('driver_id');

        $this->assertSame(1, Schedule::count());
    }

    public function test_back_to_back_trips_are_allowed(): void
    {
        $driver = $this->makeDriver();
        $truck = $this->makeTruck();
        $this->makeTrip($driver, $truck); // 08:00-12:00

        $this->create($this->payload($driver, $truck, [
            'departure_at' => now()->addDay()->setTime(12, 0)->format('Y-m-d\TH:i'),
            'expected_arrival_at' => now()->addDay()->setTime(16, 0)->format('Y-m-d\TH:i'),
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, Schedule::count());
    }

    public function test_dispatchers_can_plan_ahead_for_a_driver_who_is_on_the_road_today(): void
    {
        $driver = $this->makeDriver(['status' => 'on_trip']);
        $truck = $this->makeTruck(['status' => 'on_trip']);

        $this->create($this->payload($driver, $truck, [], 3))->assertSessionHasNoErrors();

        $this->assertSame(1, Schedule::count());
    }

    public function test_only_pending_trips_can_be_deleted(): void
    {
        $admin = $this->makeUser('admin');
        $pending = $this->makeTrip($this->makeDriver(), $this->makeTruck());
        $accepted = $this->makeTrip($this->makeDriver(), $this->makeTruck(), ['status' => 'accepted']);

        $this->actingAs($admin)->delete(route('schedules.destroy', $accepted))->assertSessionHasErrors('delete');
        $this->assertNotNull($accepted->fresh());

        $this->actingAs($admin)->delete(route('schedules.destroy', $pending))->assertRedirect(route('schedules.index'));
        $this->assertNull($pending->fresh());
    }

    public function test_finished_and_en_route_trips_cannot_be_edited(): void
    {
        $staff = $this->makeUser('dispatcher');
        $driver = $this->makeDriver();
        $truck = $this->makeTruck();

        foreach (['delivered', 'cancelled', 'en_route'] as $i => $status) {
            $trip = $this->makeTrip($driver, $truck, [
                'status' => $status,
                'departure_at' => now()->addDays(10 + $i)->setTime(8, 0),
                'expected_arrival_at' => now()->addDays(10 + $i)->setTime(12, 0),
            ]);

            $this->actingAs($staff)->get(route('schedules.edit', $trip))->assertForbidden();
        }
    }

    public function test_finished_trips_cannot_be_updated(): void
    {
        $driver = $this->makeDriver();
        $truck = $this->makeTruck();
        $trip = $this->makeTrip($driver, $truck, ['status' => 'delivered']);

        $this->actingAs($this->makeUser('admin'))
            ->put(route('schedules.update', $trip), $this->payload($driver, $truck, [], 5))
            ->assertForbidden();
    }

    public function test_changing_the_driver_while_editing_is_logged_as_a_reassignment(): void
    {
        $old = $this->makeDriver();
        $new = $this->makeDriver();
        $truck = $this->makeTruck();
        $trip = $this->makeTrip($old, $truck, ['status' => 'accepted']);

        $this->actingAs($this->makeUser('dispatcher'))
            ->put(route('schedules.update', $trip), $this->payload($new, $truck, [
                'destination_address' => 'Tagaytay City, Cavite',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('schedules.index'));

        $trip->refresh();
        $this->assertSame($new->id, $trip->driver_id);
        $this->assertSame('pending', $trip->status); // the new driver must accept
        $this->assertSame('Tagaytay City, Cavite', $trip->destination_address);
        $this->assertStringContainsString('Reassigned from', $trip->statusLogs()->first()->remarks);
    }

    public function test_search_and_status_filter_on_the_schedules_page(): void
    {
        $staff = $this->makeUser('dispatcher');
        $driver = $this->makeDriver();
        $truck = $this->makeTruck(['plate_no' => 'SRCH 111']);
        $this->makeTrip($driver, $truck, ['destination_address' => 'Zamboanga Special']);
        $this->makeTrip($this->makeDriver(), $this->makeTruck(), ['status' => 'accepted', 'destination_address' => 'Elsewhere Town']);

        $this->actingAs($staff)->get('/schedules?q=zamboanga')->assertSee('Zamboanga Special')->assertDontSee('Elsewhere Town');
        $this->actingAs($staff)->get('/schedules?q=SRCH')->assertSee('Zamboanga Special')->assertDontSee('Elsewhere Town');
        $this->actingAs($staff)->get('/schedules?status=accepted')->assertSee('Elsewhere Town')->assertDontSee('Zamboanga Special');
    }

    public function test_finished_trips_leave_the_schedules_page_and_appear_in_trip_history(): void
    {
        $staff = $this->makeUser('dispatcher');
        $driver = $this->makeDriver();
        $trip = $this->makeTrip($driver, $this->makeTruck(), ['status' => 'delivered', 'destination_address' => 'Finished Place']);

        $this->actingAs($staff)->get('/schedules')->assertDontSee('Finished Place');
        $this->actingAs($staff)->get('/transactions')->assertOk()->assertSee($trip->reference);
    }
}
