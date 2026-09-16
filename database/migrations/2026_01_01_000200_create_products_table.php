<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The catalog, split the way the shop actually sells:
 *
 *  products          – one row per design (No Signal, Bubbles, …)
 *  product_variants  – one row per design + size, carrying price and stock
 *
 * The legacy `void_inventory`.`products` table stored one row per
 * (name, size) pair, i.e. it was really a variant table with no parent.
 * Splitting it gives the storefront a real product page and gives the POS a
 * product row with size chips, without duplicating the design metadata.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category', 50)->default('General');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category');
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('size', 50);
            $table->string('sku', 50)->nullable()->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('stock')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'size']);
            $table->index('stock');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
