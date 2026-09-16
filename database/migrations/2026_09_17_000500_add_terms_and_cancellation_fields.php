<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('password');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('agreed_to_terms')->default(false)->after('payment_method');
            $table->timestamp('terms_agreed_at')->nullable()->after('agreed_to_terms');
            $table->text('cancellation_reason')->nullable()->after('notes');
            $table->string('cancellation_previous_status', 32)->nullable()->after('cancellation_reason');
            $table->timestamp('cancellation_requested_at')->nullable()->after('cancellation_previous_status');
            $table->text('cancellation_review_note')->nullable()->after('cancellation_requested_at');
            $table->timestamp('cancellation_reviewed_at')->nullable()->after('cancellation_review_note');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY status ENUM('pending', 'approved', 'processing', 'dispatched', 'cancellation_requested', 'completed', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY status ENUM('pending', 'approved', 'completed', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'agreed_to_terms',
                'terms_agreed_at',
                'cancellation_reason',
                'cancellation_previous_status',
                'cancellation_requested_at',
                'cancellation_review_note',
                'cancellation_reviewed_at',
            ]);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('terms_accepted_at');
        });
    }
};
