<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Store Administrator',
                'password' => 'password',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['username' => 'staff1'],
            [
                'name' => 'Counter Staff',
                'password' => 'password',
                'role' => 'staff',
                'is_active' => true,
            ]
        );
    }
}
