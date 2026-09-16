<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Customer;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly OrderService $orders,
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
            'totals' => $this->cart->totals(),
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

        // Proof of payment lives on the public disk so the order desk can view
        // it and the shopper can see their own upload back.
        $proofPath = $request->file('proof_of_payment')->store('payment_proofs', 'public');

        // Save the address the shopper typed if it is new, so it prefills next
        // time — the behaviour the original checkout had.
        $this->rememberAddress($customer, $data);

        $order = $this->orders->createOnlineOrder(
            lines: array_map(
                fn (array $line) => [
                    'product_variant_id' => $line['product_variant_id'],
                    'quantity' => $line['quantity'],
                ],
                $this->cart->lines()
            ),
            customer: $customer,
            shippingAddress: sprintf(
                '%s, %s, %s %s, %s',
                $data['street'],
                $data['city'],
                $data['province'],
                $data['postal_code'],
                $data['country']
            ),
            recipientName: $data['recipient_name'],
            proofPath: $proofPath,
            agreedToTerms: true,
        );

        $this->cart->clear();

        return redirect()
            ->route('shop.account')
            ->with('order_placed', $order->order_ref);
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
}
