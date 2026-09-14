<?php

use App\Enums\UserRole;
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

function profileActor(UserRole $role = UserRole::BoardMember): User
{
    return User::factory()->create([
        'email' => "{$role->value}-profile@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'first_name' => 'Rafael',
        'last_name' => 'Dizon',
        'display_name' => 'Rafael Dizon',
        'phone' => null,
        'locale' => 'en',
        'position_title' => 'Board Member',
        'district' => 'District 1',
        'employee_number' => 'BM-001',
        'is_seated_member' => true,
    ])->assignRole($role->value);
}

it('redirects guests away from the profile page', function (): void {
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
});

it('lets a board member open their profile page', function (): void {
    $member = profileActor();

    $this->actingAs($member)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')
            ->where('user.email', $member->email)
            ->where('user.display_name', 'Rafael Dizon')
            ->where('user.position_title', 'Board Member')
            ->has('user.roles', 1));
});

it('lets a board member update their details', function (): void {
    $member = profileActor();

    $this->actingAs($member)
        ->put(route('profile.update'), [
            'first_name' => 'Rafael',
            'middle_name' => 'Santos',
            'last_name' => 'Dizon',
            'display_name' => 'Hon. Rafael Dizon',
            'email' => $member->email,
            'phone' => '+639171234567',
            'locale' => 'fil',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('success', 'profile.updated');

    $member->refresh();

    expect($member->middle_name)->toBe('Santos')
        ->and($member->display_name)->toBe('Hon. Rafael Dizon')
        ->and($member->phone)->toBe('+639171234567')
        ->and($member->locale)->toBe('fil');
});

it('stores and removes a profile photo from the profile page', function (): void {
    Storage::fake('public');

    $member = profileActor();
    $photo = UploadedFile::fake()->image('rafael.jpg', 240, 240);

    $this->actingAs($member)
        ->post(route('profile.update'), [
            '_method' => 'PUT',
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => $member->email,
            'locale' => 'en',
            'avatar' => $photo,
        ])
        ->assertRedirect(route('profile.edit'));

    $member->refresh();
    expect($member->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists($member->avatar_path);

    $path = $member->avatar_path;

    $this->actingAs($member)
        ->put(route('profile.update'), [
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => $member->email,
            'locale' => 'en',
            'remove_avatar' => true,
        ])
        ->assertRedirect(route('profile.edit'));

    $member->refresh();
    expect($member->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('lets a board member change their password with the current password', function (): void {
    $member = profileActor();

    $this->actingAs($member)
        ->put(route('profile.password'), [
            'current_password' => 'password',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('success', 'profile.password_updated');

    $member->refresh();
    expect(Hash::check('Password1!', $member->password))->toBeTrue();
});

it('rejects a password change without the current password', function (): void {
    $member = profileActor();

    $this->actingAs($member)
        ->from(route('profile.edit'))
        ->put(route('profile.password'), [
            'current_password' => 'wrong-password',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('current_password');

    $member->refresh();
    expect(Hash::check('password', $member->password))->toBeTrue();
});

it('does not accept role or standing fields on the profile update', function (): void {
    $member = profileActor();
    $originalTitle = $member->position_title;

    $this->actingAs($member)
        ->put(route('profile.update'), [
            'first_name' => 'Rafael',
            'last_name' => 'Dizon',
            'email' => $member->email,
            'locale' => 'en',
            'is_active' => false,
            'is_seated_member' => false,
            'position_title' => 'Hacked Title',
            'role_ids' => ['01fake'],
        ])
        ->assertRedirect(route('profile.edit'));

    $member->refresh();

    expect($member->is_active)->toBeTrue()
        ->and($member->is_seated_member)->toBeTrue()
        ->and($member->position_title)->toBe($originalTitle)
        ->and($member->hasRole(UserRole::BoardMember->value))->toBeTrue()
        ->and($member->roles)->toHaveCount(1);
});
