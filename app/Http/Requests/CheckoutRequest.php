<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'street' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
            'payment' => ['required', 'in:digital'],
            'agreed_to_terms' => ['accepted'],

            // Payment is by transfer with a screenshot attached, so the proof
            // is what turns the order into something staff can review.
            'proof_of_payment' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proof_of_payment.required' => 'Upload a screenshot of your payment to place the order.',
            'proof_of_payment.image' => 'Proof of payment must be a JPG, PNG or WEBP image.',
            'proof_of_payment.max' => 'Proof of payment must be smaller than 5 MB.',
            'payment.in' => 'Please select a valid payment method.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'recipient_name' => 'full name',
            'postal_code' => 'postal code',
            'proof_of_payment' => 'proof of payment',
        ];
    }
}
