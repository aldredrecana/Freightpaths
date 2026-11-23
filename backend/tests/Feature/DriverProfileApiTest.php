<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** The Profile tab in the phone app: view and edit info, change password. */
class DriverProfileApiTest extends TestCase
{
    public function test_me_includes_the_profile_details(): void
    {
        $driver = $this->makeDriver(['contact_no' => '09170000001', 'address' => 'Bay, Laguna']);
        $token = $driver->user->createToken('phone')->plainTextToken;

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.contact_no', '09170000001')
            ->assertJsonPath('user.address', 'Bay, Laguna')
            ->assertJsonPath('user.license_no', $driver->license_no);
    }

    public function test_a_driver_can_edit_contact_number_and_address_only(): void
    {
        $driver = $this->makeDriver();
        $token = $driver->user->createToken('phone')->plainTextToken;
        $oldName = $driver->user->name;
        $oldLicense = $driver->license_no;

        $this->withToken($token)->postJson('/api/me', [
            'contact_no' => '09998887777',
            'address' => 'Calauan, Laguna',
            'name' => 'Hacked Name',
            'license_no' => 'FAKE-1',
        ])->assertOk()->assertJsonPath('user.contact_no', '09998887777');

        $driver->refresh();
        $this->assertSame('09998887777', $driver->contact_no);
        $this->assertSame('Calauan, Laguna', $driver->address);
        $this->assertSame($oldName, $driver->user->name);
        $this->assertSame($oldLicense, $driver->license_no);

        $this->withToken($token)->postJson('/api/me', ['contact_no' => '', 'address' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_no', 'address']);
    }

    public function test_a_driver_can_change_password_and_other_phones_are_signed_out(): void
    {
        $driver = $this->makeDriver();
        $user = $driver->user;
        $thisPhone = $user->createToken('this phone')->plainTextToken;
        $user->createToken('old phone');

        $this->withToken($thisPhone)->postJson('/api/me/password', [
            'current_password' => 'wrong',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->withToken($thisPhone)->postJson('/api/me/password', [
            'current_password' => 'password',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPass123', $user->fresh()->password));
        $this->assertSame(1, $user->tokens()->count()); // only this phone stays logged in
    }
}