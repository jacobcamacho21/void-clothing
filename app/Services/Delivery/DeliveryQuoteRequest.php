<?php

namespace App\Services\Delivery;

final readonly class DeliveryQuoteRequest
{
    public function __construct(
        public string $pickupAddress,
        public string $pickupLatitude,
        public string $pickupLongitude,
        public string $dropoffAddress,
        public ?string $dropoffLatitude,
        public ?string $dropoffLongitude,
        public string $recipientName,
        public string $recipientPhone,
        public int $itemQuantity,
    ) {}
}
