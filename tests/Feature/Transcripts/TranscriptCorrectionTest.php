<?php

use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\Transcript;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);
});

function correctionFixtures(): array
{
    $secretariat = User::factory()->create([
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);

    $member = User::factory()->create([
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole(UserRole::BoardMember->value);

    $session = LegislativeSession::factory()->create();

    $transcript = Transcript::factory()->create([
        'session_id' => $session->getKey(),
        'segments' => [
            [
                'index' => 0,
                'start' => 0.0,
                'end' => 5.0,
                'speaker' => 'Speaker 1',
                'text' => 'Original segment text.',
                'confidence' => 0.9,
            ],
        ],
        'full_text' => 'Original segment text.',
    ]);

    return compact('secretariat', 'member', 'session', 'transcript');
}

it('allows authorized users to correct a transcript segment', function (): void {
    ['secretariat' => $secretariat, 'session' => $session, 'transcript' => $transcript] = correctionFixtures();

    $this->actingAs($secretariat)
        ->patchJson(route('sessions.transcript.correct', [
            'session' => $session,
            'transcript' => $transcript,
            'segmentIndex' => 0,
        ]), [
            'text' => 'Corrected official wording.',
        ])
        ->assertOk()
        ->assertJsonPath('transcript.segments.0.text', 'Corrected official wording.');

    expect($transcript->fresh()->full_text)->toBe('Corrected official wording.');
});

it('lets secretariat assign a seated member or gallery to a segment', function (): void {
    ['secretariat' => $secretariat, 'member' => $member, 'session' => $session, 'transcript' => $transcript] = correctionFixtures();

    $this->actingAs($secretariat)
        ->patchJson(route('sessions.transcript.correct', [
            'session' => $session,
            'transcript' => $transcript,
            'segmentIndex' => 0,
        ]), [
            'text' => 'Original segment text.',
            'speaker_id' => $member->getKey(),
        ])
        ->assertOk()
        ->assertJsonPath('transcript.segments.0.speaker', $member->display_name)
        ->assertJsonPath('transcript.segments.0.speaker_id', $member->getKey())
        ->assertJsonPath('transcript.segments.0.attributed', true);

    $this->actingAs($secretariat)
        ->patchJson(route('sessions.transcript.correct', [
            'session' => $session,
            'transcript' => $transcript,
            'segmentIndex' => 0,
        ]), [
            'text' => 'Original segment text.',
            'gallery' => true,
        ])
        ->assertOk()
        ->assertJsonPath('transcript.segments.0.speaker_id', null)
        ->assertJsonPath('transcript.segments.0.attributed', true);

    expect($transcript->fresh()->segments[0]['speaker'])->toBe(__('transcripts.gallery'));
});

it('forbids unauthorized users from correcting transcript segments', function (): void {
    ['member' => $member, 'session' => $session, 'transcript' => $transcript] = correctionFixtures();

    $this->actingAs($member)
        ->patchJson(route('sessions.transcript.correct', [
            'session' => $session,
            'transcript' => $transcript,
            'segmentIndex' => 0,
        ]), [
            'text' => 'Should not apply.',
        ])
        ->assertForbidden();
});
