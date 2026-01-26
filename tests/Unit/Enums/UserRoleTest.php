<?php

namespace Tests\Unit\Enums;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function test_admin_role_has_correct_value(): void
    {
        $this->assertEquals('admin', UserRole::Admin->value);
    }

    public function test_user_role_has_correct_value(): void
    {
        $this->assertEquals('user', UserRole::User->value);
    }

    public function test_admin_role_has_correct_label(): void
    {
        $this->assertEquals('Administrador', UserRole::Admin->label());
    }

    public function test_user_role_has_correct_label(): void
    {
        $this->assertEquals('Usuário', UserRole::User->label());
    }

    public function test_values_returns_all_role_values(): void
    {
        $values = UserRole::values();

        $this->assertIsArray($values);
        $this->assertCount(2, $values);
        $this->assertContains('admin', $values);
        $this->assertContains('user', $values);
    }

    public function test_cases_returns_all_roles(): void
    {
        $cases = UserRole::cases();

        $this->assertCount(2, $cases);
        $this->assertContains(UserRole::Admin, $cases);
        $this->assertContains(UserRole::User, $cases);
    }
}
