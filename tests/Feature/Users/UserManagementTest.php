<?php

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function usersActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-users@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('lets a system administrator list and create users', function (): void {
    $admin = usersActor(UserRole::SystemAdministrator);
    $role = Role::query()->where('name', UserRole::BoardMember->value)->firstOrFail();

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users.data')
            ->where('can.create', true));

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana.reyes@sentria.test',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'locale' => 'en',
            'is_active' => true,
            'is_seated_member' => false,
            'role_ids' => [$role->getKey()],
        ])
        ->assertRedirect();

    $created = User::query()->where('email', 'ana.reyes@sentria.test')->firstOrFail();

    expect($created->display_name)->toBe('Ana Reyes')
        ->and($created->hasRole(UserRole::BoardMember->value))->toBeTrue();
});

it('lets secretariat view users but not create them', function (): void {
    $secretariat = usersActor(UserRole::Secretariat);
    $target = usersActor(UserRole::BoardMember);

    $this->actingAs($secretariat)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->where('can.create', false));

    $this->actingAs($secretariat)
        ->get(route('users.show', $target))
        ->assertOk();

    $this->actingAs($secretariat)
        ->get(route('users.create'))
        ->assertForbidden();

    $this->actingAs($secretariat)
        ->post(route('users.store'), [
            'first_name' => 'Blocked',
            'last_name' => 'User',
            'email' => 'blocked@sentria.test',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'locale' => 'en',
            'is_active' => true,
            'is_seated_member' => false,
        ])
        ->assertForbidden();
});

it('forbids a board member from viewing users', function (): void {
    $member = usersActor(UserRole::BoardMember);

    $this->actingAs($member)
        ->get(route('users.index'))
        ->assertForbidden();
});

it('soft-deletes a user and blocks self-delete', function (): void {
    $admin = usersActor(UserRole::SystemAdministrator);
    $target = usersActor(UserRole::BoardMember);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $admin))
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target))
        ->assertRedirect(route('users.index'));

    expect(User::withTrashed()->find($target->getKey())?->trashed())->toBeTrue()
        ->and(User::query()->find($target->getKey()))->toBeNull();
});

it('updates a user profile and roles', function (): void {
    $admin = usersActor(UserRole::SystemAdministrator);
    $target = usersActor(UserRole::BoardMember);
    $secretariatRole = Role::query()->where('name', UserRole::Secretariat->value)->firstOrFail();

    $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'first_name' => 'Updated',
            'last_name' => 'Member',
            'email' => $target->email,
            'locale' => 'fil',
            'is_active' => false,
            'is_seated_member' => true,
            'role_ids' => [$secretariatRole->getKey()],
        ])
        ->assertRedirect(route('users.show', $target));

    $target->refresh();

    expect($target->first_name)->toBe('Updated')
        ->and($target->locale)->toBe('fil')
        ->and($target->is_active)->toBeFalse()
        ->and($target->hasRole(UserRole::Secretariat->value))->toBeTrue()
        ->and($target->hasRole(UserRole::BoardMember->value))->toBeFalse();
});

it('stores a profile photo when creating a user', function (): void {
    Storage::fake('public');

    $admin = usersActor(UserRole::SystemAdministrator);
    $role = Role::query()->where('name', UserRole::BoardMember->value)->firstOrFail();
    $photo = UploadedFile::fake()->image('member.jpg', 240, 240);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana.reyes@sentria.test',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'locale' => 'en',
            'is_active' => true,
            'is_seated_member' => true,
            'role_ids' => [$role->getKey()],
            'avatar' => $photo,
        ])
        ->assertRedirect();

    $created = User::query()->where('email', 'ana.reyes@sentria.test')->firstOrFail();

    expect($created->avatar_path)->not->toBeNull()
        ->and($created->avatarUrl())->toContain('/storage/avatars/');

    Storage::disk('public')->assertExists($created->avatar_path);
});

it('replaces and removes a profile photo', function (): void {
    Storage::fake('public');

    $admin = usersActor(UserRole::SystemAdministrator);
    $target = usersActor(UserRole::BoardMember);
    $original = UploadedFile::fake()->image('original.jpg', 200, 200);

    $this->actingAs($admin)
        ->post(route('users.update', $target), [
            '_method' => 'PUT',
            'first_name' => $target->first_name,
            'last_name' => $target->last_name,
            'email' => $target->email,
            'locale' => 'en',
            'is_active' => true,
            'is_seated_member' => true,
            'avatar' => $original,
        ])
        ->assertRedirect(route('users.show', $target));

    $target->refresh();
    $firstPath = $target->avatar_path;
    expect($firstPath)->not->toBeNull();
    Storage::disk('public')->assertExists($firstPath);

    $replacement = UploadedFile::fake()->image('replacement.png', 200, 200);

    $this->actingAs($admin)
        ->post(route('users.update', $target), [
            '_method' => 'PUT',
            'first_name' => $target->first_name,
            'last_name' => $target->last_name,
            'email' => $target->email,
            'locale' => 'en',
            'is_active' => true,
            'is_seated_member' => true,
            'avatar' => $replacement,
        ])
        ->assertRedirect(route('users.show', $target));

    $target->refresh();
    expect($target->avatar_path)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($target->avatar_path);

    $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'first_name' => $target->first_name,
            'last_name' => $target->last_name,
            'email' => $target->email,
            'locale' => 'en',
            'is_active' => true,
            'is_seated_member' => true,
            'remove_avatar' => true,
        ])
        ->assertRedirect(route('users.show', $target));

    $target->refresh();
    expect($target->avatar_path)->toBeNull();
});
