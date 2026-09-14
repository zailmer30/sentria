<?php

use App\Enums\UserRole;
use App\Models\Committee;
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

function committeeCreateActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-cmte-create@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('lets the secretariat create a committee', function (): void {
    $secretariat = committeeCreateActor(UserRole::Secretariat);

    $this->actingAs($secretariat)
        ->get(route('committees.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Committees/Create'));

    $this->actingAs($secretariat)
        ->post(route('committees.store'), [
            'name' => 'Committee on Youth and Sports',
            'code' => 'YOUTH',
            'type' => 'standing',
            'mandate' => 'Reviews measures on youth development and sports.',
            'established_on' => '2026-01-15',
            'is_active' => true,
        ])
        ->assertRedirect();

    $committee = Committee::query()->where('code', 'YOUTH')->firstOrFail();

    expect($committee->name)->toBe('Committee on Youth and Sports')
        ->and($committee->slug)->toBe('committee-on-youth-and-sports')
        ->and($committee->type)->toBe('standing')
        ->and($committee->is_active)->toBeTrue();
});

it('forbids a board member from creating a committee', function (): void {
    $member = committeeCreateActor(UserRole::BoardMember);

    $this->actingAs($member)
        ->get(route('committees.create'))
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('committees.store'), [
            'name' => 'Committee on Youth and Sports',
            'type' => 'standing',
            'is_active' => true,
        ])
        ->assertForbidden();
});
