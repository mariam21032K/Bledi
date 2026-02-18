<?php

namespace App\Enum;

enum UserRole: string
{
    case ADMIN = 'ADMIN';
    case MUNICIPAL_AGENT = 'MUNICIPAL_AGENT';
    case CITIZEN = 'CITIZEN';

    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'Administrator',
            self::MUNICIPAL_AGENT => 'Municipal Agent',
            self::CITIZEN => 'Citizen',
        };
    }
}
