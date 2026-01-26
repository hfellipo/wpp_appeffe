<?php

namespace Tests\Unit\Enums;

use App\Enums\UserStatus;
use PHPUnit\Framework\TestCase;

class UserStatusTest extends TestCase
{
    public function test_active_status_has_correct_value(): void
    {
        $this->assertEquals('active', UserStatus::Active->value);
    }

    public function test_inactive_status_has_correct_value(): void
    {
        $this->assertEquals('inactive', UserStatus::Inactive->value);
    }

    public function test_active_status_has_correct_label(): void
    {
        $this->assertEquals('Ativo', UserStatus::Active->label());
    }

    public function test_inactive_status_has_correct_label(): void
    {
        $this->assertEquals('Inativo', UserStatus::Inactive->label());
    }

    public function test_active_status_is_active_returns_true(): void
    {
        $this->assertTrue(UserStatus::Active->isActive());
    }

    public function test_inactive_status_is_active_returns_false(): void
    {
        $this->assertFalse(UserStatus::Inactive->isActive());
    }

    public function test_values_returns_all_status_values(): void
    {
        $values = UserStatus::values();

        $this->assertIsArray($values);
        $this->assertCount(2, $values);
        $this->assertContains('active', $values);
        $this->assertContains('inactive', $values);
    }

    public function test_cases_returns_all_statuses(): void
    {
        $cases = UserStatus::cases();

        $this->assertCount(2, $cases);
        $this->assertContains(UserStatus::Active, $cases);
        $this->assertContains(UserStatus::Inactive, $cases);
    }
}
