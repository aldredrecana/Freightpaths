<?php

namespace Tests\Feature;

use App\Models\LocationPing;
use Tests\TestCase;

/** The live fleet map: who sees it and which trips it lists. */
class MapTest extends TestCase
{
    public function test_staff_can_open_the_map_and_drivers_cannot(): void
    {
        $driver = $this->makeDriver();

        $this->get(route('map.index'))->assertRedirect(route('login'));

        $this->actingAs($driver->user)->get(route('map.index'))->assertForbidden();
        $this->actingAs($driver->user)->getJson(route('map.data'))->assertForbidden();

        $this->actingAs($this->makeUser('dispatcher'))->get(route('map.index'))
            ->assertOk()->assertSee('Live fleet tracking');
    }

    public function test_the_map_lists_only_trips_on_the_road(): void
    {
        $onTheRoad = $this->makeDriver();
        $this->makeTrip($onTheRoad, $this->makeTruck(), ['status' => 'en_route']);
        $this->makeTrip($this->makeDriver(), $this->makeTruck()); // pending
        $this->makeTrip($this->makeDriver(), $this->makeTruck(), ['status' => 'delivered']);

        $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('map.data'))
            ->assertOk()
            ->assertJsonCount(1, 'trips')
            ->assertJsonPath('trips.0.driver', $onTheRoad->user->name);
    }

    public function test_the_map_shows_each_drivers_last_position_and_status(): void
    {
        $a = $this->makeDriver();
        $b = $this->makeDriver();
        $tripA = $this->makeTrip($a, $this->makeTruck(), ['status' => 'en_route']);
        $this->makeTrip($b, $this->makeTruck(), ['status' => 'en_route']);

        LocationPing::create([
            'schedule_id' => $tripA->id,
            'driver_id' => $a->id,
            'latitude' => 14.10,
            'longitude' => 121.24,
            'accuracy' => 10,
            'recorded_at' => now()->subSeconds(20),
        ]);

        $trips = collect(
            $this->actingAs($this->makeUser('dispatcher'))->getJson(route('map.data'))->assertOk()->json('trips'),
        );

        $withPoint = $trips->firstWhere('driver', $a->user->name);
        $withoutPoint = $trips->firstWhere('driver', $b->user->name);

        $this->assertSame('tracking', $withPoint['state']);
        $this->assertEqualsWithDelta(14.10, $withPoint['last']['lat'], 0.0001);
        $this->assertSame(route('schedules.show', $tripA), $withPoint['url']);
        $this->assertSame(route('schedules.tracking', $tripA), $withPoint['tracking_url']);

        $this->assertSame('waiting', $withoutPoint['state']);
        $this->assertNull($withoutPoint['last']);
    }
}