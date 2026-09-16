<?php

namespace App\Services;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Everything that creates or moves an order.
 *
 * Controllers stay thin; the rules about when stock moves and which status
 * changes are legal live here, so the storefront, the order desk and the
 * register all behave the same way.
 */
class OrderService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly InventoryService $inventory,
        private readonly ReceiptService $receipts,
        private readonly ReferenceService $references,
    ) {}

    /**
     * Price a basket without recording anything.
     *
     * Lets the register check the cash tendered against the authoritative
     * total before an order is written, rather than creating one and having to
     * unwind it.
     *
     * @param  array<int, array{product_variant_id: int, quantity: int}>  $lines
     * @return array{subtotal: float, discount_amount: float, shipping_fee: float, tax_amount: float, total_amount: float, quantity: int}
     */
    public function quote(array $lines, OrderChannel $channel): array
    {
        return $this->pricing->totals($this->resolveLines($lines), $channel);
    }

    /**
     * Ring up a counter sale: priced, paid and handed over in one step, so it
     * is written straight to Completed with the stock deducted.
     *
     * @param  array<int, array{product_variant_id: int, quantity: int}>  $lines
     */
    public function createPosSale(
        array $lines,
        User $cashier,
        PaymentMethod $method,
        float $tendered,
        ?Customer $customer = null,
        ?string $customerName = null,
        ?string $reference = null,
    ): Order {
        return DB::transaction(function () use ($lines, $cashier, $method, $tendered, $customer, $customerName, $reference) {
            $resolved = $this->resolveLines($lines);

            $this->inventory->assertAvailable(array_map(
                fn (array $line) => [
                    'product_variant_id' => $line['product_variant_id'],
                    'quantity' => $line['quantity'],
                ],
                $resolved
            ));

            $totals = $this->pricing->totals($resolved, OrderChannel::Pos);
            $now = Carbon::now();

            $order = Order::create([
                'order_ref' => $this->generateReference(OrderChannel::Pos),
                'channel' => OrderChannel::Pos,
                'status' => OrderStatus::Completed,
                'customer_id' => $customer?->id,
                'customer_name' => $customerName ?: ($customer?->username ?? 'Walk-in Customer'),
                'cashier_id' => $cashier->id,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'shipping_fee' => $totals['shipping_fee'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'payment_method' => $method->value,
                'placed_at' => $now,
                'completed_at' => $now,
            ]);

            $this->writeItems($order, $resolved);
            $order->load('items');

            $this->inventory->deductForOrder($order);

            $tendered = $method->requiresTender() ? $tendered : $totals['total_amount'];

            $order->payments()->create([
                'method' => $method->value,
                'amount_due' => $totals['total_amount'],
                'amount_tendered' => $tendered,
                'change_due' => max(0, round($tendered - $totals['total_amount'], 2)),
                'reference' => $reference,
                'received_by' => $cashier->id,
                'paid_at' => $now,
            ]);

            $this->recordHistory($order, null, OrderStatus::Completed, 'Sale completed at the register.', $cashier);

            $this->receipts->issue($order, $cashier);

            return $order->fresh(['items', 'payment', 'receipt', 'cashier', 'customer']);
        });
    }

    /**
     * Place an online order. It arrives Pending; stock is not committed until
     * a staff member approves the payment proof — the legacy rule, preserved.
     *
     * @param  array<int, array{product_variant_id: int, quantity: int}>  $lines
     */
    public function createOnlineOrder(
        array $lines,
        Customer $customer,
        string $shippingAddress,
        string $recipientName,
        ?string $proofPath = null,
        PaymentMethod $method = PaymentMethod::Digital,
        bool $agreedToTerms = false,
    ): Order {
        return DB::transaction(function () use ($lines, $customer, $shippingAddress, $recipientName, $proofPath, $method, $agreedToTerms) {
            $resolved = $this->resolveLines($lines);
            $totals = $this->pricing->totals($resolved, OrderChannel::Online);
            $now = Carbon::now();

            $order = Order::create([
                'order_ref' => $this->generateReference(OrderChannel::Online),
                'channel' => OrderChannel::Online,
                'status' => OrderStatus::Pending,
                'customer_id' => $customer->id,
                'customer_name' => $recipientName,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'shipping_fee' => $totals['shipping_fee'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'payment_method' => $method->value,
                'agreed_to_terms' => $agreedToTerms,
                'terms_agreed_at' => $agreedToTerms ? $now : null,
                'proof_of_payment' => $proofPath,
                'shipping_address' => $shippingAddress,
                'placed_at' => $now,
            ]);

            $this->writeItems($order, $resolved);

            $order->payments()->create([
                'method' => $method->value,
                'amount_due' => $totals['total_amount'],
                'amount_tendered' => $totals['total_amount'],
                'change_due' => 0,
                'proof_path' => $proofPath,
                'paid_at' => $now,
            ]);

            $this->recordHistory($order, null, OrderStatus::Pending, 'Order placed online.');

            return $order->fresh(['items', 'payment']);
        });
    }

    /**
     * Move an order along the workflow, applying whatever stock movement the
     * transition implies.
     *
     * @throws InvalidStatusTransitionException
     * @throws InsufficientStockException
     */
    public function transition(Order $order, OrderStatus $target, ?User $actor = null, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $target, $actor, $note) {
            $order->refresh()->load('items');
            $current = $order->status;

            if ($current === $target) {
                return $order;
            }

            if (! $current->canTransitionTo($target)) {
                throw new InvalidStatusTransitionException(
                    sprintf('An order that is %s cannot be marked %s.', $current->label(), $target->label())
                );
            }

            // Stock follows the status: commit it when the order starts holding
            // stock, release it when it stops.
            if (! $current->holdsStock() && $target->holdsStock()) {
                $this->inventory->deductForOrder($order);
            } elseif ($current->holdsStock() && ! $target->holdsStock()) {
                $this->inventory->restoreForOrder($order);
            }

            $order->status = $target;

            if ($target === OrderStatus::Completed) {
                $order->completed_at = Carbon::now();
                $this->receipts->issue($order, $actor);
            }

            $order->save();

            $this->recordHistory($order, $current, $target, $note, $actor);

            return $order->fresh(['items', 'payment', 'receipt']);
        });
    }

    public function requestCancellation(Order $order, string $reason): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $order->refresh();
            $current = $order->status;

            if (! in_array($current, [OrderStatus::Pending, OrderStatus::Approved, OrderStatus::Processing], true)) {
                throw new InvalidStatusTransitionException('This order can no longer be cancelled before dispatch.');
            }

            $order->update([
                'status' => OrderStatus::CancellationRequested,
                'cancellation_reason' => $reason,
                'cancellation_previous_status' => $current->value,
                'cancellation_requested_at' => Carbon::now(),
            ]);

            $this->recordHistory($order, $current, OrderStatus::CancellationRequested, $reason);

            return $order->fresh(['items']);
        });
    }

    public function resolveCancellation(Order $order, bool $approve, ?User $actor = null, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $approve, $actor, $note) {
            $order->refresh()->load('items');

            if ($order->status !== OrderStatus::CancellationRequested) {
                throw new InvalidStatusTransitionException('This order has no pending cancellation request.');
            }

            if ($approve) {
                if ($order->status->holdsStock() || in_array($order->cancellation_previous_status, [OrderStatus::Approved->value, OrderStatus::Processing->value, OrderStatus::Dispatched->value], true)) {
                    $this->inventory->restoreForOrder($order);
                }
                $target = OrderStatus::Cancelled;
            } else {
                $target = OrderStatus::tryFrom((string) $order->cancellation_previous_status) ?? OrderStatus::Processing;
            }

            $order->update([
                'status' => $target,
                'cancellation_review_note' => $note,
                'cancellation_reviewed_at' => Carbon::now(),
            ]);

            $this->recordHistory($order, OrderStatus::CancellationRequested, $target, $note ?: ($approve ? 'Cancellation approved.' : 'Cancellation rejected.'), $actor);

            return $order->fresh(['items']);
        });
    }

    /**
     * Delete an order, returning any stock it was still holding.
     */
    public function delete(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->load('items');

            if ($order->status->holdsStock()) {
                $this->inventory->restoreForOrder($order);
            }

            $order->delete();
        });
    }

    /**
     * Turn `{variant id, quantity}` pairs into priced lines using the current
     * catalog price — never a price supplied by the browser.
     *
     * @param  array<int, array{product_variant_id: int|string, quantity: int|string}>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function resolveLines(array $lines): array
    {
        $merged = [];

        foreach ($lines as $line) {
            $id = (int) $line['product_variant_id'];
            $quantity = (int) $line['quantity'];

            if ($id <= 0 || $quantity <= 0) {
                continue;
            }

            $merged[$id] = ($merged[$id] ?? 0) + $quantity;
        }

        if ($merged === []) {
            throw new \InvalidArgumentException('An order needs at least one item.');
        }

        $variants = ProductVariant::with('product')
            ->whereIn('id', array_keys($merged))
            ->get()
            ->keyBy('id');

        $resolved = [];

        foreach ($merged as $id => $quantity) {
            $variant = $variants->get($id);

            if ($variant === null) {
                throw new \InvalidArgumentException('One of the selected items is no longer in the catalog.');
            }

            $price = (float) $variant->price;

            $resolved[] = [
                'product_variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'product_name' => $variant->product?->name ?? 'Unknown item',
                'product_size' => $variant->size,
                'unit_price' => $price,
                'quantity' => $quantity,
                'line_total' => round($price * $quantity, 2),
            ];
        }

        return $resolved;
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function writeItems(Order $order, array $lines): void
    {
        foreach ($lines as $line) {
            $order->items()->create($line);
        }
    }

    private function recordHistory(
        Order $order,
        ?OrderStatus $from,
        OrderStatus $to,
        ?string $note = null,
        ?User $actor = null
    ): void {
        $order->statusHistories()->create([
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note,
            'changed_by' => $actor?->id,
        ]);
    }

    /**
     * The reference a customer or cashier quotes for this order, e.g.
     * `VD-POS-260824-0007`. Allocated by ReferenceService inside the caller's
     * transaction, so the same counter cannot be handed out twice.
     */
    public function generateReference(OrderChannel $channel = OrderChannel::Online): string
    {
        return $this->references->orderReference($channel);
    }
}
