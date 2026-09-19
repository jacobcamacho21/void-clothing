<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Get all enum cases defined in PHP
        $statuses = array_map(fn ($case) => "'{$case->value}'", OrderStatus::cases());
        $allowedValues = implode(', ', $statuses);

        if (DB::getDriverName() === 'pgsql') {
            // Drop old PostgreSQL check constraint and re-add it with all current OrderStatus enum values
            DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ({$allowedValues}));");
        } elseif (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            // Update MySQL / MariaDB ENUM definition
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM({$allowedValues}) NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        // Rollback strategy if needed
    }
};