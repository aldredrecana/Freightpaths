<?php

namespace Tests;

use App\Models\Driver;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** A user with the given role (admin, dispatcher or driver). The password is "password". */
    protected function makeUser(string $role = 'dispatcher', array $attributes = []): User
    {
        $roleId = Role::firstOrCreate(['name' => $role])->id;

        return User::factory()->create($attributes + ['role_id' => $roleId]);
    }

    /** A driver profile together with its login account. */
    protected function makeDriver(array $attributes = []): Driver
    {
        return Driver::create($attributes + [
            'user_id' => $this->makeUser('driver')->id,
            'license_no' => 'LIC-'.fake()->unique()->numerify('######'),
            'license_expiry' => now()->addYears(2)->toDateString(),
            'contact_no' => '09171234567',
            'address' => 'Calauan, Laguna',
            'status' => 'available',
        ]);
    }

    protected function makeTruck(array $attributes = []): Truck
    {
        return Truck::create($attributes + [
            'plate_no' => strtoupper(fake()->unique()->bothify('???-####')),
            'model' => 'Isuzu Elf',
            'capacity_kg' => 3000,
            'status' => 'available',
        ]);
    }

    /** A pending trip tomorrow morning, with its first timeline entry. */
    protected function makeTrip(Driver $driver, Truck $truck, array $attributes = []): Schedule
    {
        $trip = Schedule::create($attributes + [
            'driver_id' => $driver->id,
            'truck_id' => $truck->id,
            'origin_address' => 'Calauan, Laguna',
            'destination_address' => 'Batangas City, Batangas',
            'departure_at' => now()->addDay()->setTime(8, 0),
            'expected_arrival_at' => now()->addDay()->setTime(12, 0),
            'status' => 'pending',
            'assigned_at' => now(),
        ]);

        $trip->statusLogs()->create([
            'logged_by' => $this->makeUser('admin')->id,
            'status' => $trip->status,
            'logged_at' => now(),
        ]);

        return $trip;
    }
}
