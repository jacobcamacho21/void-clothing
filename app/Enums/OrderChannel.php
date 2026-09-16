<?php

namespace App\Enums;

enum OrderChannel: string
{
    case Online = 'online';
    case Pos = 'pos';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Pos => 'Counter',
        };
    }
}
