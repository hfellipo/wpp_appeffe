<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountUsersControllerTest extends TestCase
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

    public function test_admin_can_update_child_user_name_email_role_and_status(): void
    {
        $admin = $this->makeRootUser(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $accountId = $admin->accountId();

        $child = $this->makeRootUser(['role' => UserRole::User, 'status' => UserStatus::Active]);
        $child->account_id = $accountId;
        $child->save();

        $resp = $this->actingAs($admin)->put(route('settings.users.update', $child), [
            'name' => 'Novo Nome',
            'email' => 'novo.email@example.com',
            'role' => UserRole::User->value,
            'status' => UserStatus::Inactive->value,
        ]);

        $resp->assertRedirect();

        $child->refresh();
        $this->assertSame('Novo Nome', $child->name);
        $this->assertSame('novo.email@example.com', $child->email);
        $this->assertSame(UserRole::User, $child->role);
        $this->assertSame(UserStatus::Inactive, $child->status);
    }

    public function test_admin_cannot_update_user_from_other_account(): void
    {
        $admin = $this->makeRootUser(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $otherAccountOwner = $this->makeRootUser(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $otherChild = $this->makeRootUser(['role' => UserRole::User, 'status' => UserStatus::Active]);
        $otherChild->account_id = $otherAccountOwner->accountId();
        $otherChild->save();

        $resp = $this->actingAs($admin)->put(route('settings.users.update', $otherChild), [
            'name' => 'X',
            'email' => 'x@example.com',
            'role' => UserRole::User->value,
            'status' => UserStatus::Active->value,
        ]);

        $resp->assertStatus(404);
    }

    public function test_users_index_includes_master_user_but_without_actions(): void
    {
        $admin = $this->makeRootUser(['role' => UserRole::Admin, 'status' => UserStatus::Active, 'name' => 'Master Admin']);

        $resp = $this->actingAs($admin)->get(route('settings.users.index'));
        $resp->assertOk();

        $resp->assertSee('Master Admin');
        $resp->assertSee('Conta principal');
    }
}

