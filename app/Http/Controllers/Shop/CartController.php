<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoints behind the slide-out cart. Same shape of interaction as the
 * original cart_api.php, but priced from the catalog rather than from a
 * hard-coded table in the browser.
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function index(): JsonResponse
    {
        return response()->json($this->snapshot());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $result = $this->cart->add($data['product_variant_id'], $data['quantity'] ?? 1);

        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'error' => $result['message'],
                ...$this->snapshot(),
            ], 422);
        }

        return response()->json(['success' => true, ...$this->snapshot()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $result = $this->cart->setQuantity($data['product_variant_id'], $data['quantity']);

        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'error' => $result['message'],
                ...$this->snapshot(),
            ], 422);
        }

        return response()->json(['success' => true, ...$this->snapshot()]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer'],
        ]);

        $this->cart->remove($data['product_variant_id']);

        return response()->json(['success' => true, ...$this->snapshot()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'items' => $this->cart->lines(),
            'totals' => $this->cart->totals(),
            'count' => $this->cart->count(),
        ];
    }
}
