<?php

namespace App\Http\Controllers\Pos;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The register screen: catalog on the left, open order on the right.
 */
class RegisterController extends Controller
{
    public function index(Request $request): View
    {
        $cashier = $request->user();

        return view('pos.register', [
            'catalog' => $this->catalogPayload(),
            'categories' => $this->categories(),
            'customers' => Customer::orderBy('username')->get(['id', 'username']),
            'shift' => $this->shiftSummary($cashier->id),
            'heldCount' => $cashier->heldSales()->count(),
            'pendingCount' => Order::where('status', OrderStatus::Pending)->count(),
            'discountRule' => [
                'threshold' => (int) config('void.pos.discount_threshold'),
                'rate' => (float) config('void.pos.discount_rate'),
            ],
            'terminal' => config('void.pos.terminal'),
        ]);
    }

    /**
     * Fresh catalog for the register, so stock counts on the size chips can be
     * refreshed after a sale without reloading the page.
     */
    public function catalog(): JsonResponse
    {
        return response()->json([
            'products' => $this->catalogPayload(),
            'categories' => $this->categories(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalogPayload(): array
    {
        return Product::query()
            ->active()
            ->with(['variants' => fn ($query) => $query->orderBy('id')])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'category' => $product->category,
                'description' => $product->description,
                'image' => $product->imageUrl(),
                'total_stock' => $product->totalStock(),
                'price_from' => $product->displayPrice(),
                'variants' => $product->variants
                    ->sortBy(fn ($variant) => $this->sizeRank($variant->size))
                    ->values()
                    ->map(fn ($variant) => [
                        'id' => $variant->id,
                        'size' => $variant->size,
                        'abbr' => $variant->sizeAbbreviation(),
                        'sku' => $variant->sku,
                        'price' => (float) $variant->price,
                        'stock' => (int) $variant->stock,
                    ])->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Position of a size in the shop's own order (S, M, L, XL).
     *
     * A size the catalog picked up outside that set still has to sort
     * somewhere predictable, so it goes after the known ones rather than
     * jumping to the front on a false from array_search.
     */
    private function sizeRank(string $size): int
    {
        $order = array_keys(config('void.sizes'));
        $index = array_search($size, $order, true);

        return $index === false ? count($order) : $index;
    }

    /** @return array<int, string> */
    private function categories(): array
    {
        return Product::active()
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * What this cashier has taken since midnight — the figures in the header.
     *
     * @return array{amount: float, count: int}
     */
    private function shiftSummary(int $cashierId): array
    {
        $sales = Order::query()
            ->where('channel', OrderChannel::Pos)
            ->where('cashier_id', $cashierId)
            ->where('status', OrderStatus::Completed)
            ->where('created_at', '>=', Carbon::today())
            ->selectRaw('COALESCE(SUM(total_amount), 0) AS amount, COUNT(*) AS count')
            ->first();

        return [
            'amount' => (float) ($sales->amount ?? 0),
            'count' => (int) ($sales->count ?? 0),
        ];
    }
}
