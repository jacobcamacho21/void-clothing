<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Order;
use App\Services\CartService;
use App\Services\Delivery\DeliveryQuoteRequest;
use App\Services\Delivery\LalamoveService;
use App\Services\OrderService;
use App\Services\Payments\PayMongoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly OrderService $orders,
        private readonly LalamoveService $lalamove,
        private readonly PayMongoService $paymongo,
    ) {}

    public function show(): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()
                ->route('shop.products')
                ->with('status', 'Your cart is empty.');
        }

        $customer = Auth::guard('customer')->user();

        return view('shop.checkout', [
            'lines' => $this->cart->lines(),
            'totals' => $this->cart->totals(0),
            'address' => $customer->latestAddress(),
            'customer' => $customer,
        ]);
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()
                ->route('shop.products')
                ->with('status', 'Your cart is empty.');
        }

        $data = $request->validated();
        $customer = Auth::guard('customer')->user();

        // Remember the address even when the courier provider is temporarily unavailable.
        $this->rememberAddress($customer, $data);

        try {
            $deliveryQuote = $this->deliveryQuote($data);
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors(['delivery' => $exception->getMessage()]);
        }

        $order = $this->orders->createOnlineOrder(
            lines: array_map(
                fn (array $line) => [
                    'product_variant_id' => $line['product_variant_id'],
                    'quantity' => $line['quantity'],
                ],
                $this->cart->lines()
            ),
            customer: $customer,
            shippingAddress: $this->formatAddress($data),
            recipientName: $data['recipient_name'],
            proofPath: null,
            method: PaymentMethod::PayMongo,
            agreedToTerms: (bool) $data['agreed_to_terms'],
            deliveryQuote: $deliveryQuote,
        );

        try {
            $session = $this->paymongo->createCheckoutSession($order->load('items'));
            $order->payment()->update([
                'provider_checkout_id' => $session->id,
                'reference' => $session->id,
            ]);
        } catch (RuntimeException $exception) {
            $this->orders->delete($order);

            return back()
                ->withInput()
                ->withErrors(['payment' => $exception->getMessage()]);
        }

        $this->cart->clear();

        return redirect()->away($session->url);
    }

    public function shippingQuote(Request $request): JsonResponse
    {
        if ($this->cart->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty.'], 422);
        }

        $data = $request->validate([
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'street' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
        ]);

        try {
            $quote = $this->deliveryQuote($data);

            $totals = $this->cart->totals($quote->fee);

            return response()->json([
                'provider' => $quote->provider,
                'reference' => $quote->reference,
                'fee' => $quote->fee,
                'currency' => $quote->currency,
                'expires_at' => $quote->expiresAt->toIso8601String(),
                'total' => $totals['total_amount'],
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function paymentSuccess(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->customer_id === $request->user('customer')->id, 404);

        $this->paymongo->confirmCheckoutSession($order->payment()->firstOrFail());

        $order->refresh();

        return redirect()->route('shop.account')->with(
            'status',
            $order->status === \App\Enums\OrderStatus::Approved
                ? 'Payment confirmed for '.$order->order_ref.'.'
                : 'Payment is being confirmed for '.$order->order_ref.'.',
        );
    }

    public function paymentCancel(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->customer_id === $request->user('customer')->id, 404);

        return redirect()->route('shop.account')->with('status', 'Payment was cancelled for '.$order->order_ref.'.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function rememberAddress(Customer $customer, array $data): void
    {
        $match = $customer->addresses()
            ->where('recipient_name', $data['recipient_name'])
            ->where('phone', $data['phone'])
            ->where('street', $data['street'])
            ->where('city', $data['city'])
            ->where('province', $data['province'])
            ->where('postal_code', $data['postal_code'])
            ->where('country', $data['country'])
            ->exists();

        if ($match) {
            return;
        }

        $customer->addresses()->create([
            'recipient_name' => $data['recipient_name'],
            'phone' => $data['phone'],
            'street' => $data['street'],
            'city' => $data['city'],
            'province' => $data['province'],
            'postal_code' => $data['postal_code'],
            'country' => $data['country'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function deliveryQuote(array $data): \App\Services\Delivery\DeliveryQuote
    {
        return $this->lalamove->quote(new DeliveryQuoteRequest(
            pickupAddress: (string) config('services.lalamove.pickup_address'),
            pickupLatitude: (string) config('services.lalamove.pickup_latitude'),
            pickupLongitude: (string) config('services.lalamove.pickup_longitude'),
            dropoffAddress: $this->formatAddress($data),
            dropoffLatitude: null,
            dropoffLongitude: null,
            recipientName: $data['recipient_name'],
            recipientPhone: $data['phone'],
            itemQuantity: (int) $this->cart->count(),
        ));
    }

    /** @param array<string, mixed> $data */
    private function formatAddress(array $data): string
    {
        return sprintf(
            '%s, %s, %s %s, %s',
            $data['street'],
            $data['city'],
            $data['province'],
            $data['postal_code'],
            $data['country'],
        );
    }
}
