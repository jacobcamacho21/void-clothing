<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds a usable catalog and two staff logins.
 *
 * For an installation that has the legacy databases available, prefer
 * `php artisan void:import-legacy` — it brings the real products, customers
 * and order history across. This seeder is for a clean environment where that
 * data is not present.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            StaffSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}
