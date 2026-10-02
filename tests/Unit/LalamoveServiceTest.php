<?php

namespace Tests\Unit;

use App\Services\Delivery\DeliveryQuoteRequest;
use App\Services\Delivery\LalamoveService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LalamoveServiceTest extends TestCase
{
    public function test_it_returns_a_lalamove_quote(): void
    {
        config()->set('services.lalamove', [
            'base_url' => 'https://rest.sandbox.lalamove.com',
            'api_key' => 'pk_test_example',
            'api_secret' => 'sk_test_example',
            'market' => 'PH',
            'language' => 'en_PH',
            'service_type' => 'MOTORCYCLE',
        ]);

        Http::fake([
            'https://rest.sandbox.lalamove.com/v3/quotations' => Http::response([
                'data' => [
                    'quotationId' => 'quote-123',
                    'expiresAt' => '2026-10-02T12:05:00.000Z',
                    'priceBreakdown' => [
                        'total' => '145.50',
                        'currency' => 'PHP',
                    ],
                    'distance' => [
                        'value' => '8200',
                    ],
                ],
            ], 201),
        ]);

        $quote = app(LalamoveService::class)->quote(new DeliveryQuoteRequest(
            pickupAddress: 'Tangulan Street, Kawit, Cavite',
            pickupLatitude: '14.4450',
            pickupLongitude: '120.9010',
            dropoffAddress: 'Makati City, Metro Manila',
            dropoffLatitude: '14.5547',
            dropoffLongitude: '121.0244',
            recipientName: 'Customer',
            recipientPhone: '+639176123456',
            itemQuantity: 2,
        ));

        $this->assertSame('lalamove', $quote->provider);
        $this->assertSame('quote-123', $quote->reference);
        $this->assertSame(145.50, $quote->fee);
        $this->assertSame('PHP', $quote->currency);
        $this->assertSame(8200, $quote->distanceMetres);

        Http::assertSent(fn ($request) => $request->url() === 'https://rest.sandbox.lalamove.com/v3/quotations'
            && str_starts_with(implode('', (array) $request->header('Authorization')), 'hmac pk_test_example:')
            && $request->hasHeader('Market', 'PH')
            && $request['data']['stops'][1]['coordinates']['lat'] === '14.5547'
        );
    }
}
