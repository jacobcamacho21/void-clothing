<?php

namespace App\Http\Controllers\Payments;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PayMongoWebhookController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        abort_unless($this->signatureIsValid($payload, (string) $request->header('Paymongo-Signature')), 401);

        $event = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        $attributes = data_get($event, 'data.attributes', []);
        $type = $attributes['type'] ?? null;
        $resource = $attributes['data'] ?? [];

        if (! in_array($type, ['checkout_session.payment.paid', 'checkout_session.payment.failed'], true)) {
            return response()->json(['received' => true]);
        }

        $payment = Payment::query()
            ->with('order')
            ->where('provider_checkout_id', $resource['id'] ?? null)
            ->first();

        if ($payment === null) {
            return response()->json(['received' => true]);
        }

        if ($type === 'checkout_session.payment.paid') {
            $this->markPaid($payment, $event);
        } else {
            $this->markFailed($payment, $event);
        }

        return response()->json(['received' => true]);
    }

    private function markPaid(Payment $payment, array $event): void
    {
        DB::transaction(function () use ($payment, $event): void {
            $payment->refresh()->load('order');

            if ($payment->status === 'paid') {
                return;
            }

            $resource = data_get($event, 'data.attributes.data', []);
            $providerPaymentId = data_get($resource, 'attributes.payments.0.id')
                ?? data_get($resource, 'attributes.payment_id');

            $payment->update([
                'status' => 'paid',
                'provider_payment_id' => $providerPaymentId,
                'amount_tendered' => $payment->amount_due,
                'paid_at' => Carbon::now(),
                'provider_payload' => $event,
            ]);

            if ($payment->order->status === OrderStatus::Pending) {
                $this->orders->transition(
                    $payment->order,
                    OrderStatus::Approved,
                    null,
                    'PayMongo payment confirmed.',
                );
            }
        });
    }

    private function markFailed(Payment $payment, array $event): void
    {
        $payment->update([
            'status' => 'failed',
            'failure_reason' => data_get($event, 'data.attributes.data.attributes.failure_reason'),
            'provider_payload' => $event,
        ]);
    }

    private function signatureIsValid(string $payload, string $header): bool
    {
        $parts = collect(explode(',', $header))
            ->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, '');

                return [trim($key) => trim($value)];
            });

        $timestamp = $parts->get('t');
        $provided = $parts->get($this->isLiveEvent($payload) ? 'li' : 'te');
        $secret = (string) config('services.paymongo.webhook_secret');

        if ($timestamp === null || $provided === null || $provided === '' || $secret === '') {
            return false;
        }

        if (abs(now()->timestamp - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return hash_equals($expected, $provided);
    }

    private function isLiveEvent(string $payload): bool
    {
        return (bool) data_get(json_decode($payload, true), 'data.attributes.livemode', false);
    }
}
