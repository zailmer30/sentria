<?php

use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\Minutes;
use App\Models\User;
use App\Services\AI\LegislativeMinutesGenerator;
use App\States\Minutes\AiDraft;
use App\States\Session\Adjourned;
use App\States\Session\Draft;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);

    config(['sentria.ai.api_key' => null]);
});

function minutesActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-minutes-adj@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole($role->value);
}

it('generates an AI draft after adjournment with banner metadata and ai-draft status', function (): void {
    $secretariat = minutesActor(UserRole::Secretariat);
    $presiding = minutesActor(UserRole::PresidingOfficer);

    $create = test()->actingAs($secretariat)->post(route('sessions.store'), [
        'session_number' => 'MA-401',
        'title' => 'Minutes Adjournment Session',
        'type' => 'regular',
        'legislative_year' => 2026,
        'venue' => 'Session Hall',
        'seated_member_count' => 12,
        'secretary_id' => $secretariat->getKey(),
        'presiding_officer_id' => $presiding->getKey(),
    ]);
    $create->assertRedirect();

    $session = LegislativeSession::query()->firstOrFail();
    expect($session->status)->toBeInstanceOf(Draft::class);

    test()->actingAs($secretariat)->post(route('sessions.prepare-agenda', $session))->assertRedirect();
    test()->actingAs($secretariat)->post(route('sessions.schedule', $session))->assertRedirect();
    test()->actingAs($secretariat)->post(route('sessions.start', $session))->assertRedirect();
    test()->actingAs($presiding)->post(route('sessions.adjourn', $session))->assertRedirect();

    expect($session->fresh()->status)->toBeInstanceOf(Adjourned::class);

    $minutes = Minutes::query()->where('session_id', $session->getKey())->first();

    expect($minutes)->not->toBeNull()
        ->and($minutes->status)->toBeInstanceOf(AiDraft::class)
        ->and($minutes->ai_draft)->not->toBeNull()
        ->and($minutes->content)->not->toBeNull()
        ->and($minutes->ai_generated_at)->not->toBeNull()
        ->and($minutes->ai_metadata['banner'] ?? null)->toBe(LegislativeMinutesGenerator::DRAFT_BANNER)
        ->and($minutes->ai_draft)->toContain(LegislativeMinutesGenerator::DRAFT_BANNER);

    test()->assertDatabaseHas('audit_logs', [
        'event' => 'ai.minutes.draft',
        'auditable_id' => $minutes->getKey(),
    ]);
});

it('allows secretariat to manually regenerate a draft for an adjourned session', function (): void {
    $secretariat = minutesActor(UserRole::Secretariat);

    $session = LegislativeSession::factory()->adjourned()->create([
        'secretary_id' => $secretariat->getKey(),
    ]);

    $minutes = Minutes::factory()->create([
        'session_id' => $session->getKey(),
        'status' => 'session-completed',
        'prepared_by' => $secretariat->getKey(),
    ]);

    test()->actingAs($secretariat)
        ->post(route('minutes.generate-draft', $minutes))
        ->assertRedirect();

    $minutes = $minutes->fresh();

    expect($minutes->status)->toBeInstanceOf(AiDraft::class)
        ->and($minutes->ai_draft)->not->toBeNull();
});

it('allows regenerating an untouched AI draft', function (): void {
    $secretariat = minutesActor(UserRole::Secretariat);

    $session = LegislativeSession::factory()->adjourned()->create([
        'secretary_id' => $secretariat->getKey(),
    ]);

    $minutes = app(LegislativeMinutesGenerator::class)->draftFromSession($secretariat, $session);

    test()->actingAs($secretariat)
        ->post(route('minutes.generate-draft', $minutes))
        ->assertRedirect()
        ->assertSessionHas('success', 'minutes.draft_generated');

    expect($minutes->fresh()->status)->toBeInstanceOf(AiDraft::class);
});

it('refuses to regenerate when the secretariat has edited the draft', function (): void {
    $secretariat = minutesActor(UserRole::Secretariat);

    $session = LegislativeSession::factory()->adjourned()->create([
        'secretary_id' => $secretariat->getKey(),
    ]);

    $minutes = app(LegislativeMinutesGenerator::class)->draftFromSession($secretariat, $session);
    $minutes->update(['content' => $minutes->content."\n\nSecretariat note."]);

    test()->actingAs($secretariat)
        ->post(route('minutes.generate-draft', $minutes))
        ->assertRedirect()
        ->assertSessionHas('error', 'minutes.regenerate_refused');

    expect($minutes->fresh()->content)->toEndWith('Secretariat note.');
});

it('refuses to regenerate minutes that have left AI-draft', function (): void {
    $secretariat = minutesActor(UserRole::Secretariat);

    $session = LegislativeSession::factory()->adjourned()->create([
        'secretary_id' => $secretariat->getKey(),
    ]);

    $minutes = Minutes::factory()->finalized()->create([
        'session_id' => $session->getKey(),
        'prepared_by' => $secretariat->getKey(),
    ]);
    $original = $minutes->content;

    test()->actingAs($secretariat)
        ->post(route('minutes.generate-draft', $minutes))
        ->assertRedirect()
        ->assertSessionHas('error', 'minutes.regenerate_refused');

    expect($minutes->fresh()->content)->toBe($original);
});
