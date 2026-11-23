<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use Tests\TestCase;

/** Login (Admin/Dispatcher tabs, staff only), registration, logout, and the timezone setting. */
class AuthenticationTest extends TestCase
{
    public function test_admins_log_in_on_the_admin_tab(): void
    {
        $admin = $this->makeUser('admin');

        $this->post('/login', ['email' => $admin->email, 'password' => 'password', 'login_as' => 'admin'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_dispatchers_log_in_on_the_dispatcher_tab(): void
    {
        $dispatcher = $this->makeUser('dispatcher');

        $this->post('/login', ['email' => $dispatcher->email, 'password' => 'password', 'login_as' => 'dispatcher'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($dispatcher);
    }

    public function test_drivers_are_sent_to_the_app(): void
    {
        $driver = $this->makeUser('driver');

        foreach (['admin', 'dispatcher'] as $tab) {
            $this->post('/login', ['email' => $driver->email, 'password' => 'password', 'login_as' => $tab])
                ->assertSessionHasErrors(['email' => 'Drivers use the FreightPath Driver app. Please sign in on your phone.']);
            $this->assertGuest();
        }
    }

    public function test_the_wrong_tab_is_rejected(): void
    {
        $admin = $this->makeUser('admin');
        $dispatcher = $this->makeUser('dispatcher');

        $this->post('/login', ['email' => $admin->email, 'password' => 'password', 'login_as' => 'dispatcher'])
            ->assertSessionHasErrors(['email' => 'This is an admin account. Choose the Admin tab.']);
        $this->assertGuest();

        $this->post('/login', ['email' => $dispatcher->email, 'password' => 'password', 'login_as' => 'admin'])
            ->assertSessionHasErrors(['email' => 'This is not an admin account. Choose the Dispatcher tab.']);
        $this->assertGuest();
    }

    public function test_a_wrong_password_shows_a_friendly_message(): void
    {
        $admin = $this->makeUser('admin');

        $this->post('/login', ['email' => $admin->email, 'password' => 'nope', 'login_as' => 'dispatcher'])
            ->assertSessionHasErrors(['email' => 'Wrong email or password. Please try again, or call your dispatcher.']);
        $this->assertGuest();
    }

    public function test_registration_creates_a_least_privilege_driver_account(): void
    {
        $this->post('/register', [
            'name' => 'New Person',
            'email' => 'new.person@example.test',
            'password' => 'a-strong-password-123',
            'password_confirmation' => 'a-strong-password-123',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'new.person@example.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole('driver'));
        $this->assertNull($user->driver);
        $this->assertSame(0, Driver::count());

        // They cannot see any staff page and the driver home explains there is no trip.
        $this->get('/drivers')->assertForbidden();
        $this->get('/dashboard')->assertOk()->assertSee('No active trip right now.');
    }

    public function test_users_can_log_out(): void
    {
        $this->actingAs($this->makeUser('admin'))->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_the_app_uses_the_philippine_timezone_from_the_environment(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
        $this->assertSame('Asia/Manila', now()->timezoneName);
    }
}
