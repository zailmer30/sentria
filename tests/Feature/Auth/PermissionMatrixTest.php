<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function userWithRole(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-perm@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('grants system administrators every seeded permission', function (): void {
    $admin = userWithRole(UserRole::SystemAdministrator);

    expect($admin->can('settings.update'))->toBeTrue()
        ->and($admin->can('backup.restore'))->toBeTrue()
        ->and($admin->can('voting.cast'))->toBeTrue();
});

it('denies public users internal document abilities', function (): void {
    $public = userWithRole(UserRole::PublicUser);

    expect($public->can('portal.view'))->toBeTrue()
        ->and($public->can('documents.viewAny'))->toBeFalse()
        ->and($public->can('sessions.start'))->toBeFalse()
        ->and($public->can('voting.cast'))->toBeFalse();
});

it('allows board members to vote but not manage users', function (): void {
    $member = userWithRole(UserRole::BoardMember);

    expect($member->can('voting.cast'))->toBeTrue()
        ->and($member->can('session-chat.use'))->toBeTrue()
        ->and($member->can('documents.create'))->toBeTrue()
        ->and($member->can('users.create'))->toBeFalse()
        ->and($member->can('settings.update'))->toBeFalse();
});

it('allows secretariat to register documents but not cast votes', function (): void {
    $secretariat = userWithRole(UserRole::Secretariat);

    expect($secretariat->can('documents.register'))->toBeTrue()
        ->and($secretariat->can('agenda.manage'))->toBeTrue()
        ->and($secretariat->can('settings.chamber'))->toBeTrue()
        ->and($secretariat->can('settings.update'))->toBeFalse()
        ->and($secretariat->can('voting.cast'))->toBeFalse()
        ->and($secretariat->can('sessions.start'))->toBeTrue()
        ->and($secretariat->can('sessions.suspend'))->toBeTrue()
        ->and($secretariat->can('sessions.adjourn'))->toBeTrue()
        ->and($secretariat->can('session-chat.use'))->toBeTrue();
});

it('allows the presiding officer to adjourn and rule, but not start sittings', function (): void {
    $presiding = userWithRole(UserRole::PresidingOfficer);

    expect($presiding->can('sessions.start'))->toBeFalse()
        ->and($presiding->can('sessions.adjourn'))->toBeTrue()
        ->and($presiding->can('motions.rule'))->toBeTrue()
        ->and($presiding->can('roles.manage'))->toBeFalse();
});
