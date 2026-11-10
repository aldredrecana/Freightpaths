<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Truck;
use App\Models\User;
use Tests\TestCase;

/** Drivers and trucks: creating, validation, and protected deletes. */
class CrudTest extends TestCase
{
    private function driverPayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.test',
            'password' => 'a-strong-password-123',
            'password_confirmation' => 'a-strong-password-123',
            'license_no' => 'N01-23-456789',
            'license_expiry' => now()->addYear()->toDateString(),
            'contact_no' => '09171234567',
            'address' => 'Calauan, Laguna',
            'status' => 'available',
        ];
    }

    public function test_admin_creates_a_driver_with_a_login_account(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->post(route('drivers.store'), $this->driverPayload())
            ->assertRedirect(route('drivers.index'));

        $user = User::where('email', 'juan@example.test')->firstOrFail();

        $this->assertTrue($user->hasRole('driver'));
        $this->assertSame('N01-23-456789', $user->driver->license_no);
    }

    public function test_driver_validation_rejects_bad_input(): void
    {
        $admin = $this->makeUser('admin');
        $existing = $this->makeDriver(['license_no' => 'TAKEN-1']);

        $this->actingAs($admin)
            ->post(route('drivers.store'), $this->driverPayload(['license_expiry' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors('license_expiry');

        $this->actingAs($admin)
            ->post(route('drivers.store'), $this->driverPayload(['license_no' => 'TAKEN-1']))
            ->assertSessionHasErrors('license_no');

        $this->actingAs($admin)
            ->post(route('drivers.store'), $this->driverPayload(['email' => $existing->user->email]))
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)
            ->post(route('drivers.store'), $this->driverPayload(['status' => 'flying']))
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)
            ->post(route('drivers.store'), $this->driverPayload(['password_confirmation' => 'different']))
            ->assertSessionHasErrors('password');
    }

    public function test_dispatchers_cannot_create_drivers(): void
    {
        $this->actingAs($this->makeUser('dispatcher'))
            ->post(route('drivers.store'), $this->driverPayload())
            ->assertForbidden();

        $this->assertSame(0, Driver::count());
    }

    public function test_admin_updates_a_driver_and_keeps_the_password_when_left_blank(): void
    {
        $driver = $this->makeDriver();
        $oldHash = $driver->user->password;

        $this->actingAs($this->makeUser('admin'))
            ->put(route('drivers.update', $driver), $this->driverPayload([
                'email' => $driver->user->email,
                'license_no' => $driver->license_no,
                'name' => 'Renamed Driver',
                'password' => '',
                'password_confirmation' => '',
                'status' => 'off_duty',
            ]))
            ->assertRedirect(route('drivers.index'));

        $driver->refresh();

        $this->assertSame('Renamed Driver', $driver->user->name);
        $this->assertSame('off_duty', $driver->status);
        $this->assertSame($oldHash, $driver->user->password);
    }

    public function test_a_driver_without_history_can_be_deleted(): void
    {
        $driver = $this->makeDriver();

        $this->actingAs($this->makeUser('admin'))
            ->delete(route('drivers.destroy', $driver))
            ->assertRedirect(route('drivers.index'));

        $this->assertSame(0, Driver::count());
    }

    public function test_a_driver_with_trips_cannot_be_deleted(): void
    {
        $driver = $this->makeDriver();
        $this->makeTrip($driver, $this->makeTruck());

        $this->actingAs($this->makeUser('admin'))
            ->delete(route('drivers.destroy', $driver))
            ->assertSessionHasErrors('delete');
    }

    public function test_admin_creates_a_truck_and_validation_rejects_bad_input(): void
    {
        $admin = $this->makeUser('admin');
        $payload = ['plate_no' => 'ABC 1234', 'model' => 'Hino 300', 'capacity_kg' => 4000, 'status' => 'available'];

        $this->actingAs($admin)->post(route('trucks.store'), $payload)->assertRedirect(route('trucks.index'));
        $this->assertSame(1, Truck::count());

        $this->actingAs($admin)->post(route('trucks.store'), $payload)->assertSessionHasErrors('plate_no');
        $this->actingAs($admin)->post(route('trucks.store'), ['plate_no' => 'XYZ 1'] + $payload)
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('trucks.store'), ['plate_no' => 'NEW 1', 'capacity_kg' => 0] + $payload)
            ->assertSessionHasErrors('capacity_kg');
        $this->actingAs($admin)->post(route('trucks.store'), ['plate_no' => 'NEW 2', 'status' => 'flying'] + $payload)
            ->assertSessionHasErrors('status');
    }

    public function test_a_truck_with_trips_cannot_be_deleted(): void
    {
        $truck = $this->makeTruck();
        $this->makeTrip($this->makeDriver(), $truck);

        $this->actingAs($this->makeUser('admin'))
            ->delete(route('trucks.destroy', $truck))
            ->assertSessionHasErrors('delete');
    }

    public function test_search_and_status_filter_narrow_the_driver_and_truck_lists(): void
    {
        $admin = $this->makeUser('admin');
        $this->makeTruck(['plate_no' => 'FINDME 1', 'status' => 'maintenance']);
        $this->makeTruck(['plate_no' => 'OTHER 2']);

        $this->actingAs($admin)->get('/trucks?q=findme')->assertSee('FINDME 1')->assertDontSee('OTHER 2');
        $this->actingAs($admin)->get('/trucks?status=available')->assertSee('OTHER 2')->assertDontSee('FINDME 1');
    }
}
