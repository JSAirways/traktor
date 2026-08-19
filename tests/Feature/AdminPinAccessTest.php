<?php

namespace Tests\Feature;

use App\Http\Middleware\EncryptCookies;
use App\Models\DeviceRegistration;
use App\Models\User;
use App\Services\DeviceRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminPinAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_can_enable_admin_pin(): void
    {
        $user = $this->makeApprovedParent();

        $response = $this->actingAs($user)->put(route('admin.profile.update'), [
            'email' => $user->email,
            'username' => $user->username,
            'password' => '',
            'use_admin_pin' => 'on',
            'admin_pin' => '1234',
            'use_pin' => 'on',
            'pin' => '4321',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertTrue($user->fresh()->hasAdminPin());
        $this->assertSame('1234', $user->fresh()->getAdminPin());
    }

    public function test_profile_update_can_disable_admin_pin(): void
    {
        $user = $this->makeApprovedParent();
        $user->setAdminPin('1234');

        $response = $this->actingAs($user)->put(route('admin.profile.update'), [
            'email' => $user->email,
            'username' => $user->username,
            'password' => '',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertFalse($user->fresh()->hasAdminPin());
    }

    public function test_profile_update_validates_admin_pin_as_four_digits(): void
    {
        $user = $this->makeApprovedParent();

        $response = $this->actingAs($user)->from(route('admin.profile.edit'))->put(route('admin.profile.update'), [
            'email' => $user->email,
            'username' => $user->username,
            'password' => '',
            'use_admin_pin' => 'on',
            'admin_pin' => '12',
        ]);

        $response->assertRedirect(route('admin.profile.edit'));
        $response->assertSessionHasErrors('admin_pin');
    }

    public function test_registered_device_can_access_admin_with_valid_admin_pin(): void
    {
        $user = $this->makeApprovedParent();
        $user->setAdminPin('1234');
        $this->withoutMiddleware(EncryptCookies::class);
        $this->mockRegisteredDevice($this->makeRegisteredDevice($user));

        $response = $this->postJson(route('admin.verify-password'), [
            'pin' => '1234',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.redirect', route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registered_device_rejects_invalid_admin_pin(): void
    {
        $user = $this->makeApprovedParent();
        $user->setAdminPin('1234');
        $this->withoutMiddleware(EncryptCookies::class);
        $this->mockRegisteredDevice($this->makeRegisteredDevice($user));

        $response = $this->postJson(route('admin.verify-password'), [
            'pin' => '9999',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.pin.0', __('auth.invalid_pin'));
        $this->assertGuest();
    }

    public function test_password_fallback_still_works_when_admin_pin_is_enabled(): void
    {
        $user = $this->makeApprovedParent();
        $user->setAdminPin('1234');
        $this->withoutMiddleware(EncryptCookies::class);
        $this->mockRegisteredDevice($this->makeRegisteredDevice($user));

        $response = $this->postJson(route('admin.verify-password'), [
            'password' => 'secret-pass',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.redirect', route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_pin_is_rejected_when_not_configured(): void
    {
        $user = $this->makeApprovedParent();
        $this->withoutMiddleware(EncryptCookies::class);
        $this->mockRegisteredDevice($this->makeRegisteredDevice($user));

        $response = $this->postJson(route('admin.verify-password'), [
            'pin' => '1234',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.pin.0', __('auth.invalid_pin'));
    }

    public function test_admin_pin_is_rejected_when_device_is_not_registered(): void
    {
        $user = $this->makeApprovedParent();
        $user->setAdminPin('1234');

        $this->mock(DeviceRegistrationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getDeviceFromCookie')->andReturn(null);
        });

        $response = $this->postJson(route('admin.verify-password'), [
            'pin' => '1234',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.password.0', __('messages.device_not_registered'));
        $this->assertGuest();
    }

    public function test_admin_pin_is_rate_limited_after_repeated_failures(): void
    {
        $user = $this->makeApprovedParent();
        $user->setAdminPin('1234');
        $this->withoutMiddleware(EncryptCookies::class);
        $this->mockRegisteredDevice($this->makeRegisteredDevice($user));
        $this->clearAdminPinRateLimit();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('admin.verify-password'), ['pin' => '9999'])->assertStatus(422);
        }

        $response = $this->postJson(route('admin.verify-password'), ['pin' => '1234']);

        $response->assertStatus(429);
        $this->assertStringContainsString(
            __('auth.too_many_pin_attempts', ['minutes' => 15]),
            (string) $response->json('message')
        );
        $this->assertGuest();
    }

    public function test_password_fallback_works_when_admin_pin_rate_limit_is_exhausted(): void
    {
        $user = $this->makeApprovedParent();
        $user->setAdminPin('1234');
        $this->withoutMiddleware(EncryptCookies::class);
        $this->mockRegisteredDevice($this->makeRegisteredDevice($user));
        $this->clearAdminPinRateLimit();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('admin.verify-password'), ['pin' => '9999'])->assertStatus(422);
        }

        $response = $this->postJson(route('admin.verify-password'), [
            'password' => 'secret-pass',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.redirect', route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_view_pin_rate_limit_does_not_block_admin_password_fallback(): void
    {
        $user = $this->makeApprovedParent();
        $user->setAdminPin('1234');
        $this->withoutMiddleware(EncryptCookies::class);
        $this->mockRegisteredDevice($this->makeRegisteredDevice($user));

        RateLimiter::clear('view_pin_attempts_127.0.0.1');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            RateLimiter::hit('view_pin_attempts_127.0.0.1', 900);
        }

        $response = $this->postJson(route('admin.verify-password'), [
            'password' => 'secret-pass',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.redirect', route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    private function makeApprovedParent(): User
    {
        $username = 'parent-'.Str::lower(Str::random(6));

        $user = User::forceCreate([
            'username' => $username,
            'slug' => $username,
            'email' => Str::lower(Str::random(8)).'@example.test',
            'password' => Hash::make('secret-pass'),
            'role' => 'user',
            'account_status' => 'approved',
            'parent_id' => null,
            'is_viewable' => true,
            'appears_in_profile_selection' => true,
        ]);

        return $user->fresh();
    }

    private function makeRegisteredDevice(User $user): DeviceRegistration
    {
        return DeviceRegistration::create([
            'parent_user_id' => $user->id,
            'device_uid' => (string) Str::uuid(),
            'device_token' => (string) Str::uuid(),
            'device_name' => 'Test Device',
            'registered_at' => now(),
            'last_used_at' => now(),
            'is_active' => true,
            'token_expires_at' => now()->addDays(30),
        ]);
    }

    private function mockRegisteredDevice(DeviceRegistration $device): void
    {
        $device->load('parent');

        $this->mock(DeviceRegistrationService::class, function (MockInterface $mock) use ($device): void {
            $mock->shouldReceive('getDeviceFromCookie')->andReturn($device);
            $mock->shouldReceive('isTokenExpired')->andReturnFalse();
            $mock->shouldReceive('refreshDeviceToken')->never();
        });
    }

    private function clearAdminPinRateLimit(): void
    {
        RateLimiter::clear('admin_pin_attempts_127.0.0.1');
        RateLimiter::clear('admin_password_attempts_127.0.0.1');
    }
}
