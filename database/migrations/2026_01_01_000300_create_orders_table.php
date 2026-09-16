<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders from both sales channels.
 *
 * `channel` distinguishes an online checkout from a sale rung up at the
 * register. The legacy schema stored only a bare `total_amount` (which really
 * held the subtotal) and then added the flat shipping fee in three different
 * views; every component of the total is stored here so the receipt, the order
 * desk and the shopper's order history can never disagree.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_ref', 32)->unique();
            $table->enum('channel', ['online', 'pos'])->default('online');
            $table->enum('status', [
                'pending', 'approved', 'completed', 'rejected', 'cancelled',
            ])->default('pending');

            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);

            $table->string('payment_method', 32)->default('Cash');
            $table->string('proof_of_payment')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('notes')->nullable();

            $table->timestamp('placed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['channel', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            // Denormalised on purpose: a receipt reprinted a year from now must
            // still show what was actually sold, even if the catalog moved on.
            $table->string('product_name');
            $table->string('product_size', 50)->nullable();
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
