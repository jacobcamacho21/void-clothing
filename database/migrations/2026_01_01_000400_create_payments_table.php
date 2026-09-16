<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One payment record per settled order. Cash sales carry the tendered amount
 * and the change handed back; digital payments carry the uploaded proof.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method', 32);
            $table->decimal('amount_due', 10, 2)->default(0);
            $table->decimal('amount_tendered', 10, 2)->default(0);
            $table->decimal('change_due', 10, 2)->default(0);
            $table->string('reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
