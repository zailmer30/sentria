<?php

use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\Minutes;
use App\Models\User;
use App\Services\AI\LegislativeMinutesGenerator;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Minutes\AiDraft;
use App\States\Minutes\Approval;
use App\States\Minutes\Edit;
use App\States\Minutes\FinalMinutes;
use App\States\Minutes\Review;
use App\States\Minutes\SecretariatReview;
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

function finalizeActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-minutes-fin@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole($role->value);
}

function minutesReadyForFinalization(User $secretariat, User $presiding): Minutes
{
    $session = LegislativeSession::factory()->adjourned()->create([
        'secretary_id' => $secretariat->getKey(),
        'presiding_officer_id' => $presiding->getKey(),
    ]);

    $minutes = app(LegislativeMinutesGenerator::class)->draftFromSession($secretariat, $session);

    app(GuardedStateTransition::class)->transition($minutes, SecretariatReview::class, $secretariat);
    app(GuardedStateTransition::class)->transition($minutes->fresh(), Edit::class, $secretariat);
    app(GuardedStateTransition::class)->transition($minutes->fresh(), Review::class, $secretariat);
    app(GuardedStateTransition::class)->transition($minutes->fresh(), Approval::class, $presiding);

    return $minutes->fresh();
}

it('denies board members from finalizing minutes', function (): void {
    $secretariat = finalizeActor(UserRole::Secretariat);
    $presiding = finalizeActor(UserRole::PresidingOfficer);
    $member = finalizeActor(UserRole::BoardMember);

    $minutes = minutesReadyForFinalization($secretariat, $presiding);

    test()->actingAs($member)
        ->post(route('minutes.finalize', $minutes))
        ->assertForbidden();

    expect($minutes->fresh()->status)->toBeInstanceOf(Approval::class);
});

it('allows presiding officer to finalize minutes in approval state', function (): void {
    $secretariat = finalizeActor(UserRole::Secretariat);
    $presiding = finalizeActor(UserRole::PresidingOfficer);

    $minutes = minutesReadyForFinalization($secretariat, $presiding);

    test()->actingAs($presiding)
        ->post(route('minutes.finalize', $minutes))
        ->assertRedirect();

    expect($minutes->fresh()->status)->toBeInstanceOf(FinalMinutes::class)
        ->and($minutes->fresh()->finalized_at)->not->toBeNull();
});

it('never lets AI draft generation jump directly to final minutes', function (): void {
    $secretariat = finalizeActor(UserRole::Secretariat);

    $session = LegislativeSession::factory()->adjourned()->create([
        'secretary_id' => $secretariat->getKey(),
    ]);

    $minutes = app(LegislativeMinutesGenerator::class)->draftFromSession($secretariat, $session);

    expect($minutes->status)->toBeInstanceOf(AiDraft::class)
        ->and($minutes->status)->not->toBeInstanceOf(FinalMinutes::class)
        ->and($minutes->finalized_at)->toBeNull();
});

it('denies secretariat from finalizing when they lack minutes.finalize permission', function (): void {
    $secretariat = finalizeActor(UserRole::Secretariat);
    $presiding = finalizeActor(UserRole::PresidingOfficer);

    $minutes = minutesReadyForFinalization($secretariat, $presiding);

    test()->actingAs($secretariat)
        ->post(route('minutes.finalize', $minutes))
        ->assertForbidden();
});
