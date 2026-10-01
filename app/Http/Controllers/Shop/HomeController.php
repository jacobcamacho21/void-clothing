<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('shop.index', [
            'products' => Product::active()->with('variants')->orderBy('id')->get(),
            'reviews' => ProductReview::query()
                ->with(['customer:id,username', 'product:id,name,slug,image'])
                ->where('is_approved', true)
                ->where('rating', '>=', 4)
                ->orderByDesc('rating')
                ->latest('created_at')
                ->limit(6)
                ->get(),
        ]);
    }
}
