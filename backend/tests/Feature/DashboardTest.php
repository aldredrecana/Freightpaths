<?php

namespace Tests\Feature;

use Tests\TestCase;

/** The home screen is different for staff and for drivers. */
class DashboardTest extends TestCase
{
    public function test_staff_see_alerts_counts_and_todays_trips(): void
    {
        $driver = $this->makeDriver();
        $today = $this->makeTrip($driver, $this->makeTruck(), [
            'departure_at' => now()->addMinutes(30),
            'expected_arrival_at' => now()->addHours(4),
            'destination_address' => 'Todays Destination',
        ]);

        $this->actingAs($this->makeUser('admin'))->get('/dashboard')
            ->assertOk()
            ->assertViewIs('dashboard')
            ->assertSee('Todays Destination')
            ->assertSee('urgent or overdue')
            ->assertViewHas('alerts', fn ($alerts) => $alerts['urgent'] === 1);

        $this->assertSame('urgent', $today->urgency);
    }

    public function test_staff_see_all_clear_when_nothing_needs_attention(): void
    {
        $this->actingAs($this->makeUser('dispatcher'))->get('/dashboard')
            ->assertOk()->assertSee('All clear');
    }

    public function test_a_license_that_is_expiring_is_flagged(): void
    {
        $this->makeDriver(['license_expiry' => now()->addDays(10)->toDateString()]);

        $this->actingAs($this->makeUser('admin'))->get('/dashboard')
            ->assertViewHas('alerts', fn ($alerts) => $alerts['licenses'] === 1)
            ->assertSee('Licenses to renew');
    }

    public function test_drivers_see_their_current_and_new_trips(): void
    {
        $driver = $this->makeDriver();

        $this->actingAs($driver->user)->get('/dashboard')
            ->assertOk()->assertViewIs('dashboard-driver')->assertSee('No active trip right now.');

        $this->makeTrip($driver, $this->makeTruck(), ['destination_address' => 'Brand New Trip']);
        $this->actingAs($driver->user)->get('/dashboard')->assertSee('Brand New Trip')->assertSee('Waiting for you to accept');

        $current = $this->makeTrip($driver, $this->makeTruck(), [
            'status' => 'accepted',
            'destination_address' => 'Current Trip',
            'departure_at' => now()->addDays(2)->setTime(8, 0),
            'expected_arrival_at' => now()->addDays(2)->setTime(12, 0),
        ]);
        $this->actingAs($driver->user)->get('/dashboard')
            ->assertSee('Current Trip')
            ->assertSee(route('schedules.show', $current)); // the "Open trip" button links here
    }

    public function test_the_transactions_page_shows_the_timeline_of_a_finished_trip(): void
    {
        $driver = $this->makeDriver();
        $trip = $this->makeTrip($driver, $this->makeTruck(), ['status' => 'delivered']);

        $this->actingAs($this->makeUser('admin'))->get('/transactions?trip='.$trip->id)
            ->assertOk()->assertSee($trip->reference)->assertSee('Trip journey');
    }
}
