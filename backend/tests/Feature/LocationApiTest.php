<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\LocationPing;
use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The phone's GPS upload: who may send, what is stored, and how rests are found. */
class LocationApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The same rule the app uses, written out so these tests do not depend on .env.
        config([
            'freightpath.rest_radius_m' => 100,
            'freightpath.rest_min_minutes' => 15,
            'freightpath.rest_max_gap_minutes' => 10,
            'freightpath.rest_max_accuracy_m' => 100,
        ]);
    }

    /** @return array{0: Driver, 1: Schedule} a driver with a trip that is on the road */
    private function onTheRoad(): array
    {
        $driver = $this->makeDriver();
        $trip = $this->makeTrip($driver, $this->makeTruck(), ['status' => 'en_route']);

        return [$driver, $trip];
    }

    private function point(Carbon $start, int $minute, float $lat): array
    {
        return [
            'lat' => $lat,
            'lng' => 121.24,
            'accuracy' => 10,
            'recorded_at' => $start->copy()->addMinutes($minute)->toIso8601String(),
        ];
    }

    /** 35 points, one per minute: driving, a 20 minute stop, then driving again. */
    private function tripPoints(): array
    {
        $start = now()->subHour()->startOfMinute();
        $points = [];

        for ($m = 0; $m < 10; $m++) { // driving, about 550 m per minute
            $points[] = $this->point($start, $m, 14.10 + $m * 0.005);
        }
        for ($m = 10; $m < 30; $m++) { // parked, about 20 m of GPS wobble
            $points[] = $this->point($start, $m, 14.15 + ($m % 2 === 0 ? 0.0002 : -0.0002));
        }
        for ($m = 30; $m < 35; $m++) { // driving again
            $points[] = $this->point($start, $m, 14.155 + ($m - 30) * 0.005);
        }

        return $points;
    }

    public function test_a_driver_can_send_points_for_a_trip_on_the_road(): void
    {
        [$driver, $trip] = $this->onTheRoad();
        Sanctum::actingAs($driver->user);

        $this->postJson('/api/locations', ['schedule_id' => $trip->id, 'pings' => $this->tripPoints()])
            ->assertOk()
            ->assertJsonPath('received', 35)
            ->assertJsonPath('stored', 35);

        $this->assertSame(35, LocationPing::where('schedule_id', $trip->id)->count());
    }

    public function test_sending_the_same_points_twice_creates_no_duplicates(): void
    {
        [$driver, $trip] = $this->onTheRoad();
        Sanctum::actingAs($driver->user);
        $payload = ['schedule_id' => $trip->id, 'pings' => $this->tripPoints()];

        $this->postJson('/api/locations', $payload)->assertOk()->assertJsonPath('stored', 35);
        $this->postJson('/api/locations', $payload)->assertOk()->assertJsonPath('stored', 0);

        $this->assertSame(35, LocationPing::count());
        $this->assertCount(1, $trip->restPeriods()->get()); // still one rest, not two
    }

    public function test_times_are_saved_as_the_same_moment_in_any_timezone(): void
    {
        [$driver, $trip] = $this->onTheRoad();
        Sanctum::actingAs($driver->user);

        $this->postJson('/api/locations', [
            'schedule_id' => $trip->id,
            'pings' => [['lat' => 14.1, 'lng' => 121.2, 'recorded_at' => '2026-01-15T00:00:00Z']],
        ])->assertOk();

        $saved = LocationPing::firstOrFail()->recorded_at;

        $this->assertSame(Carbon::parse('2026-01-15T00:00:00Z')->getTimestamp(), $saved->getTimestamp());
        $this->assertSame('08:00', $saved->format('H:i')); // Philippine time
    }

    public function test_a_long_stop_is_saved_as_one_rest_and_the_driver_can_see_it(): void
    {
        [$driver, $trip] = $this->onTheRoad();
        Sanctum::actingAs($driver->user);

        $this->postJson('/api/locations', ['schedule_id' => $trip->id, 'pings' => $this->tripPoints()])->assertOk();

        $rests = $trip->restPeriods()->get();
        $this->assertCount(1, $rests);
        $this->assertSame(19 * 60, $rests[0]->duration_seconds);
        $this->assertSame(20, $rests[0]->ping_count);

        $this->getJson("/api/trips/{$trip->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.rests')
            ->assertJsonPath('data.rests.0.minutes', 19);
    }

    public function test_points_are_refused_for_a_trip_that_is_not_on_the_road(): void
    {
        $driver = $this->makeDriver();
        $pending = $this->makeTrip($driver, $this->makeTruck()); // pending
        Sanctum::actingAs($driver->user);

        $this->postJson('/api/locations', ['schedule_id' => $pending->id, 'pings' => $this->tripPoints()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule_id');

        $this->assertSame(0, LocationPing::count());
    }

    public function test_late_points_are_accepted_just_after_delivery(): void
    {
        $driver = $this->makeDriver();
        $delivered = $this->makeTrip($driver, $this->makeTruck(), ['status' => 'delivered']);
        Sanctum::actingAs($driver->user);

        $this->postJson('/api/locations', ['schedule_id' => $delivered->id, 'pings' => $this->tripPoints()])
            ->assertOk();

        $this->assertSame(35, LocationPing::count());
    }

    public function test_a_driver_cannot_send_points_for_someone_elses_trip(): void
    {
        [, $trip] = $this->onTheRoad();
        $other = $this->makeDriver();
        Sanctum::actingAs($other->user);

        $this->postJson('/api/locations', ['schedule_id' => $trip->id, 'pings' => $this->tripPoints()])
            ->assertForbidden();

        $this->assertSame(0, LocationPing::count());
    }

    public function test_guests_and_staff_cannot_upload_points(): void
    {
        [, $trip] = $this->onTheRoad();
        $payload = ['schedule_id' => $trip->id, 'pings' => $this->tripPoints()];

        $this->postJson('/api/locations', $payload)->assertUnauthorized();

        Sanctum::actingAs($this->makeUser('dispatcher'));
        $this->postJson('/api/locations', $payload)->assertForbidden();

        $this->assertSame(0, LocationPing::count());
    }

    public function test_bad_points_are_rejected(): void
    {
        [$driver, $trip] = $this->onTheRoad();
        Sanctum::actingAs($driver->user);

        $this->postJson('/api/locations', ['schedule_id' => $trip->id, 'pings' => []])
            ->assertUnprocessable();

        $this->postJson('/api/locations', [
            'schedule_id' => $trip->id,
            'pings' => [['lat' => 200, 'lng' => 121, 'recorded_at' => now()->toIso8601String()]],
        ])->assertJsonValidationErrors('pings.0.lat');

        $this->postJson('/api/locations', [
            'schedule_id' => $trip->id,
            'pings' => [['lat' => 14, 'lng' => 121, 'recorded_at' => 'not a date']],
        ])->assertJsonValidationErrors('pings.0.recorded_at');

        $this->assertSame(0, LocationPing::count());
    }
}