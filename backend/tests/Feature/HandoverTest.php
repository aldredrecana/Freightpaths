<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Fixes found in the QA review before handing the system to a company. */
class HandoverTest extends TestCase
{
    public function test_forgot_password_pages_are_removed(): void
    {
        $this->get('/forgot-password')->assertNotFound();
        $this->get('/reset-password/some-token')->assertNotFound();
    }

    public function test_the_delete_button_only_shows_on_pending_trips(): void
    {
        $admin = $this->makeUser('admin');
        $driver = $this->makeDriver();
        $truck = $this->makeTruck();

        $pending = $this->makeTrip($driver, $truck, ['status' => 'pending']);
        $this->actingAs($admin)
            ->get(route('schedules.show', $pending))
            ->assertSee('Delete this trip?');

        $accepted = $this->makeTrip($driver, $truck, [
            'status' => 'accepted',
            'departure_at' => now()->addDays(5),
            'expected_arrival_at' => now()->addDays(5)->addHours(4),
        ]);
        $this->actingAs($admin)
            ->get(route('schedules.show', $accepted))
            ->assertDontSee('Delete this trip?');
    }

    public function test_admin_can_log_a_driver_out_of_the_phone_app(): void
    {
        $driver = $this->makeDriver();
        $driver->user->createToken('driver-phone');
        $this->assertSame(1, $driver->user->tokens()->count());

        $this->actingAs($this->makeUser('admin'))
            ->post(route('drivers.logout-phone', $driver))
            ->assertRedirect();

        $this->assertSame(0, $driver->user->tokens()->count());
    }

    public function test_dispatchers_cannot_log_drivers_out(): void
    {
        $driver = $this->makeDriver();
        $driver->user->createToken('driver-phone');

        $this->actingAs($this->makeUser('dispatcher'))
            ->post(route('drivers.logout-phone', $driver))
            ->assertForbidden();

        $this->assertSame(1, $driver->user->tokens()->count());
    }

    public function test_changing_the_password_logs_the_phone_out(): void
    {
        $driver = $this->makeDriver();
        $driver->user->createToken('driver-phone');

        $this->actingAs($this->makeUser('admin'))
            ->patch(route('drivers.update', $driver), [
                'name' => $driver->user->name,
                'email' => $driver->user->email,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
                'license_no' => $driver->license_no,
                'license_expiry' => $driver->license_expiry->toDateString(),
                'contact_no' => $driver->contact_no,
                'address' => $driver->address,
                'status' => $driver->status,
            ])
            ->assertRedirect(route('drivers.index'));

        $this->assertSame(0, $driver->user->tokens()->count());
    }

    public function test_phone_logins_expire(): void
    {
        $this->assertSame(43200, config('sanctum.expiration'));
    }
}