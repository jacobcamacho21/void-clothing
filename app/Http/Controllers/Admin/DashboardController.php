<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Ranges offered by the sales-trend chart. */
    private const RANGES = [7, 30, 90];

    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        $days = (int) $request->integer('range', 30);
        $days = in_array($days, self::RANGES, true) ? $days : 30;

        return view('admin.dashboard', [
            'isAdmin' => $isAdmin,
            'selectedDays' => $days,
            'ranges' => self::RANGES,
            'metrics' => $this->metrics($isAdmin),
            'trend' => $isAdmin ? $this->trend($days) : [],
            'topProducts' => $this->topProducts(),
            'totalUsers' => $isAdmin ? User::count() : 0,
            'totalCustomers' => $isAdmin ? Customer::count() : 0,
            'cancellationRequests' => Order::where('status', OrderStatus::CancellationRequested)
                ->with('customer')
                ->latest('cancellation_requested_at')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * Revenue counts orders whose stock has actually left the shelf, so a
     * pending order awaiting payment review is not booked as a sale.
     *
     * @return array<string, float|int>
     */
    private function metrics(bool $isAdmin): array
    {
        $variantStats = ProductVariant::selectRaw(
            'COUNT(*) AS total, SUM(CASE WHEN stock > 0 THEN 1 ELSE 0 END) AS in_stock'
        )->first();

        $total = (int) ($variantStats->total ?? 0);
        $inStock = (int) ($variantStats->in_stock ?? 0);

        return [
            'revenue' => $isAdmin
                ? (float) Order::holdingStock()->sum('total_amount')
                : 0.0,
            'orders' => Order::count(),
            'orders_today' => Order::whereDate('created_at', Carbon::today())->count(),
            'pending_review' => Order::where('status', OrderStatus::Pending)->count(),
            'counter_sales_today' => (float) Order::query()
                ->where('channel', OrderChannel::Pos)
                ->where('status', OrderStatus::Completed)
                ->whereDate('created_at', Carbon::today())
                ->sum('total_amount'),
            'active_products' => $inStock,
            'total_products' => Product::count(),
            // The catalog's size, so a stock count can be shown against what
            // it is a count of. "6" on its own says nothing; "6 of 40
            // variants" is the number the shelf actually cares about.
            'total_variants' => $total,
            'out_of_stock' => $total - $inStock,
            'stock_health' => $total > 0 ? ($inStock / $total) * 100 : 0.0,
            'low_stock' => ProductVariant::lowStock()->count(),
        ];
    }

    /**
     * Daily revenue and order counts for the trend chart, with empty days
     * filled in so the line has one point per day.
     *
     * @return array<int, array{date: string, label: string, revenue: float, orders: int}>
     */
    private function trend(int $days): array
    {
        $from = Carbon::today()->subDays($days - 1);

        $rows = Order::query()
            ->holdingStock()
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) AS day, SUM(total_amount) AS revenue, COUNT(*) AS orders')
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($row) => (string) $row->day);

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i);
            $key = $date->toDateString();

            $row = $rows->get($key);

            $series[] = [
                'date' => $key,
                'label' => $date->format('M d'),
                'revenue' => (float) ($row->revenue ?? 0),
                'orders' => (int) ($row->orders ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return Collection<int, object>
     */
    private function topProducts()
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', [OrderStatus::Approved->value, OrderStatus::Completed->value])
            ->groupBy('order_items.product_name')
            ->selectRaw('order_items.product_name AS product_name')
            ->selectRaw('COUNT(*) AS line_count')
            ->selectRaw('SUM(order_items.quantity) AS units_sold')
            ->selectRaw('SUM(order_items.line_total) AS revenue')
            ->orderByDesc('units_sold')
            ->limit(6)
            ->get();
    }
}
