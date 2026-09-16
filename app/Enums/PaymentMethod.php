<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'Cash';
    case Digital = 'Digital Payment';

    public function label(): string
    {
        return $this->value;
    }

    /** Cash is the only method that involves tendering and change. */
    public function requiresTender(): bool
    {
        return $this === self::Cash;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
