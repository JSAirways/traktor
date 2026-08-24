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

    public function test_logged_in_user_can_access_admin_with_valid_admin_pin(): void
    {
        $user = $this->makeApprovedParent();
        $user->setAdminPin('1234');

        $response = $this->actingAs($user)->postJson(route('admin.verify-password'), [
            'pin' => '1234',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.redirect', route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_logged_in_user_rejects_invalid_admin_pin(): void
    {
        $user = $this->makeApprovedParent();
        $user->setAdminPin('1234');

        $response = $this->actingAs($user)->postJson(route('admin.verify-password'), [
            'pin' => '9999',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.pin.0', __('auth.invalid_pin'));
        $this->assertAuthenticatedAs($user);
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

    public function test_registered_device_can_access_admin_with_valid_admin_pin_without_auth_session(): void
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

    public function test_admin_pin_is_rejected_when_device_is_not_registered_and_guest(): void
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
            ->assertJsonPath('errors.pin.0', __('messages.device_not_registered'));
        $this->assertGuest();
    }

    public function test_logged_in_admin_pin_works_without_registered_device(): void
    {
        $user = $this->makeApprovedParent();
        $user->setAdminPin('1234');

        $this->mock(DeviceRegistrationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getDeviceFromCookie')->andReturn(null);
        });

        $response = $this->actingAs($user)->postJson(route('admin.verify-password'), [
            'pin' => '1234',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.redirect', route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_pin_is_rejected_when_not_configured(): void
    {
        $user = $this->makeApprovedParent();

        $response = $this->actingAs($user)->postJson(route('admin.verify-password'), [
            'pin' => '1234',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.pin.0', __('auth.invalid_pin'));
    }

    public function test_admin_pin_login_switches_to_device_parent_when_another_user_is_authenticated(): void
    {
        $parent = $this->makeApprovedParent();
        $parent->setAdminPin('1234');
        $this->withoutMiddleware(EncryptCookies::class);
        $this->mockRegisteredDevice($this->makeRegisteredDevice($parent));

        $otherUsername = 'other-'.Str::lower(Str::random(6));
        $other = User::forceCreate([
            'username' => $otherUsername,
            'slug' => $otherUsername,
            'email' => Str::lower(Str::random(8)).'@example.test',
            'password' => Hash::make('secret-pass'),
            'role' => 'user',
            'account_status' => 'approved',
            'parent_id' => null,
            'is_viewable' => true,
            'appears_in_profile_selection' => true,
        ])->fresh();

        $response = $this->actingAs($other)->postJson(route('admin.verify-password'), [
            'pin' => '1234',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.redirect', route('admin.dashboard'));
        $this->assertAuthenticatedAs($parent);
    }

    public function test_password_fallback_still_works_after_failed_admin_pin_attempts(): void
    {
        $user = $this->makeApprovedParent();
        $user->setAdminPin('1234');
        $this->withoutMiddleware(EncryptCookies::class);
        $this->mockRegisteredDevice($this->makeRegisteredDevice($user));

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

    public function test_gallery_settings_modal_shows_admin_pin_when_device_parent_has_pin(): void
    {
        [$child, $device] = $this->makeViewableChildOnRegisteredDevice();
        $this->withoutMiddleware(EncryptCookies::class);
        $this->mockRegisteredDevice($device);

        $this->get(route('gallery.show', $child->slug))
            ->assertOk()
            ->assertSee('id="adminPin"', false)
            ->assertSee('adminAccessPinPanel', false);
    }

    public function test_gallery_settings_modal_shows_admin_pin_even_when_authenticated_as_child(): void
    {
        [$child, $device] = $this->makeViewableChildOnRegisteredDevice();
        $this->withoutMiddleware(EncryptCookies::class);
        $this->mockRegisteredDevice($device);

        $this->actingAs($child)
            ->get(route('gallery.show', $child->slug))
            ->assertOk()
            ->assertSee('id="adminPin"', false);
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

    /**
     * @return array{0: User, 1: DeviceRegistration}
     */
    private function makeViewableChildOnRegisteredDevice(): array
    {
        $parent = $this->makeApprovedParent();
        $parent->setAdminPin('1234');

        $childUsername = 'child-'.Str::lower(Str::random(6));
        $child = User::forceCreate([
            'username' => $childUsername,
            'slug' => $childUsername,
            'email' => Str::lower(Str::random(8)).'@example.test',
            'password' => Hash::make('secret-pass'),
            'role' => 'user',
            'account_status' => 'approved',
            'parent_id' => $parent->id,
            'is_viewable' => true,
            'appears_in_profile_selection' => true,
        ])->fresh();

        $device = $this->makeRegisteredDevice($parent);
        $device->update([
            'current_viewing_slug' => $child->slug,
            'viewing_validated_at' => now(),
            'viewing_expires_at' => now()->addDay(),
        ]);
        $device->load('parent');

        return [$child, $device];
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
            $mock->shouldReceive('getDeviceFromCookie')->zeroOrMoreTimes()->andReturn($device);
            $mock->shouldReceive('resolveAdminPinUser')->zeroOrMoreTimes()->andReturn($device->parent);
            $mock->shouldReceive('isTokenExpired')->zeroOrMoreTimes()->andReturnFalse();
            $mock->shouldReceive('refreshDeviceToken')->never();
        });
    }
}
