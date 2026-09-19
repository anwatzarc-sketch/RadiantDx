<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * A fresh installation receives the permission catalogue, the Super Admin role
 * and the Super Admin user. Operational roles, laboratory staff accounts and
 * catalogue content are created from the UI.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
