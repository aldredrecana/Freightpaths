<?php

namespace Tests\Feature;

use App\Models\LocationPing;
use Tests\TestCase;

/** The truck icon on the map turns to face the direction of travel. */
class HeadingTest extends TestCase
{
    private function ping($trip, float $lat, float $lng, int $secondsAgo): void
    {
        LocationPing::create([
            'schedule_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => 10,
            'recorded_at' => now()->subSeconds($secondsAgo),
        ]);
    }

    private function heading($trip)
    {
        $trips = collect($this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('map.data'))->assertOk()->json('trips'));

        return $trips->firstWhere('id', $trip->id)['last']['heading'];
    }

    public function test_a_truck_driving_east_faces_east(): void
    {
        $trip = $this->makeTrip($this->makeDriver(), $this->makeTruck(), ['status' => 'en_route']);
        $this->ping($trip, 14.1000, 121.2000, 60);
        $this->ping($trip, 14.1000, 121.2010, 30); // about 108 m east
        $this->ping($trip, 14.1000, 121.2020, 5);

        $this->assertEqualsWithDelta(90, $this->heading($trip), 1);
    }

    public function test_a_truck_driving_north_faces_north(): void
    {
        $trip = $this->makeTrip($this->makeDriver(), $this->makeTruck(), ['status' => 'en_route']);
        $this->ping($trip, 14.1000, 121.2000, 30);
        $this->ping($trip, 14.1010, 121.2000, 5);

        $heading = $this->heading($trip);
        $this->assertTrue($heading < 1 || $heading > 359, "expected about 0, got {$heading}");
    }

    public function test_small_gps_jitter_is_not_a_direction(): void
    {
        $trip = $this->makeTrip($this->makeDriver(), $this->makeTruck(), ['status' => 'en_route']);
        $this->ping($trip, 14.10000, 121.20000, 30);
        $this->ping($trip, 14.10003, 121.20002, 5); // a few meters: standing still

        $this->assertNull($this->heading($trip));
    }

    public function test_the_trip_map_gets_the_heading_too(): void
    {
        $trip = $this->makeTrip($this->makeDriver(), $this->makeTruck(), ['status' => 'en_route']);
        $this->ping($trip, 14.1000, 121.2020, 30);
        $this->ping($trip, 14.1000, 121.2000, 5); // driving west

        $data = $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('schedules.tracking', $trip))->assertOk()->json();

        $this->assertEqualsWithDelta(270, $data['last']['heading'], 1);
        $this->assertCount(2, $data['path']);
    }
}