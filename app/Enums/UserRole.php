<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Moderator = 'moderator';
    case User = 'user';

    public static function moderationRoles(): array
    {
        return [
            self::Admin->value,
            self::Moderator->value,
        ];
    }
}
