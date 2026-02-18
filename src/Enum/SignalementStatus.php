<?php

namespace App\Enum;

enum SignalementStatus: string
{
    case NEW = 'NEW';
    case IN_PROGRESS = 'IN_PROGRESS';
    case RESOLVED = 'RESOLVED';
    case REJECTED = 'REJECTED';

    public function label(): string
    {
        return match($this) {
            self::NEW => 'New',
            self::IN_PROGRESS => 'In Progress',
            self::RESOLVED => 'Resolved',
            self::REJECTED => 'Rejected',
        };
    }
}
