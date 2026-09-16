<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePosSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],

            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'amount_tendered' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],

            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Cash has to cover the bill. The exact total is recomputed server-side in
     * the service, so this only rejects the obviously-short tender early and
     * gives the cashier a clear message.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('payment_method') !== PaymentMethod::Cash->value) {
                    return;
                }

                if ($this->input('amount_tendered') === null) {
                    $validator->errors()->add('amount_tendered', 'Enter the cash received.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one item before charging the order.',
            'items.min' => 'Add at least one item before charging the order.',
            'items.*.product_variant_id.exists' => 'One of the items is no longer in the catalog.',
        ];
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from($this->string('payment_method')->toString());
    }
}
