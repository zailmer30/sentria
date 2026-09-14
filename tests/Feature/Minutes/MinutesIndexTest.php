<?php

use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\Minutes;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);
});

function minutesActor(): User
{
    return User::factory()->create([
        'email' => 'secretariat-minutes-index@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);
}

it('lists minutes with summary figures', function (): void {
    $actor = minutesActor();

    Minutes::factory()->create();
    Minutes::factory()->aiDrafted()->create();
    Minutes::factory()->finalized()->create();

    $this->actingAs($actor)
        ->get(route('minutes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Minutes/Index')
            ->has('minutes.data', 3)
            ->where('summary.matching', 3)
            ->where('summary.final', 1)
            ->where('can.create', true)
            ->has('filters'));
});

it('filters minutes by search and phase', function (): void {
    $actor = minutesActor();

    Minutes::factory()->create([
        'session_id' => LegislativeSession::factory()->create([
            'title' => 'Budget sitting',
            'session_number' => 'RS-880',
        ]),
    ]);
    Minutes::factory()->finalized()->create();

    $this->actingAs($actor)
        ->get(route('minutes.index', ['search' => 'Budget']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('minutes.data', 1)
            ->where('minutes.data.0.session.title', 'Budget sitting'));

    $this->actingAs($actor)
        ->get(route('minutes.index', ['phase' => 'final']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('minutes.data', 1)
            ->where('filters.phase', 'final'));
});
