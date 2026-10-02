<?php

namespace App\Services\Payments;

final readonly class PayMongoCheckoutSession
{
    public function __construct(
        public string $id,
        public string $url,
    ) {}
}
