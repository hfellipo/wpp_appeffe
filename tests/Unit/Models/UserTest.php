<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_fillable_attributes(): void
    {
        $user = new User();

        $this->assertContains('name', $user->getFillable());
        $this->assertContains('email', $user->getFillable());
        $this->assertContains('password', $user->getFillable());
        $this->assertContains('role', $user->getFillable());
        $this->assertContains('status', $user->getFillable());
    }

    public function test_user_casts_role_to_enum(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertInstanceOf(UserRole::class, $user->role);
        $this->assertEquals(UserRole::Admin, $user->role);
    }

    public function test_user_casts_status_to_enum(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);

        $this->assertInstanceOf(UserStatus::class, $user->status);
        $this->assertEquals(UserStatus::Active, $user->status);
    }

    public function test_is_active_returns_true_for_active_user(): void
    {
        $user = User::factory()->active()->create();

        $this->assertTrue($user->isActive());
    }

    public function test_is_active_returns_false_for_inactive_user(): void
    {
        $user = User::factory()->inactive()->create();

        $this->assertFalse($user->isActive());
    }

    public function test_is_inactive_returns_true_for_inactive_user(): void
    {
        $user = User::factory()->inactive()->create();

        $this->assertTrue($user->isInactive());
    }

    public function test_is_inactive_returns_false_for_active_user(): void
    {
        $user = User::factory()->active()->create();

        $this->assertFalse($user->isInactive());
    }

    public function test_is_admin_returns_true_for_admin_user(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertTrue($user->isAdmin());
    }

    public function test_is_admin_returns_false_for_regular_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        $this->assertFalse($user->isAdmin());
    }

    public function test_is_user_returns_true_for_regular_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        $this->assertTrue($user->isUser());
    }

    public function test_is_user_returns_false_for_admin_user(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertFalse($user->isUser());
    }

    public function test_scope_active_returns_only_active_users(): void
    {
        User::factory()->active()->count(3)->create();
        User::factory()->inactive()->count(2)->create();

        $activeUsers = User::active()->get();

        $this->assertCount(3, $activeUsers);
        $activeUsers->each(function ($user) {
            $this->assertTrue($user->isActive());
        });
    }

    public function test_scope_admins_returns_only_admin_users(): void
    {
        User::factory()->admin()->count(2)->create();
        User::factory()->create(['role' => UserRole::User]);

        $admins = User::admins()->get();

        $this->assertCount(2, $admins);
        $admins->each(function ($user) {
            $this->assertTrue($user->isAdmin());
        });
    }

    public function test_factory_creates_active_user_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertEquals(UserStatus::Active, $user->status);
    }

    public function test_factory_creates_regular_user_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertEquals(UserRole::User, $user->role);
    }

    public function test_factory_admin_state_creates_admin_user(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertEquals(UserRole::Admin, $user->role);
    }

    public function test_factory_inactive_state_creates_inactive_user(): void
    {
        $user = User::factory()->inactive()->create();

        $this->assertEquals(UserStatus::Inactive, $user->status);
    }

    public function test_combined_scopes_work_correctly(): void
    {
        User::factory()->admin()->active()->create();
        User::factory()->admin()->inactive()->create();
        User::factory()->active()->create(['role' => UserRole::User]);

        $activeAdmins = User::admins()->active()->get();

        $this->assertCount(1, $activeAdmins);
        $this->assertTrue($activeAdmins->first()->isAdmin());
        $this->assertTrue($activeAdmins->first()->isActive());
    }
}
