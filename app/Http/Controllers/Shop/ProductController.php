<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('shop.products', [
            'heading' => 'All Products',
            'products' => Product::active()->with('variants')->orderBy('id')->get(),
        ]);
    }

    /**
     * The apparel collection.
     *
     * The original page was a placeholder that listed nothing at all. Every
     * design the shop stocks is apparel, so it lists the catalog rather than
     * staying empty; when the shop starts carrying non-apparel lines this is
     * where the category filter belongs.
     */
    public function apparel(): View
    {
        return view('shop.products', [
            'heading' => 'Apparel',
            'products' => Product::active()->with('variants')->orderBy('id')->get(),
        ]);
    }

    public function search(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        $products = Product::active()
            ->with('variants')
            ->when($term !== '', fn ($query) => $query->where(function ($inner) use ($term) {
                $inner->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('category', 'like', "%{$term}%");
            }))
            ->orderBy('name')
            ->get();

        return view('shop.search', [
            'heading' => $term === '' ? 'Search' : 'Results for "'.$term.'"',
            'term' => $term,
            'products' => $products,
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);

        $product->load('variants');

        // Present sizes in the shop's own order (S, M, L, XL), not insertion
        // order. Anything outside that set sorts after the known sizes.
        $order = array_keys(config('void.sizes'));

        $variants = $product->variants
            ->sortBy(function ($variant) use ($order) {
                $index = array_search($variant->size, $order, true);

                return $index === false ? count($order) : $index;
            })
            ->values();

        return view('shop.product', [
            'product' => $product,
            'variants' => $variants,
        ]);
    }
}
