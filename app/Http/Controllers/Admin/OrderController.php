<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The order desk: review online orders, follow counter sales, move both along
 * the workflow.
 */
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Order::class);

        $search = trim((string) $request->query('q', ''));
        $status = OrderStatus::tryFrom((string) $request->query('status', ''));
        $channel = OrderChannel::tryFrom((string) $request->query('channel', ''));

        $orders = Order::query()
            ->with(['customer:id,username', 'cashier:id,name,username', 'items:id,order_id,product_name,quantity'])
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('order_ref', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('username', 'like', "%{$search}%"));
            }))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($channel !== null, fn ($query) => $query->where('channel', $channel))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'search' => $search,
            'status' => $status,
            'channel' => $channel,
            'statuses' => OrderStatus::cases(),
            'summary' => [
                'total' => Order::count(),
                'today' => Order::whereDate('created_at', today())->count(),
                'pending' => Order::where('status', OrderStatus::Pending)->count(),
                'counter_today' => Order::where('channel', OrderChannel::Pos)
                    ->whereDate('created_at', today())
                    ->count(),
            ],
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorize('view', $order);

        $order->load([
            'items', 'payment', 'receipt', 'cashier', 'customer',
            'statusHistories.author',
        ]);

        // Filtered by what this user may actually do, so a staff member is not
        // shown an empty action panel on an order only an admin can move.
        $allowed = array_values(array_filter(
            $order->status->allowedTransitions(),
            fn (OrderStatus $target) => $target !== OrderStatus::Cancelled
                || $request->user()->can('cancel', $order)
        ));

        return view('admin.orders.show', [
            'order' => $order,
            'allowedTransitions' => $allowed,

            // Button wording lives here so the desk reads in the language of
            // the workflow rather than of the enum.
            'labels' => [
                OrderStatus::Approved->value => 'Approve payment',
                OrderStatus::Completed->value => 'Mark fulfilled',
                OrderStatus::Pending->value => 'Return to pending',
                OrderStatus::Rejected->value => 'Reject',
                OrderStatus::Cancelled->value => 'Cancel order',
            ],
            'confirmations' => [
                OrderStatus::Approved->value => 'Approve this order? Stock will be deducted now.',
                OrderStatus::Completed->value => 'Mark this order fulfilled and issue its receipt?',
                OrderStatus::Pending->value => 'Send this order back to pending? Any stock it holds will be returned.',
                OrderStatus::Rejected->value => 'Reject this order? Any stock it holds will be returned.',
                OrderStatus::Cancelled->value => 'Cancel this order? Any stock it holds will be returned.',
            ],
        ]);
    }

    public function proof(Request $request, Order $order): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('view', $order);

        abort_unless(
            $order->proof_of_payment
                && Storage::disk('public')->exists($order->proof_of_payment),
            404
        );

        return response()->file(Storage::disk('public')->path($order->proof_of_payment));
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('review', $order);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $target = OrderStatus::from($validated['status']);

        // Cancelling reverses a settled sale, so it needs the stronger check.
        if ($target === OrderStatus::Cancelled) {
            $this->authorize('cancel', $order);
        }

        try {
            $this->orders->transition($order, $target, $request->user(), $validated['note'] ?? null);
        } catch (InvalidStatusTransitionException|InsufficientStockException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'Order '.$order->order_ref.' marked '.$target->label().'.');
    }

    public function resolveCancellation(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('resolveCancellation', $order);

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->orders->resolveCancellation(
                $order,
                $validated['decision'] === 'approve',
                $request->user(),
                $validated['note'] ?? null,
            );
        } catch (InvalidStatusTransitionException $exception) {
            return back()->withErrors(['cancellation' => $exception->getMessage()]);
        }

        return back()->with('status', 'Cancellation request '.($validated['decision'] === 'approve' ? 'approved.' : 'rejected.'));
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('delete', $order);

        $ref = $order->order_ref;
        $this->orders->delete($order);

        return redirect()
            ->route('admin.orders')
            ->with('status', 'Order '.$ref.' deleted. Any stock it held has been returned.');
    }
}
