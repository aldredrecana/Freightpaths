<?php

namespace Tests\Feature;

use App\Models\LocationPing;
use Tests\TestCase;

/** The alerts bell for dispatchers: declined trips, no GPS signal, trips not accepted yet. */
class AlertsTest extends TestCase
{
    private function onTheRoad(array $attributes = [])
    {
        $trip = $this->makeTrip($this->makeDriver(), $this->makeTruck(), $attributes + [
            'status' => 'en_route',
            'departure_at' => now()->subHours(2),
            'expected_arrival_at' => now()->addHours(2),
        ]);
        $trip->statusLogs()->create([
            'logged_by' => $trip->driver->user_id,
            'status' => 'en_route',
            'logged_at' => now()->subHours(2),
        ]);

        return $trip;
    }

    private function ping($trip, $minutesAgo): void
    {
        LocationPing::create([
            'schedule_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'latitude' => 14.17,
            'longitude' => 121.24,
            'accuracy' => 10,
            'speed' => 0,
            'recorded_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_a_declined_trip_shows_as_an_alert_with_the_reason(): void
    {
        $trip = $this->makeTrip($this->makeDriver(), $this->makeTruck(), ['status' => 'needs_reassignment']);
        $trip->statusLogs()->create([
            'logged_by' => $trip->driver->user_id,
            'status' => 'needs_reassignment',
            'remarks' => 'Sick',
            'logged_at' => now(),
        ]);

        $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('alerts.index'))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.type', 'declined')
            ->assertJsonPath('items.0.url', route('schedules.show', $trip));

        $this->assertStringContainsString('Sick', $this->actingAs($this->makeUser('admin'))
            ->getJson(route('alerts.index'))->json('items.0.detail'));
    }

    public function test_a_phone_silent_for_too_long_shows_no_signal(): void
    {
        $silent = $this->onTheRoad();
        $this->ping($silent, 30); // last point 30 minutes ago, the limit is 10

        $fine = $this->onTheRoad();
        $this->ping($fine, 1);

        $items = $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('alerts.index'))
            ->assertOk()
            ->json('items');

        $this->assertCount(1, $items);
        $this->assertSame('no_signal', $items[0]['type']);
        $this->assertSame('no_signal-'.$silent->id, $items[0]['key']);
    }

    public function test_a_trip_that_never_sent_gps_shows_no_signal(): void
    {
        $trip = $this->onTheRoad();

        $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('alerts.index'))
            ->assertJsonPath('items.0.key', 'no_signal-'.$trip->id);
    }

    public function test_a_trip_leaving_soon_without_acceptance_is_urgent(): void
    {
        $this->makeTrip($this->makeDriver(), $this->makeTruck(), [
            'departure_at' => now()->addHour(),
            'expected_arrival_at' => now()->addHours(5),
        ]);
        // Far in the future: not urgent yet.
        $this->makeTrip($this->makeDriver(), $this->makeTruck(), [
            'departure_at' => now()->addDays(3),
            'expected_arrival_at' => now()->addDays(3)->addHours(4),
        ]);

        $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('alerts.index'))
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.type', 'urgent');
    }

    public function test_all_clear_when_nothing_needs_attention(): void
    {
        $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('alerts.index'))
            ->assertJsonPath('count', 0);
    }

    public function test_drivers_cannot_see_staff_alerts(): void
    {
        $this->actingAs($this->makeDriver()->user)
            ->getJson(route('alerts.index'))
            ->assertForbidden();
    }

    public function test_the_bell_is_on_staff_pages_and_the_dashboard_counts_no_signal(): void
    {
        $this->onTheRoad();

        $this->actingAs($this->makeUser('dispatcher'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('alertsBell', false)
            ->assertSee('trip(s) with no GPS signal');
    }
}