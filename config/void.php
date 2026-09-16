<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Store identity
    |--------------------------------------------------------------------------
    | Printed on receipts and shown in the POS chrome.
    */
    'store' => [
        'name' => env('STORE_NAME', 'VOID Clothing Co.'),
        'brand' => 'VOID',
        'address' => env('STORE_ADDRESS', 'Tangulan St., Kawit, Cavite 4102'),
        'tin' => env('STORE_TIN', '004-215-889-000'),
        'currency_symbol' => env('STORE_CURRENCY_SYMBOL', '₱'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Commerce rules
    |--------------------------------------------------------------------------
    | shipping_fee  : flat fee added to every online order (legacy behaviour).
    | pos.discount  : bundle discount applied at the register once the basket
    |                 reaches the quantity threshold.
    | tax           : Philippine VAT. Inclusive means the tax is already part of
    |                 the total and is only broken out on the receipt.
    */
    'shipping_fee' => (float) env('SHOP_SHIPPING_FEE', 50),

    'pos' => [
        'discount_threshold' => (int) env('POS_DISCOUNT_THRESHOLD', 3),
        'discount_rate' => (float) env('POS_DISCOUNT_RATE', 0.05),
        'terminal' => env('POS_TERMINAL', 'Counter 01'),
    ],

    'tax' => [
        'rate' => (float) env('TAX_RATE', 0.12),
        'inclusive' => filter_var(env('TAX_INCLUSIVE', true), FILTER_VALIDATE_BOOL),
    ],

    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Catalog
    |--------------------------------------------------------------------------
    | The sizes the shop stocks, in display order, with their short labels.
    */
    'sizes' => [
        'Small' => 'S',
        'Medium' => 'M',
        'Large' => 'L',
        'Extra Large' => 'XL',
    ],

    /*
    |--------------------------------------------------------------------------
    | Document numbering
    |--------------------------------------------------------------------------
    | Order references read `VD-POS-260824-0007`: the store's mark, the channel
    | the sale came through, the trading date, and a counter that restarts each
    | day. Short enough to read out, sortable, and enough on its own to find the
    | day's paperwork. See App\Services\ReferenceService.
    */
    'reference' => [
        'prefix' => env('ORDER_REF_PREFIX', 'VD'),
        'held_prefix' => env('HELD_REF_PREFIX', 'HOLD'),
        'padding' => 4,
        'channels' => [
            'pos' => 'POS',
            'online' => 'WEB',
        ],
    ],

    'receipt' => [
        'prefix' => 'VD',
        'padding' => 6,
        'return_window_days' => 7,
        'footer_note' => env('RECEIPT_FOOTER_NOTE', 'This document is your proof of purchase.'),
    ],
];
