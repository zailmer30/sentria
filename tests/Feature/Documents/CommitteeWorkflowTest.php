<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\CommitteeReferral;
use App\Models\CommitteeReport;
use App\Models\Document;
use App\Models\User;
use App\States\Document\CommitteeReferral as CommitteeReferralState;
use App\States\Document\CommitteeReview;
use App\States\Document\Registered;
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

function committeeActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-cmte@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('supports referral to report workflow for authorized roles', function (): void {
    $chair = committeeActor(UserRole::CommitteeChair);
    $document = Document::factory()->create();
    $committee = Committee::factory()->create();

    $this->actingAs($chair)
        ->post(route('referrals.store'), [
            'document_id' => $document->getKey(),
            'committee_id' => $committee->getKey(),
            'instructions' => 'Review and report within thirty days.',
            'due_at' => now()->addDays(30)->toDateString(),
        ])
        ->assertRedirect(route('committees.show', $committee));

    $referral = CommitteeReferral::query()
        ->where('document_id', $document->getKey())
        ->where('committee_id', $committee->getKey())
        ->firstOrFail();

    expect($referral->status)->toBe('pending');

    $this->actingAs($chair)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/Show')
            ->where('can.createReport', true)
            ->where('document.open_referral.id', $referral->getKey())
            ->where('document.open_referral.committee_id', $committee->getKey())
            ->where('document.reports', []));

    $this->actingAs($chair)
        ->post(route('reports.store'), [
            'committee_referral_id' => $referral->getKey(),
            'committee_id' => $committee->getKey(),
            'subject_document_id' => $document->getKey(),
            'recommendation' => 'approve',
            'findings' => 'The committee recommends approval as proposed.',
            'report_number' => 'CR-2026-001',
        ])
        ->assertRedirect(route('documents.show', $document));

    $report = CommitteeReport::query()->where('report_number', 'CR-2026-001')->firstOrFail();
    expect($report->status)->toBe('draft');

    $this->actingAs($chair)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.reports.0.id', $report->getKey())
            ->where('document.reports.0.status', 'draft')
            ->where(
                'document.transitions',
                fn (mixed $transitions): bool => collect($transitions)->every(
                    fn (mixed $item): bool => is_array($item) && ($item['to'] ?? null) !== 'committee-report',
                ),
            ));

    $this->actingAs($chair)
        ->post(route('reports.submit-for-review', $report))
        ->assertRedirect(route('documents.show', $document));

    expect($report->fresh()->status)->toBe('chair-review');

    $this->actingAs($chair)
        ->post(route('reports.submit', $report))
        ->assertRedirect(route('documents.show', $document));

    expect($report->fresh()->status)->toBe('submitted')
        ->and($report->fresh()->submitted_at)->not->toBeNull();
});

it('lets the chair advance a referral from pending to reported', function (): void {
    $chair = committeeActor(UserRole::CommitteeChair);
    $committee = Committee::factory()->create();
    $referral = CommitteeReferral::factory()->create([
        'committee_id' => $committee->getKey(),
        'status' => 'pending',
        'completed_at' => null,
    ]);

    $this->actingAs($chair)
        ->get(route('committees.show', $committee))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.updateReferral', true)
            ->where('committee.referrals.0.transitions', [
                ['to' => 'in-review'],
            ]));

    $this->actingAs($chair)
        ->put(route('referrals.update', $referral), ['status' => 'in-review'])
        ->assertRedirect(route('committees.show', $committee));

    expect($referral->fresh()->status)->toBe('in-review');

    $this->actingAs($chair)
        ->put(route('referrals.update', $referral), ['status' => 'reported'])
        ->assertRedirect(route('committees.show', $committee));

    $fresh = $referral->fresh();

    expect($fresh->status)->toBe('reported')
        ->and($fresh->completed_at)->not->toBeNull();
});

it('rejects skipping from pending to reported', function (): void {
    $chair = committeeActor(UserRole::CommitteeChair);
    $referral = CommitteeReferral::factory()->create(['status' => 'pending']);

    $this->actingAs($chair)
        ->put(route('referrals.update', $referral), ['status' => 'reported'])
        ->assertSessionHasErrors('status');

    expect($referral->fresh()->status)->toBe('pending');
});

it('forbids a committee member from changing referral status', function (): void {
    $member = committeeActor(UserRole::CommitteeMember);
    $committee = Committee::factory()->create();
    $referral = CommitteeReferral::factory()->create([
        'committee_id' => $committee->getKey(),
        'status' => 'pending',
    ]);

    $this->actingAs($member)
        ->get(route('committees.show', $committee))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.updateReferral', false));

    $this->actingAs($member)
        ->put(route('referrals.update', $referral), ['status' => 'in-review'])
        ->assertForbidden();

    expect($referral->fresh()->status)->toBe('pending');
});

it('lists committee detail for authorized members', function (): void {
    $member = committeeActor(UserRole::BoardMember);
    $committee = Committee::factory()->create();

    $this->actingAs($member)
        ->get(route('committees.show', $committee))
        ->assertOk();
});

it('returns a referral to secretariat with a reason and restores the document to registered', function (): void {
    $chair = committeeActor(UserRole::CommitteeChair);
    $secretariat = committeeActor(UserRole::Secretariat);
    $committee = Committee::factory()->create();
    $document = Document::factory()->ofType(DocumentType::Communication)->create([
        'status' => CommitteeReview::$name,
        'committee_id' => $committee->getKey(),
    ]);
    $referral = CommitteeReferral::factory()->create([
        'document_id' => $document->getKey(),
        'committee_id' => $committee->getKey(),
        'status' => 'in-review',
        'completed_at' => null,
    ]);

    $this->actingAs($chair)
        ->put(route('referrals.update', $referral), [
            'status' => 'returned',
            'outcome_notes' => 'The filing is incomplete.',
        ])
        ->assertRedirect(route('committees.show', $committee));

    $freshReferral = $referral->fresh();
    $freshDocument = $document->fresh();

    expect($freshReferral->status)->toBe('returned')
        ->and($freshReferral->outcome_notes)->toBe('The filing is incomplete.')
        ->and($freshReferral->completed_at)->not->toBeNull()
        ->and($freshDocument->status)->toBeInstanceOf(Registered::class);

    $this->actingAs($secretariat)
        ->get(route('documents.show', $freshDocument))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.status', 'registered')
            ->where('document.returned_referral.committee', $committee->name)
            ->where('document.returned_referral.outcome_notes', 'The filing is incomplete.')
            ->where('document.transitions', [
                ['to' => CommitteeReferralState::$name, 'label' => 'Refer to committee'],
            ]));

    $this->actingAs($secretariat)
        ->post(route('documents.transition', $freshDocument), [
            'to' => CommitteeReferralState::$name,
            'committee_id' => $committee->getKey(),
        ])
        ->assertRedirect(route('documents.show', $freshDocument));

    expect(CommitteeReferral::query()->where('document_id', $document->getKey())->count())->toBe(2);
});

it('requires a reason when returning a referral', function (): void {
    $chair = committeeActor(UserRole::CommitteeChair);
    $referral = CommitteeReferral::factory()->create(['status' => 'in-review']);

    $this->actingAs($chair)
        ->put(route('referrals.update', $referral), ['status' => 'returned'])
        ->assertSessionHasErrors('outcome_notes');

    expect($referral->fresh()->status)->toBe('in-review');
});
