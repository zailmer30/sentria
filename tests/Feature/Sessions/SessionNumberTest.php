<?php

use App\Enums\SessionType;
use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\User;
use Carbon\CarbonImmutable;
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

    CarbonImmutable::setTestNow('2026-08-31 12:00:00');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

function sessionNumberActor(): User
{
    return User::factory()->create([
        'email' => 'secretariat-session-number@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);
}

it('previews the next tagged session number and title on the create form', function (): void {
    $actor = sessionNumberActor();

    LegislativeSession::factory()->create([
        'type' => SessionType::Regular->value,
        'session_number' => 'RS-2026-00001',
        'legislative_year' => 2026,
    ]);

    $this->actingAs($actor)
        ->get(route('sessions.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Create')
            ->has('sessionTypes.0', fn (Assert $type) => $type
                ->where('value', SessionType::Regular->value)
                ->where('tag', 'RS')
                ->where('next_session_number', 'RS-2026-00002')
                ->where('next_title', '2nd Regular Session')
                ->where('next_sequence', 2)
                ->etc()));
});

it('assigns the next tagged number and ordinal title when a session is opened', function (): void {
    $actor = sessionNumberActor();

    LegislativeSession::factory()->create([
        'type' => SessionType::Regular->value,
        'session_number' => 'RS-2026-00001',
        'legislative_year' => 2026,
    ]);

    $this->actingAs($actor)
        ->post(route('sessions.store'), [
            'type' => SessionType::Regular->value,
            'legislative_year' => 2026,
            'venue' => 'Session Hall',
        ])
        ->assertRedirect();

    $session = LegislativeSession::query()->where('venue', 'Session Hall')->firstOrFail();

    expect($session->session_number)->toBe('RS-2026-00002')
        ->and($session->title)->toBe('2nd Regular Session');
});

it('keeps special sessions on their own yearly series', function (): void {
    $actor = sessionNumberActor();

    LegislativeSession::factory()->create([
        'type' => SessionType::Regular->value,
        'session_number' => 'RS-2026-00012',
        'legislative_year' => 2026,
    ]);

    $this->actingAs($actor)
        ->post(route('sessions.store'), [
            'type' => SessionType::Special->value,
            'legislative_year' => 2026,
        ])
        ->assertRedirect();

    $session = LegislativeSession::query()->where('type', SessionType::Special->value)->firstOrFail();

    expect($session->session_number)->toBe('SS-2026-00001')
        ->and($session->title)->toBe('1st Special Session');
});
