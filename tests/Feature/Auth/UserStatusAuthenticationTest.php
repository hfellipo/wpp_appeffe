<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatusAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login(): void
    {
        $user = User::factory()->active()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_inactive_user_sees_appropriate_error_message(): void
    {
        $user = User::factory()->inactive()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Sua conta está inativa. Entre em contato com o administrador.',
        ]);
    }

    public function test_active_admin_can_login(): void
    {
        $admin = User::factory()->admin()->active()->create();

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->isAdmin());
    }

    public function test_inactive_admin_cannot_login(): void
    {
        $admin = User::factory()->admin()->inactive()->create();

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_wrong_password_shows_failed_message_not_inactive_message(): void
    {
        $user = User::factory()->active()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        // Should not contain the inactive message
        $this->assertStringNotContainsString(
            'inativa',
            session('errors')->get('email')[0] ?? ''
        );
    }

    public function test_nonexistent_user_shows_failed_message(): void
    {
        $response = $this->post('/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->active()->create();

        $this->actingAs($user);
        $this->assertAuthenticated();

        $response = $this->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_remember_me_works_for_active_user(): void
    {
        $user = User::factory()->active()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);

        $this->assertAuthenticated();
        $response->assertCookie(auth()->guard()->getRecallerName());
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->active()->create();

        // Attempt 6 failed logins (limit is 5)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response->assertSessionHasErrors('email');

        // Verify the error message indicates throttling (works for any locale)
        $errorMessage = session('errors')->get('email')[0] ?? '';
        $isThrottled = str_contains($errorMessage, 'Too many') 
            || str_contains($errorMessage, 'seconds')
            || str_contains($errorMessage, 'segundos')
            || str_contains($errorMessage, 'attempts');

        $this->assertTrue($isThrottled, "Expected throttle message, got: {$errorMessage}");
    }
}
