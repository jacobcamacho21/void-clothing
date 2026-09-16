<?php

namespace App\Providers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);

        // Laravel's bundled paginator markup is Tailwind-only; this app styles
        // its own so the controls match the rest of the back office.
        Paginator::defaultView('vendor.pagination.void');
        Paginator::defaultSimpleView('vendor.pagination.void');

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Store identity is needed by the shop chrome, the POS header and the
        // receipt, so it is shared rather than passed from every controller.
        View::share('store', config('void.store'));

        // The staff sidebar is one shared component used by both the register
        // and the back office, so its badge counts are resolved here instead
        // of being passed by every staff controller. The register already
        // supplies its own values; those win, and this only fills the gaps.
        View::composer('partials.sidebar', function ($view) {
            $data = $view->getData();
            $user = auth()->user();

            if (! isset($data['pendingCount'])) {
                $view->with('pendingCount', Order::where('status', OrderStatus::Pending)->count());
            }

            if (! isset($data['heldCount'])) {
                $view->with('heldCount', $user?->heldSales()->count() ?? 0);
            }

            if (! isset($data['terminal'])) {
                $view->with('terminal', config('void.pos.terminal'));
            }
        });
    }
}
