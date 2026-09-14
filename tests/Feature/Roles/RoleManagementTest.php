<?php

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function rolesActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-roles@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('lets a system administrator manage custom roles', function (): void {
    $admin = rolesActor(UserRole::SystemAdministrator);
    $permission = Permission::query()->where('name', 'documents.viewAny')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Roles/Index')
            ->where('can.create', true));

    $this->actingAs($admin)
        ->post(route('roles.store'), [
            'name' => 'records-clerk',
            'label' => 'Records Clerk',
            'description' => 'Can view the document register.',
            'precedence' => 50,
            'permissions' => [$permission->name],
        ])
        ->assertRedirect();

    $role = Role::query()->where('name', 'records-clerk')->firstOrFail();

    expect($role->label)->toBe('Records Clerk')
        ->and($role->is_system)->toBeFalse()
        ->and($role->hasPermissionTo('documents.viewAny'))->toBeTrue();

    $this->actingAs($admin)
        ->put(route('roles.update', $role), [
            'label' => 'Records Clerk II',
            'description' => 'Updated description.',
            'precedence' => 55,
            'permissions' => [$permission->name, 'documents.view'],
        ])
        ->assertRedirect(route('roles.edit', $role));

    $role->refresh();

    expect($role->label)->toBe('Records Clerk II')
        ->and($role->hasPermissionTo('documents.view'))->toBeTrue();

    $this->actingAs($admin)
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));

    expect(Role::query()->whereKey($role->getKey())->exists())->toBeFalse();
});

it('refuses to delete a system role', function (): void {
    $admin = rolesActor(UserRole::SystemAdministrator);
    $systemRole = Role::query()->where('name', UserRole::Secretariat->value)->firstOrFail();

    $this->actingAs($admin)
        ->delete(route('roles.destroy', $systemRole))
        ->assertForbidden();

    expect(Role::query()->whereKey($systemRole->getKey())->exists())->toBeTrue();
});

it('refuses to delete a custom role that still has users', function (): void {
    $admin = rolesActor(UserRole::SystemAdministrator);

    $role = Role::query()->create([
        'name' => 'temp-clerk',
        'guard_name' => 'web',
        'label' => 'Temp Clerk',
        'is_system' => false,
        'precedence' => 90,
    ]);

    $holder = User::factory()->create([
        'email' => 'temp-holder@sentria.test',
        'is_active' => true,
    ])->assignRole($role);

    $this->actingAs($admin)
        ->from(route('roles.edit', $role))
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.edit', $role))
        ->assertSessionHas('error', 'roles.delete_has_users');

    expect(Role::query()->whereKey($role->getKey())->exists())->toBeTrue();

    $holder->removeRole($role);

    $this->actingAs($admin)
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));
});

it('forbids secretariat from viewing roles', function (): void {
    $secretariat = rolesActor(UserRole::Secretariat);

    $this->actingAs($secretariat)
        ->get(route('roles.index'))
        ->assertForbidden();
});
