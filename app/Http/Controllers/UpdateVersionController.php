<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\HeldSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class UpdateVersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $version = Cache::remember('app.update-version', now()->addSeconds(2), function (): string {
            $models = [
                Customer::class,
                CustomerAddress::class,
                HeldSale::class,
                Order::class,
                OrderItem::class,
                OrderStatusHistory::class,
                Payment::class,
                Product::class,
                ProductVariant::class,
                Receipt::class,
                User::class,
            ];

            $fingerprint = [];

            foreach ($models as $modelClass) {
                $summary = $modelClass::query()
                    ->selectRaw('MAX(updated_at) AS latest, COUNT(*) AS total')
                    ->first();

                $fingerprint[$modelClass] = [
                    'latest' => $summary?->latest,
                    'total' => (int) ($summary?->total ?? 0),
                ];
            }

            return sha1((string) json_encode($fingerprint));
        });

        return response()->json(['version' => $version])->header('Cache-Control', 'no-store');
    }
}
