<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The shopper's account page: their details, saved addresses, and the order
 * history with live status.
 */
class ProfileController extends Controller
{
    public function show(): View
    {
        $customer = Auth::guard('customer')->user();

        return view('shop.account', [
            'customer' => $customer,
            'addresses' => $customer->addresses()->latest('id')->get(),
            'orders' => $customer->orders()
                ->with('items')
                ->latest('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function updateName(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $data = $request->validate([
            'username' => [
                'required', 'string', 'max:50',
                Rule::unique('customers', 'username')->ignore($customer->id),
            ],
        ], [
            'username.unique' => 'That username is already taken.',
        ]);

        $customer->update($data);

        return back()->with('status', 'Your name has been updated.');
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        $customer->addresses()->create($this->validateAddress($request));

        return back()->with('status', 'Address saved.');
    }

    public function updateAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->assertOwned($address);

        $address->update($this->validateAddress($request));

        return back()->with('status', 'Address updated.');
    }

    public function destroyAddress(CustomerAddress $address): RedirectResponse
    {
        $this->assertOwned($address);

        $address->delete();

        return back()->with('status', 'Address removed.');
    }

    /**
     * A single order with its full line detail — how a shopper tracks what
     * they bought and where it currently stands.
     */
    public function showOrder(Order $order): View
    {
        $customer = Auth::guard('customer')->user();

        abort_unless($order->customer_id === $customer->id, 404);

        $order->load(['items', 'payment', 'receipt', 'statusHistories']);

        return view('shop.order', [
            'order' => $order,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'street' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
        ]);
    }

    private function assertOwned(CustomerAddress $address): void
    {
        abort_unless(
            $address->customer_id === Auth::guard('customer')->id(),
            404
        );
    }
}
