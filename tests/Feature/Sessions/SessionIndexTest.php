<?php

use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\User;
use App\States\Session\AgendaPrepared;
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

function indexActor(): User
{
    return User::factory()->create([
        'email' => 'secretariat-index@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);
}

it('lists sessions with summary figures and finding-aid props', function (): void {
    $actor = indexActor();

    LegislativeSession::factory()->inSession()->create(['title' => 'Live sitting']);
    LegislativeSession::factory()->scheduled()->create(['title' => 'Next sitting']);
    LegislativeSession::factory()->create(['title' => 'Unscheduled draft', 'status' => 'draft']);

    $this->actingAs($actor)
        ->get(route('sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Index')
            ->has('sessions.data', 3)
            ->where('summary.live', 1)
            ->where('summary.upcoming', 1)
            ->where('summary.drafts', 1)
            ->where('summary.matching', 3)
            ->where('can.create', true)
            ->has('sessionTypes')
            ->has('filters'));
});

it('counts agenda-prepared sittings with drafts until they are scheduled', function (): void {
    $actor = indexActor();

    LegislativeSession::factory()->create([
        'title' => 'Agenda ready',
        'status' => AgendaPrepared::$name,
    ]);
    LegislativeSession::factory()->scheduled()->create(['title' => 'On the calendar']);

    $this->actingAs($actor)
        ->get(route('sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.upcoming', 1)
            ->where('summary.drafts', 1));

    $this->actingAs($actor)
        ->get(route('sessions.index', ['phase' => 'draft']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions.data', 1)
            ->where('sessions.data.0.title', 'Agenda ready'));
});

it('filters the register by search, type, and phase', function (): void {
    $actor = indexActor();

    LegislativeSession::factory()->inSession()->create([
        'title' => 'Budget hearing',
        'session_number' => 'CH-010',
        'type' => 'committee-hearing',
    ]);
    LegislativeSession::factory()->scheduled()->create([
        'title' => 'Regular calendar',
        'session_number' => 'RS-200',
        'type' => 'regular',
    ]);

    $this->actingAs($actor)
        ->get(route('sessions.index', ['search' => 'Budget']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions.data', 1)
            ->where('sessions.data.0.title', 'Budget hearing')
            ->where('filters.search', 'Budget'));

    $this->actingAs($actor)
        ->get(route('sessions.index', ['phase' => 'live']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions.data', 1)
            ->where('sessions.data.0.session_number', 'CH-010')
            ->where('filters.phase', 'live'));

    $this->actingAs($actor)
        ->get(route('sessions.index', ['type' => 'regular']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions.data', 1)
            ->where('sessions.data.0.type', 'regular'));
});
