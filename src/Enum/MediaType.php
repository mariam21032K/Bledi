<?php

namespace App\Enum;

enum MediaType: string
{
    case VIDEO = 'VIDEO';
    case IMAGE = 'IMAGE';

    public function label(): string
    {
        return match($this) {
            self::VIDEO => 'Video',
            self::IMAGE => 'Image',
        };
    }
}
