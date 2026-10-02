<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('provider', 32)->nullable()->after('method');
            $table->string('provider_checkout_id')->nullable()->unique()->after('provider');
            $table->string('provider_payment_id')->nullable()->unique()->after('provider_checkout_id');
            $table->string('status', 32)->default('pending')->after('provider_payment_id');
            $table->text('failure_reason')->nullable()->after('status');
            $table->json('provider_payload')->nullable()->after('failure_reason');
        });

        Schema::create('deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('quote_reference')->nullable();
            $table->decimal('quoted_fee', 10, 2)->default(0);
            $table->timestamp('quote_expires_at')->nullable();
            $table->decimal('actual_fee', 10, 2)->nullable();
            $table->string('booking_reference')->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('status', 32)->default('awaiting_booking');
            $table->text('pickup_address')->nullable();
            $table->decimal('pickup_latitude', 10, 7)->nullable();
            $table->decimal('pickup_longitude', 10, 7)->nullable();
            $table->text('dropoff_address')->nullable();
            $table->decimal('dropoff_latitude', 10, 7)->nullable();
            $table->decimal('dropoff_longitude', 10, 7)->nullable();
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['provider_checkout_id']);
            $table->dropUnique(['provider_payment_id']);
            $table->dropColumn([
                'provider',
                'provider_checkout_id',
                'provider_payment_id',
                'status',
                'failure_reason',
                'provider_payload',
            ]);
        });
    }
};
