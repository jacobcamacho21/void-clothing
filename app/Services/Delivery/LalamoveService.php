<?php

namespace App\Services\Delivery;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class LalamoveService
{
    public function quote(DeliveryQuoteRequest $request): DeliveryQuote
    {
        $body = [
            'data' => [
                'serviceType' => (string) config('services.lalamove.service_type'),
                'language' => (string) config('services.lalamove.language'),
                'stops' => [
                    $this->stop($request->pickupAddress, $request->pickupLatitude, $request->pickupLongitude),
                    $this->stop($request->dropoffAddress, $request->dropoffLatitude, $request->dropoffLongitude),
                ],
                'item' => [
                    'quantity' => (string) max(1, $request->itemQuantity),
                ],
            ],
        ];

        $path = '/v3/quotations';
        $json = json_encode($body, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->valueOf();
        $signature = hash_hmac(
            'sha256',
            $timestamp."\r\nPOST\r\n{$path}\r\n\r\n{$json}",
            (string) config('services.lalamove.api_secret'),
        );

        $response = $this->client($timestamp, $signature)
            ->post($path, $body);

        if ($response->failed()) {
            throw new RuntimeException('Lalamove quotation failed: '.($response->json('message') ?? $response->reason()));
        }

        $data = $response->json('data');
        $total = data_get($data, 'priceBreakdown.total');

        if (! is_array($data) || blank($data['quotationId'] ?? null) || ! is_numeric($total)) {
            throw new RuntimeException('Lalamove returned an incomplete quotation.');
        }

        return new DeliveryQuote(
            provider: 'lalamove',
            reference: (string) $data['quotationId'],
            fee: round((float) $total, 2),
            currency: (string) data_get($data, 'priceBreakdown.currency', 'PHP'),
            expiresAt: Carbon::parse($data['expiresAt']),
            distanceMetres: (int) data_get($data, 'distance.value', 0),
            payload: $data,
        );
    }

    private function client(string $timestamp, string $signature): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.lalamove.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout(10)
            ->withHeaders([
                'Authorization' => 'hmac '.config('services.lalamove.api_key').":{$timestamp}:{$signature}",
                'Market' => config('services.lalamove.market', 'PH'),
                'Request-ID' => (string) Str::uuid(),
            ]);
    }

    /** @return array{coordinates?: array{lat: string, lng: string}, address: string} */
    private function stop(string $address, ?string $latitude, ?string $longitude): array
    {
        $stop = ['address' => $address];

        if ($latitude !== null && $longitude !== null) {
            $stop['coordinates'] = ['lat' => $latitude, 'lng' => $longitude];
        }

        return $stop;
    }
}
