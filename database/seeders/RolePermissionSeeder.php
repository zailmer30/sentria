<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the eight canonical roles. The granular permission matrix is layered
 * on by PermissionMatrixSeeder.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::query()->updateOrCreate(
                ['name' => $role->value, 'guard_name' => 'web'],
                [
                    'label' => $role->label(),
                    'description' => $role->description(),
                    'is_system' => true,
                    'precedence' => $role->precedence(),
                ],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
