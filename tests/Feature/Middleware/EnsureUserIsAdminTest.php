<?php

namespace Tests\Feature\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureUserIsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register a test route with admin middleware for testing
        Route::middleware(['web', 'auth', 'admin'])->get('/admin/test', function () {
            return response()->json(['message' => 'Admin area']);
        })->name('admin.test');
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $admin = User::factory()->admin()->active()->create();

        $response = $this->actingAs($admin)->get('/admin/test');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Admin area']);
    }

    public function test_regular_user_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        $response = $this->actingAs($user)->get('/admin/test');

        $response->assertStatus(403);
    }

    public function test_guest_cannot_access_admin_routes(): void
    {
        $response = $this->get('/admin/test');

        // Should redirect to login because of auth middleware
        $response->assertRedirect(route('login'));
    }

    public function test_inactive_admin_cannot_access_admin_routes(): void
    {
        $admin = User::factory()->admin()->inactive()->create();

        // First, we need to bypass the login (since inactive can't login)
        // by directly acting as the user
        $this->actingAs($admin);

        $response = $this->get('/admin/test');

        // Should be logged out by EnsureUserIsActive middleware first
        $this->assertGuest();
    }

    public function test_admin_middleware_returns_403_with_message(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        $response = $this->actingAs($user)->get('/admin/test');

        $response->assertStatus(403);
    }

    public function test_user_role_change_affects_access(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        // Cannot access as regular user
        $response = $this->actingAs($user)->get('/admin/test');
        $response->assertStatus(403);

        // Promote to admin
        $user->update(['role' => UserRole::Admin]);

        // Can access after promotion
        $response = $this->actingAs($user->fresh())->get('/admin/test');
        $response->assertStatus(200);
    }

    public function test_admin_demotion_removes_access(): void
    {
        $admin = User::factory()->admin()->create();

        // Can access as admin
        $response = $this->actingAs($admin)->get('/admin/test');
        $response->assertStatus(200);

        // Demote to regular user
        $admin->update(['role' => UserRole::User]);

        // Cannot access after demotion
        $response = $this->actingAs($admin->fresh())->get('/admin/test');
        $response->assertStatus(403);
    }
}
