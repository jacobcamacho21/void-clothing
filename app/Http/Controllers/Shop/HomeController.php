<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('shop.index', [
            'products' => Product::active()->with('variants')->orderBy('id')->get(),
        ]);
    }
}
