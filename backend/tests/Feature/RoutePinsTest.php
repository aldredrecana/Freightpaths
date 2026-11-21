<?php

namespace Tests\Feature;

use App\Models\Schedule;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Pickup and drop-off pins: saving them, the address search, and showing the planned route. */
class RoutePinsTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'driver_id' => $this->makeDriver()->id,
            'truck_id' => $this->makeTruck()->id,
            'origin_address' => 'Calauan, Laguna',
            'destination_address' => 'Lipa City, Batangas',
            'departure_at' => now()->addDays(2)->setTime(8, 0)->format('Y-m-d\TH:i'),
            'expected_arrival_at' => now()->addDays(2)->setTime(12, 0)->format('Y-m-d\TH:i'),
        ];
    }

    public function test_a_trip_saves_its_pins(): void
    {
        $this->actingAs($this->makeUser('dispatcher'))
            ->post(route('schedules.store'), $this->payload([
                'origin_lat' => '14.1478000', 'origin_lng' => '121.3150000',
                'destination_lat' => '13.9411000', 'destination_lng' => '121.1631000',
            ]))
            ->assertRedirect(route('schedules.index'));

        $trip = Schedule::latest('id')->first();
        $this->assertSame(['origin' => [14.1478, 121.315], 'destination' => [13.9411, 121.1631]], $trip->routePlan());
    }

    public function test_pins_are_optional(): void
    {
        $this->actingAs($this->makeUser('dispatcher'))
            ->post(route('schedules.store'), $this->payload([
                'origin_lat' => '', 'origin_lng' => '', 'destination_lat' => '', 'destination_lng' => '',
            ]))
            ->assertRedirect(route('schedules.index'));

        $this->assertFalse(Schedule::latest('id')->first()->hasPins());
    }

    public function test_a_half_pin_or_a_point_off_the_earth_is_rejected(): void
    {
        $this->actingAs($this->makeUser('dispatcher'))
            ->post(route('schedules.store'), $this->payload(['origin_lat' => '14.1']))
            ->assertSessionHasErrors('origin_lng');

        $this->actingAs($this->makeUser('dispatcher'))
            ->post(route('schedules.store'), $this->payload(['destination_lat' => '95', 'destination_lng' => '121']))
            ->assertSessionHasErrors('destination_lat');
    }

    public function test_the_address_search_returns_a_map_point(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            ['lat' => '14.1478', 'lon' => '121.315', 'display_name' => 'Calauan, Laguna, Philippines'],
        ])]);

        $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('geocode', ['q' => 'Calauan, Laguna']))
            ->assertOk()
            ->assertJson(['found' => true, 'lat' => 14.1478, 'lng' => 121.315]);

        // Searched only in the Philippines, and the app says who it is.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'countrycodes=ph')
            && $request->hasHeader('User-Agent'));
    }

    public function test_an_unknown_address_is_not_found_and_a_down_service_is_explained(): void
    {
        Http::fake(['*' => Http::sequence()->push([])->push('', 500)]);
        $user = $this->makeUser('dispatcher');

        $this->actingAs($user)->getJson(route('geocode', ['q' => 'Nowhere 123']))
            ->assertOk()->assertJson(['found' => false]);

        $this->actingAs($user)->getJson(route('geocode', ['q' => 'Another place']))
            ->assertStatus(503)->assertJson(['found' => false]);
    }

        public function test_a_long_address_falls_back_to_a_shorter_version(): void
    {
        // The full address finds nothing; the version without "(Luneta)" and "1000" is found.
        Http::fake(['*' => Http::sequence()
            ->push([])
            ->push([['lat' => '14.5826', 'lon' => '120.9787', 'display_name' => 'Rizal Park, Ermita, Manila']])]);

        $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('geocode', ['q' => 'Rizal Park (Luneta), Roxas Boulevard, Ermita, Manila, 1000']))
            ->assertOk()
            ->assertJson(['found' => true, 'lat' => 14.5826, 'approximate' => true]);

        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'q=Rizal Park, Roxas Boulevard, Ermita, Manila&'));
    }

    public function test_drivers_cannot_use_the_address_search(): void
    {
        $this->actingAs($this->makeDriver()->user)
            ->getJson(route('geocode', ['q' => 'Calauan']))
            ->assertForbidden();
    }

    public function test_the_trip_page_shows_the_planned_route_before_the_trip_starts(): void
    {
        $withPins = $this->makeTrip($this->makeDriver(), $this->makeTruck(), [
            'origin_lat' => 14.1478, 'origin_lng' => 121.315,
            'destination_lat' => 13.9411, 'destination_lng' => 121.1631,
        ]);
        $withoutPins = $this->makeTrip($this->makeDriver(), $this->makeTruck(), [
            'departure_at' => now()->addDays(4), 'expected_arrival_at' => now()->addDays(4)->addHours(3),
        ]);
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->get(route('schedules.show', $withPins))
            ->assertOk()->assertSee('route-card')->assertSee('drawPlannedRoute', false);

        $this->actingAs($admin)->get(route('schedules.show', $withoutPins))
            ->assertOk()->assertDontSee('route-card');
    }

    public function test_the_driver_app_gets_the_pins(): void
    {
        $driver = $this->makeDriver();
        $trip = $this->makeTrip($driver, $this->makeTruck(), [
            'destination_lat' => 13.9411, 'destination_lng' => 121.1631,
        ]);

        $token = $driver->user->createToken('phone')->plainTextToken;

        $this->withToken($token)->getJson('/api/trips/'.$trip->id)
            ->assertOk()
            ->assertJsonPath('data.origin_point', null)
            ->assertJsonPath('data.destination_point', [13.9411, 121.1631]);
    }

    public function test_the_form_has_the_pin_map(): void
    {
        $this->actingAs($this->makeUser('dispatcher'))
            ->get(route('schedules.create'))
            ->assertOk()
            ->assertSee('routePicker', false)
            ->assertSee('Find pickup (A)');
    }

    public function test_the_edit_form_shows_the_saved_pins_and_can_remove_them(): void
    {
        $trip = $this->makeTrip($this->makeDriver(), $this->makeTruck(), [
            'origin_lat' => 14.1478, 'origin_lng' => 121.315,
        ]);
        $dispatcher = $this->makeUser('dispatcher');

        $this->actingAs($dispatcher)
            ->get(route('schedules.edit', $trip))
            ->assertOk()
            ->assertSee('14.1478');

        $this->actingAs($dispatcher)
            ->put(route('schedules.update', $trip), [
                'driver_id' => $trip->driver_id,
                'truck_id' => $trip->truck_id,
                'origin_address' => $trip->origin_address,
                'destination_address' => $trip->destination_address,
                'departure_at' => $trip->departure_at->format('Y-m-d\TH:i'),
                'expected_arrival_at' => $trip->expected_arrival_at->format('Y-m-d\TH:i'),
                'origin_lat' => '', 'origin_lng' => '',
                'destination_lat' => '', 'destination_lng' => '',
            ])
            ->assertRedirect(route('schedules.index'));

        $this->assertFalse($trip->fresh()->hasPins());
    }
}