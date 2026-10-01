<?php

namespace App\Http\Controllers\Shop;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $order = $customer->orders()
            ->where('status', OrderStatus::Completed)
            ->whereHas('items', fn ($items) => $items->where('product_id', $product->id))
            ->latest('completed_at')
            ->first();

        abort_unless($order, 403, 'Reviews are available after a completed purchase.');

        if (ProductReview::where('customer_id', $customer->id)
            ->where('product_id', $product->id)
            ->exists()) {
            return back()->with('status', 'You already reviewed '.$product->name.'.');
        }

        ProductReview::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'rating' => $data['rating'],
            'body' => $data['body'],
            'is_approved' => true,
            'verified_purchase' => true,
        ]);

        return back()->with('status', 'Thanks for sharing your experience with '.$product->name.'.');
    }
}
