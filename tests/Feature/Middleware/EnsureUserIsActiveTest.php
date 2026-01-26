<?php

namespace Tests\Feature\Middleware;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureUserIsActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_access_protected_routes(): void
    {
        $user = User::factory()->active()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_inactive_user_is_logged_out_when_accessing_protected_routes(): void
    {
        $user = User::factory()->active()->create();

        // Login the user
        $this->actingAs($user);
        $this->assertAuthenticated();

        // Deactivate the user (simulating admin action while user is logged in)
        $user->update(['status' => UserStatus::Inactive]);

        // Try to access protected route
        $response = $this->get('/dashboard');

        // User should be logged out and redirected to login
        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_inactive_user_sees_deactivation_message(): void
    {
        $user = User::factory()->active()->create();

        $this->actingAs($user);
        $user->update(['status' => UserStatus::Inactive]);

        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'Sua conta foi desativada. Entre em contato com o administrador.',
        ]);
    }

    public function test_session_is_invalidated_when_user_is_deactivated(): void
    {
        $user = User::factory()->active()->create();

        $this->actingAs($user);

        // Store something in session
        session(['test_key' => 'test_value']);
        $this->assertEquals('test_value', session('test_key'));

        // Deactivate user
        $user->update(['status' => UserStatus::Inactive]);

        // Access route triggers middleware
        $this->get('/dashboard');

        // Session should be invalidated (new session won't have old data)
        $this->assertNull(session('test_key'));
    }

    public function test_guest_can_access_public_routes(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_guest_can_access_login_page(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_guest_can_access_register_page(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_reactivated_user_can_login_again(): void
    {
        $user = User::factory()->inactive()->create();

        // Cannot login while inactive
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $this->assertGuest();

        // Reactivate user
        $user->update(['status' => UserStatus::Active]);

        // Can login after reactivation
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $this->assertAuthenticated();
    }

    public function test_profile_route_is_protected(): void
    {
        $user = User::factory()->active()->create();

        $this->actingAs($user);
        $user->update(['status' => UserStatus::Inactive]);

        $response = $this->get('/profile');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }
}
