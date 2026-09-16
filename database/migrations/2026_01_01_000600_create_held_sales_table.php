<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A basket parked at the register so the cashier can serve the next customer.
 * Held sales are stored rather than kept in the browser so they survive a
 * reload, a crash, or the cashier moving to another terminal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('held_sales', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32);
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->json('items');
            $table->unsignedInteger('item_count')->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('held_sales');
    }
};
