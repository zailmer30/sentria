<?php

use App\Enums\UserRole;
use App\Models\ChamberChannel;
use App\Models\LegislativeSession;
use App\Models\Transcript;
use App\Models\User;
use App\States\Session\InSession;
use App\States\Session\Scheduled;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);

    Storage::fake('local');
});

it('snapshots the channel map onto a chamber transcript when a session starts', function (): void {
    $secretariat = User::factory()->create([
        'email' => 'sec-chamber-start@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);

    $member = User::factory()->seatedMember()->create([
        'email' => 'mapped-member@sentria.test',
        'display_name' => 'Hon. Mapped',
        'is_active' => true,
    ]);

    ChamberChannel::factory()->create([
        'channel_index' => 3,
        'user_id' => $member->getKey(),
        'label' => 'Seat 3',
        'is_active' => true,
    ]);

    $session = LegislativeSession::factory()->create([
        'status' => Scheduled::$name,
        'recording_enabled' => true,
        'capture_mode' => 'per_seat',
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.start', $session))
        ->assertRedirect();

    $session->refresh();
    expect($session->status)->toBeInstanceOf(InSession::class);

    $transcript = Transcript::query()
        ->where('session_id', $session->getKey())
        ->where('source', 'chamber_channels')
        ->first();

    expect($transcript)->not->toBeNull()
        ->and($transcript->language)->toBe('und')
        ->and($transcript->channel_map_snapshot)->toBeArray()
        ->and($transcript->channel_map_snapshot[0]['channel_index'])->toBe(3)
        ->and($transcript->channel_map_snapshot[0]['user_id'])->toBe($member->getKey());
});

it('lets secretariat disable chamber recording', function (): void {
    $secretariat = User::factory()->create([
        'email' => 'sec-recording@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);

    $session = LegislativeSession::factory()->inSession()->create([
        'recording_enabled' => true,
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.recording.update', $session), [
            'recording_enabled' => false,
        ])
        ->assertRedirect();

    expect($session->fresh()->recording_enabled)->toBeFalse();
});

it('lets secretariat switch a sitting to mixer mix', function (): void {
    $secretariat = User::factory()->create([
        'email' => 'sec-feed@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);

    $session = LegislativeSession::factory()->inSession()->create([
        'recording_enabled' => true,
        'capture_mode' => 'per_seat',
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.recording.update', $session), [
            'capture_mode' => 'mixer_mix',
        ])
        ->assertRedirect();

    expect($session->fresh()->capture_mode->value)->toBe('mixer_mix');
});

it('exposes recording controls on the secretariat console', function (): void {
    $secretariat = User::factory()->create([
        'email' => 'sec-console-recording@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);

    $session = LegislativeSession::factory()->inSession()->create([
        'recording_enabled' => false,
        'capture_mode' => 'mixer_mix',
    ]);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Secretariat')
            ->where('can.manage_recording', true)
            ->where('session.recording_enabled', false)
            ->where('session.capture_mode', 'mixer_mix'));
});
