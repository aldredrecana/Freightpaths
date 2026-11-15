<?php

namespace Tests\Feature;

use App\Models\LocationPing;
use App\Models\RestPeriod;
use App\Models\Schedule;
use Carbon\CarbonInterface;
use Tests\TestCase;

/** What the dispatcher sees for one trip: status, route and rests. */
class TrackingTest extends TestCase
{
    private function onTheRoad(): Schedule
    {
        return $this->makeTrip($this->makeDriver(), $this->makeTruck(), ['status' => 'en_route']);
    }

    private function ping(Schedule $trip, CarbonInterface $at, float $lat = 14.10): void
    {
        LocationPing::create([
            'schedule_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'latitude' => $lat,
            'longitude' => 121.24,
            'accuracy' => 10,
            'recorded_at' => $at,
        ]);
    }

    private function rest(Schedule $trip, CarbonInterface $from, CarbonInterface $to): void
    {
        RestPeriod::create([
            'schedule_id' => $trip->id,
            'started_at' => $from,
            'ended_at' => $to,
            'duration_seconds' => $to->getTimestamp() - $from->getTimestamp(),
            'latitude' => 14.10,
            'longitude' => 121.24,
            'ping_count' => 40,
        ]);
    }

    public function test_only_staff_can_open_the_tracking_data(): void
    {
        $trip = $this->onTheRoad();
        $url = route('schedules.tracking', $trip);

        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($trip->driver->user)->getJson($url)->assertForbidden();
        $this->actingAs($this->makeUser('dispatcher'))->getJson($url)->assertOk();
        $this->actingAs($this->makeUser('admin'))->getJson($url)->assertOk();
    }

    public function test_a_trip_without_points_waits_for_gps(): void
    {
        $trip = $this->onTheRoad();

        $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('schedules.tracking', $trip))
            ->assertOk()
            ->assertJsonPath('state', 'waiting')
            ->assertJsonPath('last', null)
            ->assertJsonCount(0, 'path');
    }

    public function test_a_fresh_point_means_on_the_road(): void
    {
        $trip = $this->onTheRoad();
        $this->ping($trip, now()->subSeconds(30));

        $response = $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('schedules.tracking', $trip))
            ->assertOk()
            ->assertJsonPath('state', 'tracking')
            ->assertJsonPath('label', 'On the road');

        $this->assertEqualsWithDelta(14.10, $response->json('last.lat'), 0.0001);
    }

    public function test_an_old_point_means_no_signal(): void
    {
        $trip = $this->onTheRoad();
        $this->ping($trip, now()->subMinutes(30));

        $response = $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('schedules.tracking', $trip))
            ->assertOk()
            ->assertJsonPath('state', 'offline')
            ->assertJsonPath('label', 'No signal');

        $this->assertStringContainsString('No GPS point for', $response->json('message'));
    }

    public function test_a_current_rest_means_resting(): void
    {
        $trip = $this->onTheRoad();
        $this->ping($trip, now()->subSeconds(30));
        $this->rest($trip, now()->subMinutes(20), now()->subSeconds(30));

        $response = $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('schedules.tracking', $trip))
            ->assertOk()
            ->assertJsonPath('state', 'resting')
            ->assertJsonCount(1, 'rests');

        $this->assertStringContainsString('Resting for 19 minutes', $response->json('message'));
    }

    public function test_a_finished_trip_shows_its_whole_route(): void
    {
        $trip = $this->makeTrip($this->makeDriver(), $this->makeTruck(), ['status' => 'delivered']);
        $this->ping($trip, now()->subHour(), 14.10);
        $this->ping($trip, now()->subMinutes(50), 14.11);
        $this->ping($trip, now()->subMinutes(40), 14.12);
        $this->rest($trip, now()->subMinutes(50), now()->subMinutes(40));

        $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('schedules.tracking', $trip))
            ->assertOk()
            ->assertJsonPath('state', 'finished')
            ->assertJsonCount(3, 'path')
            ->assertJsonCount(1, 'rests');
    }

    public function test_the_trip_page_shows_the_map_only_for_trips_that_have_started(): void
    {
        $dispatcher = $this->makeUser('dispatcher');
        $pending = $this->makeTrip($this->makeDriver(), $this->makeTruck());
        $onTheRoad = $this->onTheRoad();

        $this->actingAs($dispatcher)->get(route('schedules.show', $pending))
            ->assertOk()->assertDontSee('tracking-card');

        $this->actingAs($dispatcher)->get(route('schedules.show', $onTheRoad))
            ->assertOk()->assertSee('tracking-card');
    }
}