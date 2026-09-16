<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Customer records, with their saved address and order count. Admin-only.
 */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->withCount('orders')
            ->with(['addresses' => fn ($query) => $query->latest('id')->limit(1)])
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.customers', [
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', 'unique:customers,username'],
            'email' => ['required', 'email', 'max:100', 'unique:customers,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
        ]);

        $customer = Customer::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        // The address is optional here; the shopper can add one at checkout.
        if ($payload = $this->addressPayload($data, $customer->username)) {
            $customer->addresses()->create($payload);
        }

        return redirect()
            ->route('admin.customers')
            ->with('status', 'Customer '.$customer->username.' created.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', Rule::unique('customers', 'username')->ignore($customer->id)],
            'email' => ['required', 'email', 'max:100', Rule::unique('customers', 'email')->ignore($customer->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
        ]);

        $customer->username = $data['username'];
        $customer->email = $data['email'];

        if (! empty($data['password'])) {
            $customer->password = $data['password'];
        }

        $customer->save();

        if ($payload = $this->addressPayload($data, $customer->username)) {
            $address = $customer->addresses()->latest('id')->first();

            $address ? $address->update($payload) : $customer->addresses()->create($payload);
        }

        return redirect()
            ->route('admin.customers')
            ->with('status', 'Customer '.$customer->username.' updated.');
    }

    /**
     * Build an address row from the optional fields on the form.
     *
     * Returns null when no street was given — the desk is allowed to create a
     * customer with no address at all, and the shopper supplies one at
     * checkout. Nullable fields are absent from the validated array entirely,
     * so every read is defaulted.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    private function addressPayload(array $data, string $fallbackName): ?array
    {
        $street = trim((string) ($data['street'] ?? ''));

        if ($street === '') {
            return null;
        }

        return [
            'recipient_name' => ($data['recipient_name'] ?? null) ?: $fallbackName,
            'phone' => ($data['phone'] ?? null) ?: '-',
            'street' => $street,
            'city' => ($data['city'] ?? null) ?: '-',
            'province' => ($data['province'] ?? null) ?: '-',
            'postal_code' => ($data['postal_code'] ?? null) ?: '-',
            'country' => ($data['country'] ?? null) ?: 'Philippines',
        ];
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $username = $customer->username;

        // Orders survive: the foreign key nulls out so the sales history and
        // its receipts stay intact after the account is gone.
        $customer->delete();

        return redirect()
            ->route('admin.customers')
            ->with('status', 'Customer '.$username.' deleted. Their past orders were kept.');
    }
}
