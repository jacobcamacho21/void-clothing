<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Either attach the size to an existing design, or name a new one.
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'name' => ['required_without:product_id', 'nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],

            'size' => ['required', 'string', 'max:50'],
            'sku' => ['nullable', 'string', 'max:50', 'unique:product_variants,sku'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'stock' => ['required', 'integer', 'min:0', 'max:999999'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->filled('product_id') || $this->filled('name')) {
                    return;
                }

                $validator->errors()->add('name', 'Choose an existing product or give the new one a name.');
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required_without' => 'Give the new product a name.',
            'sku.unique' => 'That SKU is already in use.',
        ];
    }
}
