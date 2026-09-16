<?php

namespace App\Http\Controllers\Pos;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePosSaleRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Charging an order at the register.
 */
class SaleController extends Controller
{
    /** Absorbs float rounding so an exact-cash tender is never called short. */
    private const CENT_TOLERANCE = 0.001;

    public function __construct(private readonly OrderService $orders) {}

    public function store(StorePosSaleRequest $request): JsonResponse
    {
        $method = $request->paymentMethod();
        $customer = $request->filled('customer_id')
            ? Customer::find($request->integer('customer_id'))
            : null;

        $tendered = (float) $request->input('amount_tendered', 0);

        try {
            // Price the basket first so the cash check runs against the real
            // total — prices come from the catalog, never from the browser.
            $quote = $this->orders->quote($request->validated('items'), OrderChannel::Pos);

            if ($method->requiresTender() && $tendered + self::CENT_TOLERANCE < $quote['total_amount']) {
                return response()->json([
                    'message' => 'Cash tendered is less than the amount due.',
                    'errors' => ['amount_tendered' => ['Cash tendered is less than the amount due.']],
                ], 422);
            }

            $order = $this->orders->createPosSale(
                lines: $request->validated('items'),
                cashier: $request->user(),
                method: $method,
                tendered: $tendered,
                customer: $customer,
                customerName: $request->string('customer_name')->toString() ?: null,
                reference: $request->string('reference')->toString() ?: null,
            );
        } catch (InsufficientStockException $e) {
            // Someone else sold the last one between loading the screen and
            // charging. Report it as a field error so the cashier can adjust.
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['items' => [$e->getMessage()]],
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['items' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'order' => $this->present($order),
            'receipt_url' => route('pos.receipt', $order),
            'print_url' => route('pos.receipt.print', $order),
        ], 201);
    }

    /**
     * The order-tracking board shown inside the register.
     */
    public function recent(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with(['customer:id,username', 'cashier:id,name,username', 'receipt:id,order_id,receipt_number'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $status = OrderStatus::tryFrom($request->string('status')->toString());

                if ($status !== null) {
                    $query->where('status', $status);
                }
            })
            ->latest('created_at')
            ->limit(25)
            ->get()
            ->map(fn (Order $order) => [
                'order_ref' => $order->order_ref,
                'customer' => $order->buyerName(),
                'channel' => $order->channel->label(),
                'placed_at' => $order->created_at?->format('M d, g:i A'),
                'total' => (float) $order->total_amount,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'has_receipt' => $order->receipt !== null,
                'receipt_url' => route('pos.receipt', $order),
            ]);

        return response()->json(['orders' => $orders]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Order $order): array
    {
        return [
            'order_ref' => $order->order_ref,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'customer' => $order->buyerName(),
            'cashier' => $order->cashier?->displayName(),
            'subtotal' => (float) $order->subtotal,
            'discount_amount' => (float) $order->discount_amount,
            'tax_amount' => (float) $order->tax_amount,
            'total_amount' => (float) $order->total_amount,
            'payment_method' => $order->payment_method,
            'amount_tendered' => (float) ($order->payment?->amount_tendered ?? 0),
            'change_due' => (float) ($order->payment?->change_due ?? 0),
            'receipt_number' => $order->receipt?->receipt_number,
            'placed_at' => $order->created_at?->toIso8601String(),
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'size' => $item->product_size,
                'abbr' => $item->sizeAbbreviation(),
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
            ])->all(),
        ];
    }
}
