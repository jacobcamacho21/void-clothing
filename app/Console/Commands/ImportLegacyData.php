<?php

namespace App\Console\Commands;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Copies the three legacy MySQL schemas into this application's tables.
 *
 * Reads only — the legacy databases are never modified, so they remain a
 * fallback. Runs are idempotent: records are matched on their natural keys
 * (username, email, product name + size, order reference) and updated rather
 * than duplicated, so the command is safe to re-run after a fix.
 */
class ImportLegacyData extends Command
{
    protected $signature = 'void:import-legacy
                            {--fresh : Wipe the imported tables first}';

    protected $description = 'Import staff, customers, catalog and orders from the legacy users_db / customers_db / void_inventory databases';

    /**
     * Descriptions lifted from the original per-product pages, which had no
     * database column to live in.
     *
     * @var array<string, string>
     */
    private const DESCRIPTIONS = [
        'NoSignal' => 'Heavyweight cotton, oversized fit, drop shoulder, "SIGNAL LOST" block print, retro TV halftone graphic, cursive "Please Stand By" detailing with "est. 2024" branding.',
        'Bubbles' => 'Heavyweight cotton, drop shoulder construction, center-chest anime character illustration with "VOID" bubble-style typography.',
        'Philemon' => 'Oversized cotton tee, drop shoulder construction, stippled anime-style character illustration with butterfly motif and "The butterfly, guiding transformation, embodying growth" typography at center chest.',
        'Time' => 'Oversized cotton tee, drop shoulder construction, anime-style character illustration with "Time" motif at center chest.',
        'GhostMode' => 'Oversized cotton tee, drop shoulder construction, anime-style character illustration with "Ghost Mode" motif at center chest.',
        'Alice' => 'Heavyweight cotton, oversized fit, drop shoulder, center-chest anime illustration in purple, "VOID XIII" vertical print with Japanese script "Will you die for me?".',
        'Evoker' => 'Oversized cotton tee, drop shoulder construction, "VOID" block lettering, anime-style character halftone graphic with "evoker" script at center chest.',
        'Temperance' => 'Oversized cotton tee, drop shoulder construction, anime-style character illustration with "Temperance" motif at center chest.',
    ];

    /**
     * Display names for designs the legacy catalog stored without spaces.
     *
     * @var array<string, string>
     */
    private const DISPLAY_NAMES = [
        'NoSignal' => 'No Signal',
        'GhostMode' => 'Ghost Mode',
    ];

    public function handle(): int
    {
        foreach (['legacy_users', 'legacy_customers', 'legacy_inventory'] as $connection) {
            if (! $this->canReach($connection)) {
                $this->error("Cannot reach the `{$connection}` database. Check the LEGACY_* settings in .env.");

                return self::FAILURE;
            }
        }

        if ($this->option('fresh')) {
            $this->wipe();
        }

        $this->components->info('Importing legacy data');

        $users = $this->importUsers();
        $customers = $this->importCustomers();
        $addresses = $this->importAddresses();
        [$products, $variants] = $this->importCatalog();
        [$orders, $items] = $this->importOrders();

        $this->newLine();
        $this->table(['Imported', 'Rows'], [
            ['Staff accounts', $users],
            ['Customers', $customers],
            ['Customer addresses', $addresses],
            ['Products', $products],
            ['Product variants', $variants],
            ['Orders', $orders],
            ['Order items', $items],
        ]);

        $this->components->info('Legacy import complete. The source databases were not modified.');

        return self::SUCCESS;
    }

    private function canReach(string $connection): bool
    {
        try {
            DB::connection($connection)->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function wipe(): void
    {
        if (! $this->confirm('This deletes every imported record in the application database. Continue?', false)) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        foreach ([
            'order_status_histories', 'receipts', 'payments', 'order_items', 'orders',
            'held_sales', 'product_variants', 'products', 'customer_addresses', 'customers', 'users',
        ] as $table) {
            DB::table($table)->delete();
        }

        Schema::enableForeignKeyConstraints();

        $this->components->warn('Existing records cleared.');
    }

    private function importUsers(): int
    {
        $count = 0;

        foreach (DB::connection('legacy_users')->table('users')->orderBy('user_id')->get() as $row) {
            $username = trim((string) $row->username);

            if ($username === '') {
                continue;
            }

            $existing = DB::table('users')->where('username', $username)->first();

            $attributes = [
                'name' => $existing->name ?? Str::headline($username),
                'role' => in_array($row->role, ['admin', 'staff'], true) ? $row->role : 'staff',
                'updated_at' => now(),
            ];

            if ($existing) {
                // The password is deliberately left alone on an existing
                // account: re-running the import after go-live must not undo a
                // password someone has since changed here.
                DB::table('users')->where('id', $existing->id)->update($attributes);
            } else {
                // The legacy column already holds a bcrypt hash, so it is
                // written through the query builder to bypass the model's
                // `hashed` cast — hashing it again would lock everyone out.
                DB::table('users')->insert($attributes + [
                    'username' => $username,
                    'password' => (string) $row->password,
                    'is_active' => true,
                    'created_at' => now(),
                ]);
            }

            $count++;
        }

        return $count;
    }

    private function importCustomers(): int
    {
        $count = 0;

        foreach (DB::connection('legacy_customers')->table('customer_acc')->orderBy('id')->get() as $row) {
            $username = trim((string) $row->username);
            $email = trim((string) $row->email);

            if ($username === '' || $email === '') {
                continue;
            }

            // Written through the query builder so the original primary keys
            // survive — the legacy orders reference customers by id — and so
            // the already-bcrypted password is stored verbatim rather than
            // being hashed a second time by the model cast.
            $attributes = [
                'username' => $username,
                'email' => $email,
                'updated_at' => $row->created_at,
            ];

            $existing = DB::table('customers')->where('id', $row->id)->exists();

            if ($existing) {
                // As with staff accounts, a password changed since the first
                // import is left as it is.
                DB::table('customers')->where('id', $row->id)->update($attributes);
            } else {
                DB::table('customers')->insert($attributes + [
                    'id' => $row->id,
                    'password' => (string) $row->password,
                    'created_at' => $row->created_at,
                ]);
            }

            $count++;
        }

        return $count;
    }

    private function importAddresses(): int
    {
        $count = 0;

        foreach (DB::connection('legacy_customers')->table('address')->orderBy('id')->get() as $row) {
            if (! Customer::whereKey($row->customer_id)->exists()) {
                continue;
            }

            $address = CustomerAddress::updateOrCreate(
                ['id' => $row->id],
                [
                    'customer_id' => $row->customer_id,
                    'recipient_name' => $row->recipient_name,
                    'phone' => $row->phone,
                    'street' => $row->street,
                    'city' => $row->city,
                    'province' => $row->province,
                    'postal_code' => $row->postal_code,
                    'country' => $row->country ?: 'Philippines',
                ]
            );

            $this->backdate($address, $row->created_at);

            $count++;
        }

        return $count;
    }

    /**
     * The legacy `products` table held one row per (name, size). Each distinct
     * name becomes a product; each row becomes one of its variants.
     *
     * @return array{0: int, 1: int}
     */
    private function importCatalog(): array
    {
        $rows = DB::connection('legacy_inventory')->table('products')->orderBy('product_id')->get();

        $products = 0;
        $variants = 0;

        foreach ($rows as $row) {
            $rawName = trim((string) $row->product_name);

            if ($rawName === '') {
                continue;
            }

            $displayName = self::DISPLAY_NAMES[$rawName] ?? $rawName;
            $slug = Str::slug($displayName);

            $product = Product::firstOrNew(['slug' => $slug]);

            if (! $product->exists) {
                $products++;
            }

            $product->name = $displayName;
            $product->category = trim((string) $row->category) ?: 'General';
            $product->description = $product->description ?: (self::DESCRIPTIONS[$rawName] ?? null);
            // The image files are stored without spaces, e.g. GhostMode.png.
            $product->image = str_replace(' ', '', $displayName).'.png';
            $product->is_active = true;
            $product->save();

            $size = trim((string) $row->product_size);

            if ($size === '') {
                continue;
            }

            $variant = ProductVariant::firstOrNew([
                'product_id' => $product->id,
                'size' => $size,
            ]);

            if (! $variant->exists) {
                $variants++;
            }

            // Product id keeps the SKU unique even when two designs share
            // their first letters; (product, size) is already unique.
            $variant->sku = $variant->sku ?: sprintf(
                'VD-%s%03d-%s',
                Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', $displayName), 0, 2)),
                $product->id,
                $this->sizeCode($size)
            );
            $variant->price = $row->unit_price;
            $variant->stock = max(0, (int) $row->stock);
            $variant->save();

            // Remember where the legacy row went so order items can be relinked.
            $this->variantMap[(int) $row->product_id] = $variant->id;
        }

        return [$products, $variants];
    }

    /** @var array<int, int> legacy products.product_id => product_variants.id */
    private array $variantMap = [];

    /** @return array{0: int, 1: int} */
    private function importOrders(): array
    {
        if ($this->variantMap === []) {
            $this->rebuildVariantMap();
        }

        $orders = 0;
        $items = 0;

        $legacy = DB::connection('legacy_inventory');

        foreach ($legacy->table('orders')->orderBy('id')->get() as $row) {
            $ref = trim((string) $row->order_ref);

            if ($ref === '') {
                $ref = 'ORDLEGACY'.$row->id;
            }

            $subtotal = (float) $row->total_amount;
            $shipping = (float) config('void.shipping_fee');
            $total = round($subtotal + $shipping, 2);
            $status = $this->mapStatus((string) $row->status);

            $order = Order::updateOrCreate(
                ['order_ref' => $ref],
                [
                    'channel' => OrderChannel::Online,
                    'status' => $status,
                    'customer_id' => Customer::whereKey($row->customer_id)->exists() ? $row->customer_id : null,
                    'customer_name' => $this->recipientNameFor((int) $row->customer_id),
                    'subtotal' => $subtotal,
                    'discount_amount' => 0,
                    'shipping_fee' => $shipping,
                    'tax_amount' => round($total - ($total / (1 + (float) config('void.tax.rate'))), 2),
                    'total_amount' => $total,
                    'payment_method' => PaymentMethod::Digital->value,
                    'proof_of_payment' => $row->proof_of_payment ?: null,
                    'shipping_address' => $this->addressFor((int) $row->customer_id),
                    'placed_at' => $row->created_at,
                    'completed_at' => $status === OrderStatus::Completed ? $row->created_at : null,
                ]
            );

            $this->backdate($order, $row->created_at);

            $orders++;

            $order->items()->delete();

            foreach ($legacy->table('order_items')->where('order_ref', $ref)->get() as $itemRow) {
                $variantId = $this->variantMap[(int) $itemRow->product_id] ?? null;
                $variant = $variantId ? ProductVariant::find($variantId) : null;

                $item = $order->items()->create([
                    'product_id' => $variant?->product_id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => self::DISPLAY_NAMES[$itemRow->product_name] ?? $itemRow->product_name,
                    'product_size' => $itemRow->product_size,
                    'unit_price' => $itemRow->unit_price,
                    'quantity' => $itemRow->quantity,
                    'line_total' => round((float) $itemRow->unit_price * (int) $itemRow->quantity, 2),
                ]);

                $this->backdate($item, $itemRow->created_at);

                $items++;
            }

            if ($order->payments()->count() === 0) {
                $payment = $order->payments()->create([
                    'method' => PaymentMethod::Digital->value,
                    'amount_due' => $total,
                    'amount_tendered' => $total,
                    'change_due' => 0,
                    'proof_path' => $row->proof_of_payment ?: null,
                    'paid_at' => $row->created_at,
                ]);

                $this->backdate($payment, $row->created_at);
            }

            if ($order->statusHistories()->count() === 0) {
                $history = $order->statusHistories()->create([
                    'from_status' => null,
                    'to_status' => $status->value,
                    'note' => 'Imported from the legacy system.',
                ]);

                $this->backdate($history, $row->created_at);
            }
        }

        return [$orders, $items];
    }

    /**
     * Rebuild the legacy-id map when orders are imported without the catalog
     * step having run in this process (a re-run against an already-imported
     * catalog).
     */
    private function rebuildVariantMap(): void
    {
        $rows = DB::connection('legacy_inventory')->table('products')->get();

        foreach ($rows as $row) {
            $displayName = self::DISPLAY_NAMES[trim((string) $row->product_name)] ?? trim((string) $row->product_name);
            $product = Product::where('slug', Str::slug($displayName))->first();

            if ($product === null) {
                continue;
            }

            $variant = ProductVariant::where('product_id', $product->id)
                ->where('size', trim((string) $row->product_size))
                ->first();

            if ($variant !== null) {
                $this->variantMap[(int) $row->product_id] = $variant->id;
            }
        }
    }

    /**
     * The legacy statuses were Pending / Approved / Rejected. Approved orders
     * were the ones that had been paid for and released, so they keep that
     * meaning here rather than being force-fitted to Completed.
     */
    private function mapStatus(string $legacy): OrderStatus
    {
        return match (Str::lower(trim($legacy))) {
            'approved' => OrderStatus::Approved,
            'rejected' => OrderStatus::Rejected,
            'completed' => OrderStatus::Completed,
            'cancelled', 'canceled' => OrderStatus::Cancelled,
            default => OrderStatus::Pending,
        };
    }

    private function recipientNameFor(int $customerId): ?string
    {
        $row = DB::connection('legacy_customers')
            ->table('address')
            ->where('customer_id', $customerId)
            ->orderByDesc('id')
            ->first();

        if ($row?->recipient_name) {
            return $row->recipient_name;
        }

        return DB::connection('legacy_customers')
            ->table('customer_acc')
            ->where('id', $customerId)
            ->value('username');
    }

    private function addressFor(int $customerId): ?string
    {
        $row = DB::connection('legacy_customers')
            ->table('address')
            ->where('customer_id', $customerId)
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return null;
        }

        return sprintf(
            '%s, %s, %s %s, %s',
            $row->street,
            $row->city,
            $row->province,
            $row->postal_code,
            $row->country
        );
    }

    private function sizeCode(string $size): string
    {
        return config('void.sizes')[$size] ?? Str::upper(Str::substr($size, 0, 2));
    }

    /**
     * Stamp a record with the date it was created in the legacy system.
     *
     * `created_at` is deliberately not mass-assignable on these models, so it
     * has to be forced — without this the whole imported history would show
     * the date of the import instead of the date of the sale.
     */
    private function backdate(Model $model, mixed $when): void
    {
        if (empty($when)) {
            return;
        }

        $model->timestamps = false;
        $model->forceFill(['created_at' => $when, 'updated_at' => $when])->save();
        $model->timestamps = true;
    }
}
