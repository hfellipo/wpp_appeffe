<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AdminClearCacheEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function makeRootUser(array $overrides = []): User
    {
        /** @var User $user */
        $user = User::factory()->create($overrides);

        if ($user->account_id === null) {
            $user->account_id = $user->id;
            $user->save();
        }

        return $user;
    }

    public function test_guest_cannot_clear_cache(): void
    {
        $response = $this->post('/admin/clear-cache');
        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_clear_cache(): void
    {
        $user = $this->makeRootUser([
            'role' => UserRole::User,
            'status' => UserStatus::Active,
        ]);

        $response = $this->actingAs($user)->post('/admin/clear-cache');
        $response->assertStatus(403);
    }

    public function test_admin_can_clear_cache_and_get_success_json(): void
    {
        Artisan::shouldReceive('call')->andReturn(0);

        $admin = $this->makeRootUser([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $response = $this->actingAs($admin)->postJson('/admin/clear-cache');
        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
    }
}

