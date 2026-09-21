<?php

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\StaffAuthController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Pos\HeldSaleController;
use App\Http\Controllers\Pos\ReceiptController;
use App\Http\Controllers\Pos\RegisterController;
use App\Http\Controllers\Pos\SaleController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\CustomerAuthController;
use App\Http\Controllers\Shop\ForgotPasswordController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\ProfileController;
use App\Http\Controllers\Shop\ResetPasswordController;
use App\Models\Order;
use App\Services\OrderService;
use App\Exceptions\InvalidStatusTransitionException;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Global Route Aliases for Laravel Core Verification Listeners & Middleware
|--------------------------------------------------------------------------
| Placed OUTSIDE `Route::name('shop.')` so both `verification.notice` and
| `verification.verify` exist strictly under their exact global names.
*/
Route::middleware('auth:customer')->group(function () {
    Route::get('/shop/email/verify', function () {
        return view('shop.auth.verify-email');
    })->name('verification.notice');

    Route::get('/shop/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {$request->fulfill();
        return redirect()->route('shop.account')->with('status', 'Email verified successfully!');
    })->middleware(['signed'])->name('verification.verify');

    Route::post('/shop/email/verification-notification', function (Request $request) {$request->user('customer')->sendEmailVerificationNotification();
        return back()->with('status', 'Verification link sent!');
    })->middleware(['throttle:6,1'])->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Global Password Reset Routes
|--------------------------------------------------------------------------
| Must exist outside `Route::name('shop.')` so Laravel's password broker 
| can find `password.reset` directly without a prefix.
*/
Route::middleware('guest:customer')->group(function () {
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
| The customer-facing shop. Kept at the site root so the existing links and
| the approved design carry over unchanged.
*/
Route::name('shop.')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/products', [ProductController::class, 'index'])->name('products');
    Route::get('/apparel', [ProductController::class, 'apparel'])->name('apparel');
    Route::get('/search', [ProductController::class, 'search'])->name('search');
    Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('product');
    Route::view('/terms', 'shop.terms')->name('terms');

    // Cart endpoints are called by cart.js and answer with JSON.
    Route::prefix('shop/cart')->name('cart.')->group(function () {
        Route::get('/', [CartController::class, 'index'])->name('index');
        Route::post('/add', [CartController::class, 'store'])->name('add');
        Route::patch('/update', [CartController::class, 'update'])->name('update');
        Route::delete('/remove', [CartController::class, 'destroy'])->name('remove');
    });

    // Guest Customer Routes (Login, Register, Forgot Password)
    Route::middleware('guest:customer')->group(function () {
        Route::get('/login', [CustomerAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [CustomerAuthController::class, 'login'])->name('login.attempt');
        Route::get('/register', [CustomerAuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [CustomerAuthController::class, 'register'])->name('register.attempt');

        Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
        Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    });

    Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');

    // Authenticated Customer Core Routes
    Route::middleware('auth:customer')->prefix('shop')->group(function () {
        Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
        Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

        Route::get('/account', [ProfileController::class, 'show'])->name('account');
        Route::patch('/account', [ProfileController::class, 'updateName'])->name('account.name');
        Route::post('/account/addresses', [ProfileController::class, 'storeAddress'])->name('account.addresses.store');
        Route::patch('/account/addresses/{address}', [ProfileController::class, 'updateAddress'])->name('account.addresses.update');
        Route::delete('/account/addresses/{address}', [ProfileController::class, 'destroyAddress'])->name('account.addresses.destroy');
        Route::get('/account/orders/{order:order_ref}', [ProfileController::class, 'showOrder'])->name('account.order');
        
        Route::post('/account/orders/{order:order_ref}/cancellation', function (Request $request, Order $order, OrderService$orders): RedirectResponse {
            abort_unless($order->customer_id ===$request->user('customer')->id, 404);
            $data =$request->validate([
                'cancellation_reason' => ['required', 'string', 'max:1000'],
                'cancellation_details' => ['nullable', 'string', 'max:1000'],
            ]);

            try {
                $reason =$data['cancellation_reason'];
                if (! empty($data['cancellation_details'])) {
                    $reason .= ': '.$data['cancellation_details'];
                }
                $orders->requestCancellation($order,$reason);
            } catch (InvalidStatusTransitionException $exception) {
                return back()->withErrors(['cancellation_reason' => $exception->getMessage()]);
            }

            return back()->with('status', 'Your cancellation request has been sent for review.');
        })->name('account.order.cancellation');
    });
});

/*
|--------------------------------------------------------------------------
| Back office and register
|--------------------------------------------------------------------------
| Staff sign in once and reach both the admin pages and the POS.
*/

Route::prefix('staff')->name('staff.')->group(function () {
    Route::middleware('guest:web')->group(function () {
        Route::get('/login', [StaffAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [StaffAuthController::class, 'login'])->name('login.attempt');
    });

    Route::post('/logout', [StaffAuthController::class, 'logout'])
        ->middleware('auth:web')
        ->name('logout');
});

Route::middleware(['auth:web', 'active'])->group(function () {

    /* ---------------------------------------------------------------- POS */

    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [RegisterController::class, 'index'])->name('register');
        Route::get('/catalog', [RegisterController::class, 'catalog'])->name('catalog');

        Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
        Route::get('/sales/recent', [SaleController::class, 'recent'])->name('sales.recent');

        Route::get('/held-sales', [HeldSaleController::class, 'index'])->name('held.index');
        Route::post('/held-sales', [HeldSaleController::class, 'store'])->name('held.store');
        Route::delete('/held-sales/{heldSale}', [HeldSaleController::class, 'destroy'])->name('held.destroy');

        Route::get('/receipts/{order:order_ref}', [ReceiptController::class, 'show'])->name('receipt');
        Route::get('/receipts/{order:order_ref}/print', [ReceiptController::class, 'print'])->name('receipt.print');
    });

    /* ------------------------------------------------------- Back office */

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
        Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
        Route::patch('/inventory/{variant}', [InventoryController::class, 'update'])->name('inventory.update');
        Route::delete('/inventory/{variant}', [InventoryController::class, 'destroy'])->name('inventory.destroy');

        Route::get('/orders', [OrderController::class, 'index'])->name('orders');
        Route::get('/orders/{order:order_ref}', [OrderController::class, 'show'])->name('orders.show');
        Route::get('/orders/{order:order_ref}/proof', [OrderController::class, 'proof'])->name('orders.proof');
        Route::patch('/orders/{order:order_ref}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::patch('/orders/{order:order_ref}/cancellation', [OrderController::class, 'resolveCancellation'])->name('orders.cancellation');
        Route::delete('/orders/{order:order_ref}', [OrderController::class, 'destroy'])->name('orders.destroy');

        Route::middleware('admin')->group(function () {
            Route::get('/customers', [CustomerController::class, 'index'])->name('customers');
            Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
            Route::patch('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
            Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

            Route::get('/users', [UserController::class, 'index'])->name('users');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        });
    });
});