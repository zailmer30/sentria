<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\CommitteeReferral;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\States\Document\CommitteeReferral as CommitteeReferralState;
use App\States\Document\CommitteeReview;
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

function hearingAgendaActor(string $suffix): User
{
    return User::factory()->create([
        'email' => "secretariat-hearing-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);
}

function referredMeasure(string $title, string $reference, Committee $committee, string $referralStatus = 'pending'): Document
{
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'title' => $title,
        'reference_number' => $reference,
        'status' => $referralStatus === 'in-review' ? CommitteeReview::$name : CommitteeReferralState::$name,
        'committee_id' => $committee->getKey(),
    ]);

    CommitteeReferral::factory()->create([
        'document_id' => $document->getKey(),
        'committee_id' => $committee->getKey(),
        'status' => $referralStatus,
        'completed_at' => null,
    ]);

    return $document;
}

it('adds every open referral when preparing a committee hearing agenda', function (): void {
    $secretariat = hearingAgendaActor('prepare');
    $sample = Committee::factory()->create(['name' => 'Committee on Sample']);
    $rules = Committee::factory()->create(['name' => 'Committee on Rules']);

    $sampleOrdinance = referredMeasure('Sample Ordinance', 'PO-2026-00001', $sample);
    $rulesMeasure = referredMeasure('A resolution on rules', 'PR-2026-00004', $rules, 'in-review');
    $closed = referredMeasure('Already reported', 'PO-2026-00099', $sample, 'pending');
    $closed->referrals()->update([
        'status' => 'reported',
        'completed_at' => now()->subDay(),
    ]);

    $session = LegislativeSession::factory()->create([
        'type' => 'committee-hearing',
        'status' => 'draft',
        'scheduled_start_at' => '2026-04-21 09:00:00',
        'secretary_id' => $secretariat->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.prepare-agenda', $session))
        ->assertRedirect();

    $items = $session->fresh()->agendaItems()->orderBy('position')->get();
    $heading = $items->firstWhere('category', 'referred-measures');
    $linkedIds = $items->pluck('document_id')->filter()->values();

    expect($heading)->not->toBeNull()
        ->and($items->whereNull('parent_id')->pluck('title')->values()->all())->toBe([
            'Call to Order',
            'Invocation',
            'Roll Call',
            'Committee Concerns',
            'Other Matters',
            'Adjournment',
        ])
        ->and($linkedIds->all())->toContain($sampleOrdinance->getKey(), $rulesMeasure->getKey())
        ->and($linkedIds->all())->not->toContain($closed->getKey())
        ->and($heading?->title)->toBe('Committee Concerns')
        ->and($items->firstWhere('document_id', $sampleOrdinance->getKey())?->parent_id)->toBe($heading?->getKey())
        ->and($items->firstWhere('document_id', $sampleOrdinance->getKey())?->item_number)->toBe('4.1')
        ->and($items->firstWhere('document_id', $rulesMeasure->getKey())?->parent_id)->toBe($heading?->getKey())
        ->and($sampleOrdinance->fresh()->status)->toBeInstanceOf(CommitteeReferralState::class);

    $this->actingAs($secretariat)
        ->get(route('sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Show')
            ->where('calendar_docket', function ($docket) use ($sampleOrdinance, $sample): bool {
                $row = collect($docket)->firstWhere('document_id', $sampleOrdinance->getKey());

                return is_array($row)
                    && $row['reference_number'] === 'PO-2026-00001'
                    && $row['title'] === 'Sample Ordinance'
                    && ($row['document']['committee'] ?? null) === $sample->name;
            }));
});

it('leaves a same-sitting referral off the committee hearing docket', function (): void {
    $secretariat = hearingAgendaActor('waived');
    $committee = Committee::factory()->create();
    $document = referredMeasure('Taken up on the floor', 'PO-2026-00008', $committee);
    $document->referrals()->update(['hearing_waived' => true]);

    $session = LegislativeSession::factory()->create([
        'type' => 'committee-hearing',
        'status' => 'draft',
        'scheduled_start_at' => '2026-04-23 09:00:00',
        'secretary_id' => $secretariat->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.prepare-agenda', $session))
        ->assertRedirect();

    expect($session->fresh()->agendaItems()->where('document_id', $document->getKey())->exists())->toBeFalse();
});

it('does not pull open referrals onto a regular sitting', function (): void {
    $secretariat = hearingAgendaActor('regular');
    $committee = Committee::factory()->create();
    $document = referredMeasure('Sample Ordinance', 'PO-2026-00001', $committee);

    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => 'draft',
        'scheduled_start_at' => '2026-04-22 09:00:00',
        'secretary_id' => $secretariat->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.prepare-agenda', $session))
        ->assertRedirect();

    expect($session->agendaItems()->where('document_id', $document->getKey())->exists())->toBeFalse()
        ->and($session->agendaItems()->where('category', 'referred-measures')->exists())->toBeFalse()
        ->and($session->agendaItems()->count())->toBe(17);
});
