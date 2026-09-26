<?php

namespace Tests\Feature;

use App\Models\DeviceRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_registration_authenticates_with_email_and_password(): void
    {
        $user = $this->makeApprovedParent('parent@example.test', 'secret-pass');
        $deviceUid = (string) Str::uuid();

        $response = $this->post(route('device.register'), [
            'email' => 'parent@example.test',
            'password' => 'secret-pass',
            'device_name' => 'Living Room TV',
            'device_uid' => $deviceUid,
            'user_agent' => 'PHPUnit',
            'screen_resolution' => '1920x1080',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertGuest();

        $this->assertDatabaseHas('device_registrations', [
            'parent_user_id' => $user->id,
            'device_uid' => $deviceUid,
            'device_name' => 'Living Room TV',
            'is_active' => true,
        ]);
    }

    public function test_device_registration_rejects_invalid_email_credentials(): void
    {
        $this->makeApprovedParent('parent@example.test', 'secret-pass');

        $response = $this->from(route('device.register.show'))->post(route('device.register'), [
            'email' => 'parent@example.test',
            'password' => 'wrong-pass',
            'device_name' => 'Living Room TV',
            'device_uid' => (string) Str::uuid(),
        ]);

        $response
            ->assertRedirect(route('device.register.show'))
            ->assertSessionHasErrors(['email', 'password']);
        $this->assertSame(0, DeviceRegistration::count());
    }

    public function test_password_only_device_login_authenticates_with_email(): void
    {
        $user = $this->makeApprovedParent('parent@example.test', 'secret-pass');
        $deviceUid = (string) Str::uuid();

        DeviceRegistration::create([
            'parent_user_id' => $user->id,
            'device_uid' => $deviceUid,
            'device_token' => (string) Str::uuid(),
            'device_name' => 'Kitchen Tablet',
            'registered_at' => now(),
            'last_used_at' => now(),
            'is_active' => true,
            'token_expires_at' => now()->addDays(30),
        ]);

        $response = $this->postJson(route('device.register'), [
            'email' => 'parent@example.test',
            'password' => 'secret-pass',
            'device_name' => 'password-login',
            'device_uid' => $deviceUid,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertGuest();
    }

    private function makeApprovedParent(string $email, string $password): User
    {
        $profileName = 'parent-'.Str::lower(Str::random(6));

        return User::forceCreate([
            'profile_name' => $profileName,
            'slug' => $profileName,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'user',
            'account_status' => 'approved',
            'parent_id' => null,
            'is_viewable' => true,
            'appears_in_profile_selection' => true,
        ]);
    }
}
