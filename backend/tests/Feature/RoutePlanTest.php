<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The planned road route A -> B, and filling in an address from a map pin. */
class RoutePlanTest extends TestCase
{
    private function fakeOsrm(): void
    {
        config(['freightpath.snap_to_roads' => true]);
        Http::fake(fn (Request $r) => Http::response(['code' => 'Ok', 'routes' => [[
            'geometry' => ['coordinates' => [[121.24, 14.10], [121.25, 14.12], [121.26, 14.15]]],
            'distance' => 42350,
            'duration' => 3900,
        ]]]));
    }

    public function test_staff_get_the_road_route_between_two_pins(): void
    {
        $this->fakeOsrm();

        $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('route.preview', ['from_lat' => 14.10, 'from_lng' => 121.24, 'to_lat' => 14.15, 'to_lng' => 121.26]))
            ->assertOk()
            ->assertJson(['on_roads' => true, 'km' => 42.4, 'minutes' => 65])
            ->assertJsonCount(3, 'points');
    }

    public function test_without_osrm_the_route_is_a_straight_line(): void
    {
        $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('route.preview', ['from_lat' => 14.10, 'from_lng' => 121.24, 'to_lat' => 14.15, 'to_lng' => 121.26]))
            ->assertOk()
            ->assertJson(['on_roads' => false, 'points' => [[14.1, 121.24], [14.15, 121.26]]]);
    }

    public function test_drivers_cannot_use_the_staff_route_preview(): void
    {
        $this->actingAs($this->makeDriver()->user)
            ->getJson(route('route.preview', ['from_lat' => 14.10, 'from_lng' => 121.24, 'to_lat' => 14.15, 'to_lng' => 121.26]))
            ->assertForbidden();
    }

    public function test_the_app_gets_the_road_route_of_its_own_trip_only(): void
    {
        $this->fakeOsrm();
        $driver = $this->makeDriver();
        $trip = $this->makeTrip($driver, $this->makeTruck(), [
            'origin_lat' => 14.10, 'origin_lng' => 121.24,
            'destination_lat' => 14.15, 'destination_lng' => 121.26,
        ]);

        Sanctum::actingAs($driver->user);
        $this->getJson("/api/trips/{$trip->id}/route")
            ->assertOk()
            ->assertJson(['on_roads' => true, 'km' => 42.4]);

        Sanctum::actingAs($this->makeDriver()->user);
        $this->getJson("/api/trips/{$trip->id}/route")->assertForbidden();
    }

    public function test_a_pin_is_turned_into_a_short_address(): void
    {
        Http::fake(['*' => Http::response([
            'display_name' => 'Vitra Building, P. Alcantara Street, San Pablo, Laguna, Calabarzon, 4000, Philippines',
        ])]);

        $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('geocode.reverse', ['lat' => 14.0697, 'lng' => 121.3253]))
            ->assertOk()
            ->assertJson(['found' => true, 'label' => 'Vitra Building, P. Alcantara Street, San Pablo, Laguna']);
    }

    public function test_reverse_lookup_reports_when_the_service_is_down(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);

        $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('geocode.reverse', ['lat' => 14.0697, 'lng' => 121.3253]))
            ->assertStatus(503)
            ->assertJson(['found' => false]);
    }
}