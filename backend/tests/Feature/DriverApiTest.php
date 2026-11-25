<?php

namespace Tests\Feature;

use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\LocationPing;

/** The driver API used by the phone app: login, trips, status changes and decline. */
class DriverApiTest extends TestCase
{
    public function test_a_driver_can_log_in_and_use_the_token(): void
    {
        $driver = $this->makeDriver();

        $token = $this->postJson('/api/login', [
            'email' => $driver->user->email,
            'password' => 'password',
            'device_name' => 'test phone',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'driver_id', 'driver_status']])
            ->json('token');

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.name', $driver->user->name);
    }

    public function test_wrong_passwords_and_staff_accounts_cannot_log_in(): void
    {
        $driver = $this->makeDriver();
        $dispatcher = $this->makeUser('dispatcher');

        $this->postJson('/api/login', ['email' => $driver->user->email, 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/login', ['email' => $dispatcher->email, 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

        public function test_too_many_wrong_passwords_make_the_phone_wait(): void
    {
        $driver = $this->makeDriver();
        $email = $driver->user->email;

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/login', ['email' => $email, 'password' => 'wrong'])->assertUnprocessable();
        }

        // Even the right password must wait now.
        $this->postJson('/api/login', ['email' => $email, 'password' => 'password'])
            ->assertStatus(429)
            ->assertJsonStructure(['message', 'retry_after']);

        // After a minute the driver can log in again.
        $this->travel(61)->seconds();
        $this->postJson('/api/login', ['email' => $email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_the_trip_list_only_shows_the_drivers_own_trips(): void
    {
        $mine = $this->makeDriver();
        $other = $this->makeDriver();
        $myTrip = $this->makeTrip($mine, $this->makeTruck());
        $this->makeTrip($other, $this->makeTruck());

        Sanctum::actingAs($mine->user);

        $this->getJson('/api/trips')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $myTrip->id);
    }

    public function test_a_driver_moves_a_trip_forward_through_the_api(): void
    {
        $driver = $this->makeDriver();
        $trip = $this->makeTrip($driver, $this->makeTruck());
        Sanctum::actingAs($driver->user);

        $this->postJson("/api/trips/{$trip->id}/status", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('message', 'Trip accepted. Thank you!');

        $this->postJson("/api/trips/{$trip->id}/status", ['status' => 'en_route'])
            ->assertOk()
            ->assertJsonPath('data.status', 'en_route')
            ->assertJsonPath('data.allowed_next', ['delivered']);
    }

    public function test_a_status_cannot_skip_steps_through_the_api(): void
    {
        $driver = $this->makeDriver();
        $trip = $this->makeTrip($driver, $this->makeTruck());
        Sanctum::actingAs($driver->user);

        $this->postJson("/api/trips/{$trip->id}/status", ['status' => 'delivered'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame('pending', $trip->fresh()->status);
    }

    public function test_a_driver_can_decline_a_trip_through_the_api(): void
    {
        $driver = $this->makeDriver();
        $trip = $this->makeTrip($driver, $this->makeTruck());
        Sanctum::actingAs($driver->user);

        $this->postJson("/api/trips/{$trip->id}/decline", ['reason' => 'sick'])
            ->assertOk()
            ->assertJsonPath('data.status', 'needs_reassignment')
            ->assertJsonPath('message', 'You declined this trip. The dispatcher will assign another driver.');

        $this->assertSame('needs_reassignment', $trip->fresh()->status);
        $this->assertSame('Sick', $trip->statusLogs()->first()->remarks);
    }

    public function test_declining_as_other_needs_details(): void
    {
        $driver = $this->makeDriver();
        $trip = $this->makeTrip($driver, $this->makeTruck());
        Sanctum::actingAs($driver->user);

        $this->postJson("/api/trips/{$trip->id}/decline", ['reason' => 'other'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('details');

        $this->postJson("/api/trips/{$trip->id}/decline", ['reason' => 'other', 'details' => 'Family matter'])
            ->assertOk();

        $this->assertSame('Other: Family matter', $trip->statusLogs()->first()->remarks);
    }

    public function test_a_trip_on_the_road_cannot_be_declined(): void
    {
        $driver = $this->makeDriver();
        $trip = $this->makeTrip($driver, $this->makeTruck(), ['status' => 'en_route']);
        Sanctum::actingAs($driver->user);

        $this->postJson("/api/trips/{$trip->id}/decline", ['reason' => 'sick'])->assertForbidden();

        $this->assertSame('en_route', $trip->fresh()->status);
    }

    public function test_a_driver_cannot_see_or_change_someone_elses_trip(): void
    {
        $owner = $this->makeDriver();
        $trip = $this->makeTrip($owner, $this->makeTruck());
        Sanctum::actingAs($this->makeDriver()->user);

        $this->getJson("/api/trips/{$trip->id}")->assertForbidden();
        $this->postJson("/api/trips/{$trip->id}/status", ['status' => 'accepted'])->assertForbidden();
        $this->postJson("/api/trips/{$trip->id}/decline", ['reason' => 'sick'])->assertForbidden();

        $this->assertSame('pending', $trip->fresh()->status);
    }

        public function test_a_driver_can_get_the_route_driven_so_far(): void
    {
        $driver = $this->makeDriver();
        $trip = $this->makeTrip($driver, $this->makeTruck(), ['status' => 'en_route']);

        foreach ([3, 2, 1] as $minutesAgo) {
            LocationPing::create([
                'schedule_id' => $trip->id,
                'driver_id' => $driver->id,
                'latitude' => 14.10 + $minutesAgo * 0.001,
                'longitude' => 121.24,
                'accuracy' => 10,
                'recorded_at' => now()->subMinutes($minutesAgo),
            ]);
        }

        Sanctum::actingAs($driver->user);
        $this->getJson("/api/trips/{$trip->id}/track")
            ->assertOk()
            ->assertJsonCount(3, 'path');

        Sanctum::actingAs($this->makeDriver()->user);
        $this->getJson("/api/trips/{$trip->id}/track")->assertForbidden();
    }
}