<?php

use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\Transcript;
use App\Models\TranscriptSegmentEdit;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Inertia\Testing\AssertableInertia as Assert;

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
                'original_text' => 'Original segment text.',
                'original_speaker' => 'Speaker 1',
                'original_speaker_id' => null,
                'original_attributed' => true,
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
        ->assertJsonPath('transcript.segments.0.text', 'Corrected official wording.')
        ->assertJsonPath('transcript.segments.0.original_text', 'Original segment text.')
        ->assertJsonPath('transcript.segments.0.is_edited', true);

    $fresh = $transcript->fresh();

    expect($fresh->full_text)->toBe('Corrected official wording.')
        ->and($fresh->segments[0]['original_text'])->toBe('Original segment text.')
        ->and($fresh->segments[0]['text'])->toBe('Corrected official wording.');

    expect(TranscriptSegmentEdit::query()->where('transcript_id', $transcript->getKey())->get())
        ->toHaveCount(1)
        ->and(TranscriptSegmentEdit::query()->first()?->field)->toBe('text')
        ->and(TranscriptSegmentEdit::query()->first()?->old_value)->toBe('Original segment text.')
        ->and(TranscriptSegmentEdit::query()->first()?->new_value)->toBe('Corrected official wording.');
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

    $edits = TranscriptSegmentEdit::query()
        ->where('transcript_id', $transcript->getKey())
        ->orderBy('created_at')
        ->get();

    expect($edits)->toHaveCount(2)
        ->and($edits->every(fn (TranscriptSegmentEdit $edit): bool => $edit->field === 'speaker'))->toBeTrue()
        ->and(TranscriptSegmentEdit::query()->where('field', 'text')->count())->toBe(0);
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

it('keeps the original STT text after a second correction', function (): void {
    ['secretariat' => $secretariat, 'session' => $session, 'transcript' => $transcript] = correctionFixtures();

    $this->actingAs($secretariat)
        ->patchJson(route('sessions.transcript.correct', [
            'session' => $session,
            'transcript' => $transcript,
            'segmentIndex' => 0,
        ]), [
            'text' => 'First human wording.',
        ])
        ->assertOk();

    $this->actingAs($secretariat)
        ->patchJson(route('sessions.transcript.correct', [
            'session' => $session,
            'transcript' => $transcript,
            'segmentIndex' => 0,
        ]), [
            'text' => 'Second human wording.',
        ])
        ->assertOk()
        ->assertJsonPath('transcript.segments.0.original_text', 'Original segment text.')
        ->assertJsonPath('transcript.segments.0.text', 'Second human wording.');

    expect($transcript->fresh()->segments[0]['original_text'])->toBe('Original segment text.')
        ->and(TranscriptSegmentEdit::query()->where('field', 'text')->count())->toBe(2);
});

it('restores original wording as a new text edit', function (): void {
    ['secretariat' => $secretariat, 'session' => $session, 'transcript' => $transcript] = correctionFixtures();

    $this->actingAs($secretariat)
        ->patchJson(route('sessions.transcript.correct', [
            'session' => $session,
            'transcript' => $transcript,
            'segmentIndex' => 0,
        ]), [
            'text' => 'Over-corrected wording.',
        ])
        ->assertOk();

    $this->actingAs($secretariat)
        ->patchJson(route('sessions.transcript.correct', [
            'session' => $session,
            'transcript' => $transcript,
            'segmentIndex' => 0,
        ]), [
            'text' => 'Original segment text.',
        ])
        ->assertOk()
        ->assertJsonPath('transcript.segments.0.text', 'Original segment text.')
        ->assertJsonPath('transcript.segments.0.original_text', 'Original segment text.')
        ->assertJsonPath('transcript.segments.0.is_edited', false);

    $last = TranscriptSegmentEdit::query()->where('field', 'text')->orderByDesc('id')->first();

    expect($last?->old_value)->toBe('Over-corrected wording.')
        ->and($last?->new_value)->toBe('Original segment text.');
});

it('lists segment edits for correctors and hides originals from viewers', function (): void {
    ['secretariat' => $secretariat, 'member' => $member, 'session' => $session, 'transcript' => $transcript] = correctionFixtures();

    $this->actingAs($secretariat)
        ->patchJson(route('sessions.transcript.correct', [
            'session' => $session,
            'transcript' => $transcript,
            'segmentIndex' => 0,
        ]), [
            'text' => 'Corrected official wording.',
        ])
        ->assertOk();

    $this->actingAs($secretariat)
        ->getJson(route('sessions.transcript.segment-edits', [
            'session' => $session,
            'transcript' => $transcript,
            'segmentIndex' => 0,
        ]))
        ->assertOk()
        ->assertJsonPath('edits.0.field', 'text')
        ->assertJsonPath('edits.0.new_value', 'Corrected official wording.');

    $this->actingAs($member)
        ->getJson(route('sessions.transcript.segment-edits', [
            'session' => $session,
            'transcript' => $transcript,
            'segmentIndex' => 0,
        ]))
        ->assertForbidden();

    $this->actingAs($secretariat)
        ->get(route('sessions.transcript.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Transcript')
            ->where('transcript.segments.0.original_text', 'Original segment text.')
            ->where('transcript.segments.0.text', 'Corrected official wording.')
            ->has('transcript.corrections', 1)
        );

    $this->actingAs($member)
        ->get(route('sessions.transcript.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Transcript')
            ->where('transcript.segments.0.text', 'Corrected official wording.')
            ->missing('transcript.segments.0.original_text')
            ->missing('transcript.corrections')
        );
});
