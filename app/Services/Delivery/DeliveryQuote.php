<?php

namespace App\Services\Delivery;

use Illuminate\Support\Carbon;

final readonly class DeliveryQuote
{
    public function __construct(
        public string $provider,
        public string $reference,
        public float $fee,
        public string $currency,
        public Carbon $expiresAt,
        public int $distanceMetres,
        public array $payload,
    ) {}
}
