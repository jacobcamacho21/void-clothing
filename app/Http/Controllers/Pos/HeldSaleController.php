<?php

namespace App\Http\Controllers\Pos;

use App\Enums\OrderChannel;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\HeldSale;
use App\Services\OrderService;
use App\Services\ReferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Parking and resuming a basket so the cashier can serve the next customer.
 */
class HeldSaleController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly ReferenceService $references,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $held = $request->user()->heldSales()
            ->latest()
            ->get()
            ->map(fn (HeldSale $sale) => [
                'id' => $sale->id,
                'reference' => $sale->reference,
                'customer_id' => $sale->customer_id,
                'customer_name' => $sale->customer_name ?: 'Walk-in Customer',
                'item_count' => $sale->item_count,
                'line_count' => $sale->lineCount(),
                'total' => (float) $sale->total,
                'held_at' => $sale->created_at?->format('g:i A'),
                'items' => $sale->items,
            ]);

        return response()->json(['held' => $held]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $quote = $this->orders->quote($data['items'], OrderChannel::Pos);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        $customer = isset($data['customer_id']) ? Customer::find($data['customer_id']) : null;

        // The hold gets its reference here, not from the browser, so a parked
        // basket carries a document number in the same house format as the
        // sale it will become — HOLD-260824-0002.
        $sale = DB::transaction(fn () => $request->user()->heldSales()->create([
            'reference' => $this->references->heldSaleReference(),
            'customer_id' => $customer?->id,
            'customer_name' => $data['customer_name'] ?? $customer?->username ?? 'Walk-in Customer',
            'items' => $data['items'],
            'item_count' => $quote['quantity'],
            'total' => $quote['total_amount'],
        ]));

        return response()->json([
            'held' => [
                'id' => $sale->id,
                'reference' => $sale->reference,
                'total' => (float) $sale->total,
            ],
            'held_count' => $request->user()->heldSales()->count(),
        ], 201);
    }

    /**
     * Resuming a held sale removes it — the basket goes back to the register.
     * Scoped to the signed-in cashier so one till cannot pick up another's.
     */
    public function destroy(Request $request, HeldSale $heldSale): JsonResponse
    {
        abort_unless($heldSale->cashier_id === $request->user()->id, 403);

        $payload = [
            'reference' => $heldSale->reference,
            'customer_id' => $heldSale->customer_id,
            'customer_name' => $heldSale->customer_name,
            'items' => $heldSale->items,
        ];

        $heldSale->delete();

        return response()->json([
            'resumed' => $payload,
            'held_count' => $request->user()->heldSales()->count(),
        ]);
    }
}
