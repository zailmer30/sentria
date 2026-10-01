<?php

use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Services\AI\LegislativeMinutesGenerator;
use App\States\Session\Finalized;
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

    config(['sentria.ai.api_key' => null]);
});

function floorMinutesActor(UserRole $role, string $suffix): User
{
    return User::factory()->create([
        'email' => "{$role->value}-floor-minutes-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole($role->value);
}

it('exposes secretariat minutes on the floor console', function (): void {
    $secretariat = floorMinutesActor(UserRole::Secretariat, 'expose');
    $session = LegislativeSession::factory()->inSession()->create([
        'secretariat_minutes' => 'Quorum was declared at the opening.',
    ]);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Secretariat')
            ->where('workspace', 'console')
            ->where('session.secretariat_minutes', 'Quorum was declared at the opening.')
            ->where('can.record_minutes', true));
});

it('opens the recording floor view for the secretariat', function (): void {
    $secretariat = floorMinutesActor(UserRole::Secretariat, 'recording');
    $session = LegislativeSession::factory()->inSession()->create();

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.recording', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Secretariat')
            ->where('workspace', 'recording')
            ->where('can.view_transcript', true));
});

it('opens the minutes floor view for the secretariat', function (): void {
    $secretariat = floorMinutesActor(UserRole::Secretariat, 'view');
    $session = LegislativeSession::factory()->inSession()->create([
        'secretariat_minutes' => 'Quorum was declared at the opening.',
    ]);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.minutes', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Secretariat')
            ->where('workspace', 'minutes')
            ->where('session.secretariat_minutes', 'Quorum was declared at the opening.')
            ->where('can.record_minutes', true));
});

it('lets the secretariat save live minutes from the floor', function (): void {
    $secretariat = floorMinutesActor(UserRole::Secretariat, 'save');
    $session = LegislativeSession::factory()->inSession()->create();

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.minutes', $session))
        ->put(route('sessions.floor.minutes.update', $session), [
            'secretariat_minutes' => "The chair declared a quorum.\nHon. Cruz moved to approve the minutes.",
        ])
        ->assertRedirect(route('sessions.floor.minutes', $session));

    expect($session->fresh()->secretariat_minutes)
        ->toBe("The chair declared a quorum.\nHon. Cruz moved to approve the minutes.");
});

it('forbids board members from recording secretariat minutes', function (): void {
    $member = floorMinutesActor(UserRole::BoardMember, 'blocked');
    $session = LegislativeSession::factory()->inSession()->create();

    $this->actingAs($member)
        ->put(route('sessions.floor.minutes.update', $session), [
            'secretariat_minutes' => 'Hijacked notes.',
        ])
        ->assertForbidden();

    expect($session->fresh()->secretariat_minutes)->toBeNull();
});

it('refuses minutes notes after the sitting is finalized', function (): void {
    $secretariat = floorMinutesActor(UserRole::Secretariat, 'final');
    $session = LegislativeSession::factory()->create([
        'status' => Finalized::$name,
        'secretariat_minutes' => 'Locked text.',
    ]);

    $this->actingAs($secretariat)
        ->put(route('sessions.floor.minutes.update', $session), [
            'secretariat_minutes' => 'Should not save.',
        ])
        ->assertForbidden();

    expect($session->fresh()->secretariat_minutes)->toBe('Locked text.');
});

it('folds floor minutes into the generated draft', function (): void {
    $secretariat = floorMinutesActor(UserRole::Secretariat, 'draft');
    $session = LegislativeSession::factory()->adjourned()->create([
        'secretary_id' => $secretariat->getKey(),
        'secretariat_minutes' => 'The gallery was reminded to remain silent during voting.',
    ]);

    $minutes = app(LegislativeMinutesGenerator::class)->draftFromSession($secretariat, $session);

    expect((string) $minutes->content)
        ->toContain(LegislativeMinutesGenerator::SECRETARIAT_MINUTES_HEADING)
        ->toContain('The gallery was reminded to remain silent during voting.');
});

it('merges later floor minutes into an unedited AI draft', function (): void {
    $secretariat = floorMinutesActor(UserRole::Secretariat, 'merge');
    $session = LegislativeSession::factory()->adjourned()->create([
        'secretary_id' => $secretariat->getKey(),
    ]);

    $minutes = app(LegislativeMinutesGenerator::class)->draftFromSession($secretariat, $session);
    expect((string) $minutes->content)->not->toContain('Members observed a moment of silence.');

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->put(route('sessions.floor.minutes.update', $session), [
            'secretariat_minutes' => 'Members observed a moment of silence.',
        ])
        ->assertRedirect();

    expect((string) $minutes->fresh()->content)
        ->toContain(LegislativeMinutesGenerator::SECRETARIAT_MINUTES_HEADING)
        ->toContain('Members observed a moment of silence.')
        ->and((string) $minutes->fresh()->ai_draft)
        ->toContain('Members observed a moment of silence.');
});

it('does not overwrite human-edited minutes when floor notes are saved', function (): void {
    $secretariat = floorMinutesActor(UserRole::Secretariat, 'human');
    $session = LegislativeSession::factory()->adjourned()->create([
        'secretary_id' => $secretariat->getKey(),
    ]);

    $minutes = app(LegislativeMinutesGenerator::class)->draftFromSession($secretariat, $session);
    $minutes->update(['content' => 'Human-edited official record.']);

    $this->actingAs($secretariat)
        ->put(route('sessions.floor.minutes.update', $session), [
            'secretariat_minutes' => 'Typed after the official record was edited.',
        ])
        ->assertRedirect();

    expect($minutes->fresh()->content)->toBe('Human-edited official record.')
        ->and($session->fresh()->secretariat_minutes)->toBe('Typed after the official record was edited.');
});
