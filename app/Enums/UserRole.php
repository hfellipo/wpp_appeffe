<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::User => 'Usuário',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
