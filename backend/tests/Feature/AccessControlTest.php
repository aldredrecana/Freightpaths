<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Who may open which page (authentication and role checks). */
class AccessControlTest extends TestCase
{
    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/drivers')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
    }

    public function test_home_redirects_logged_in_users_to_the_dashboard(): void
    {
        $this->actingAs($this->makeUser('admin'))->get('/')->assertRedirect('/dashboard');
    }

    public function test_drivers_cannot_open_staff_pages(): void
    {
        $driver = $this->makeUser('driver');

        foreach (['/drivers', '/drivers/create', '/trucks', '/trucks/create', '/schedules', '/transactions'] as $url) {
            $this->actingAs($driver)->get($url)->assertForbidden();
        }
    }

    public function test_dispatchers_can_view_but_not_manage_drivers_and_trucks(): void
    {
        $dispatcher = $this->makeUser('dispatcher');

        $this->actingAs($dispatcher)->get('/drivers')->assertOk();
        $this->actingAs($dispatcher)->get('/trucks')->assertOk();
        $this->actingAs($dispatcher)->get('/drivers/create')->assertForbidden();
        $this->actingAs($dispatcher)->get('/trucks/create')->assertForbidden();
        $this->actingAs($dispatcher)->get('/schedules/create')->assertOk();
    }

    public function test_admins_can_open_the_management_pages(): void
    {
        $admin = $this->makeUser('admin');

        foreach (['/drivers/create', '/trucks/create', '/schedules/create', '/transactions', '/dashboard'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_a_driver_can_only_open_their_own_trip(): void
    {
        $mine = $this->makeDriver();
        $other = $this->makeDriver();
        $trip = $this->makeTrip($mine, $this->makeTruck());

        $this->actingAs($mine->user)->get(route('schedules.show', $trip))->assertOk();
        $this->actingAs($other->user)->get(route('schedules.show', $trip))->assertForbidden();
    }

    public function test_a_driver_cannot_change_someone_elses_trip(): void
    {
        $owner = $this->makeDriver();
        $other = $this->makeDriver();
        $trip = $this->makeTrip($owner, $this->makeTruck());

        $this->actingAs($other->user)
            ->post(route('schedules.status', $trip), ['status' => 'accepted'])
            ->assertForbidden();

        $this->assertSame('pending', $trip->fresh()->status);
    }

    public function test_friendly_error_pages_are_used(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee("We can't find that page");
        $this->actingAs($this->makeUser('driver'))->get('/drivers')->assertForbidden()->assertSee("You can't open this page");
    }

    public function test_profile_page_has_no_account_deletion(): void
    {
        $user = $this->makeUser('driver');

        $this->actingAs($user)->get('/profile')->assertOk()->assertDontSee('Delete Account');
        $this->actingAs($user)->delete('/profile')->assertStatus(405);
    }
}
