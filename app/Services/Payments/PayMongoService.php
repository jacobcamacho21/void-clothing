<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Enums\OrderStatus;
use App\Services\OrderService;
use RuntimeException;

class PayMongoService
{
    public function __construct(private readonly OrderService $orders) {}

    public function createCheckoutSession(Order $order): PayMongoCheckoutSession
    {
        $lineItems = $order->items->map(fn ($item): array => [
            'currency' => 'PHP',
            'amount' => (int) round((float) $item->unit_price * 100),
            'name' => $item->product_name.($item->product_size ? ' - '.$item->product_size : ''),
            'quantity' => (int) $item->quantity,
        ])->values()->all();

        if ((float) $order->shipping_fee > 0) {
            $lineItems[] = [
                'currency' => 'PHP',
                'amount' => (int) round((float) $order->shipping_fee * 100),
                'name' => 'Delivery fee',
                'quantity' => 1,
            ];
        }

        $response = $this->client()->post('/v2/checkout_sessions', [
            'data' => [
                'attributes' => [
                    'line_items' => $lineItems,
                    'payment_method_types' => config('services.paymongo.payment_method_types'),
                    'success_url' => route('shop.payment.success', ['order' => $order->order_ref]),
                    'cancel_url' => route('shop.payment.cancel', ['order' => $order->order_ref]),
                    'description' => 'VOID order '.$order->order_ref,
                    'reference_number' => $order->order_ref,
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('PayMongo checkout could not be created.');
        }

        $data = $response->json('data');
        $id = data_get($data, 'id');
        $url = data_get($data, 'attributes.checkout_url');

        if (! is_string($id) || ! is_string($url) || $id === '' || $url === '') {
            throw new RuntimeException('PayMongo returned an incomplete checkout session.');
        }

        return new PayMongoCheckoutSession($id, $url);
    }

    public function confirmCheckoutSession(Payment $payment): bool
    {
        if ($payment->provider_checkout_id === null) {
            return false;
        }

        $response = $this->client()->get('/v1/checkout_sessions/'.$payment->provider_checkout_id);

        if ($response->failed()) {
            return false;
        }

        $session = $response->json('data');
        $paidPayment = collect(data_get($session, 'attributes.payments', []))
            ->first(fn (array $candidate): bool => data_get($candidate, 'attributes.status') === 'paid');

        if (data_get($session, 'attributes.status') !== 'paid' && $paidPayment === null) {
            return false;
        }

        DB::transaction(function () use ($payment, $session, $paidPayment): void {
            $payment->refresh()->load('order');

            if ($payment->status !== 'paid') {
                $payment->update([
                    'status' => 'paid',
                    'provider_payment_id' => $paidPayment['id'] ?? null,
                    'amount_tendered' => $payment->amount_due,
                    'paid_at' => now(),
                    'provider_payload' => $session,
                ]);
            }

            if ($payment->order->status === OrderStatus::Pending) {
                $this->orders->transition(
                    $payment->order,
                    OrderStatus::Approved,
                    null,
                    'PayMongo payment confirmed on return.',
                );
            }
        });

        return true;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.paymongo.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withBasicAuth((string) config('services.paymongo.secret_key'), '')
            ->timeout(10);
    }
}
