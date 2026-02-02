<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function makeRootUser(): User
    {
        /** @var User $user */
        $user = User::factory()->create();
        if ($user->account_id === null) {
            $user->account_id = $user->id;
            $user->save();
        }
        return $user;
    }

    public function test_guest_is_redirected_from_dashboard_and_settings(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/settings')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_dashboard_and_settings(): void
    {
        $user = $this->makeRootUser();

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/settings')->assertOk();
    }
}

