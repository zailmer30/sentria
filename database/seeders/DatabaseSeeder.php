<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            PermissionMatrixSeeder::class,
            UserSeeder::class,
            // SystemSettingSeeder::class,
            CommitteeSeeder::class,
            // LegislativeContentSeeder::class,
            // AiDemoSeeder::class,
        ]);
    }
}
