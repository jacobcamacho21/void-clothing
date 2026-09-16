<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('adjustStock', Product::class) ?? false;
    }

    /**
     * Price and size are admin-only, so they are validated but ignored by the
     * controller for staff — the form does not render them either.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stock' => ['required', 'integer', 'min:0', 'max:999999'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'size' => ['nullable', 'string', 'max:50'],
        ];
    }
}
