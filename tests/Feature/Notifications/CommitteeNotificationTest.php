<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\CommitteeReferral;
use App\Models\CommitteeReport;
use App\Models\Document;
use App\Models\User;
use App\Notifications\CommitteeMemberAppointed;
use App\Notifications\CommitteeReportSubmitted;
use App\Notifications\DocumentEnteredCommitteeReview;
use App\Notifications\DocumentReferralReturned;
use App\Notifications\DocumentReferredToCommittee;
use App\Notifications\DocumentWorkflowOutcome;
use App\States\Document\Approved;
use App\States\Document\CommitteeReferral as CommitteeReferralState;
use App\States\Document\CommitteeReview as CommitteeReviewState;
use App\States\Document\Registered;
use App\States\Document\Voting;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function notifyCommitteeActor(UserRole $role, string $suffix = 'cmte'): User
{
    return User::factory()->create([
        'email' => "{$role->value}-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('notifies active committee members when a referral is created, but not the referrer', function (): void {
    Notification::fake();

    $chair = notifyCommitteeActor(UserRole::CommitteeChair, 'referrer');
    $member = notifyCommitteeActor(UserRole::CommitteeMember, 'member');
    $outsider = notifyCommitteeActor(UserRole::BoardMember, 'outsider');

    $committee = Committee::factory()->create();
    CommitteeMember::factory()->create([
        'committee_id' => $committee->getKey(),
        'user_id' => $chair->getKey(),
        'position' => 'chair',
        'is_active' => true,
    ]);
    CommitteeMember::factory()->create([
        'committee_id' => $committee->getKey(),
        'user_id' => $member->getKey(),
        'position' => 'member',
        'is_active' => true,
    ]);

    $document = Document::factory()->create();

    $this->actingAs($chair)
        ->post(route('referrals.store'), [
            'document_id' => $document->getKey(),
            'committee_id' => $committee->getKey(),
            'instructions' => 'Review and report.',
            'due_at' => now()->addDays(30)->toDateString(),
        ])
        ->assertRedirect(route('committees.show', $committee));

    Notification::assertSentTo($member, DocumentReferredToCommittee::class);
    Notification::assertNotSentTo($chair, DocumentReferredToCommittee::class);
    Notification::assertNotSentTo($outsider, DocumentReferredToCommittee::class);
});

it('notifies committee members when a document enters committee review', function (): void {
    Notification::fake();

    $secretariat = notifyCommitteeActor(UserRole::Secretariat, 'irp');
    $member = notifyCommitteeActor(UserRole::CommitteeMember, 'irp-member');
    $committee = Committee::factory()->create();

    CommitteeMember::factory()->create([
        'committee_id' => $committee->getKey(),
        'user_id' => $member->getKey(),
        'position' => 'member',
        'is_active' => true,
    ]);

    $document = Document::factory()->create([
        'status' => CommitteeReferralState::$name,
        'committee_id' => $committee->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->post(route('documents.transition', $document), ['to' => CommitteeReviewState::$name])
        ->assertRedirect(route('documents.show', $document));

    Notification::assertSentTo($member, DocumentEnteredCommitteeReview::class);
    Notification::assertNotSentTo($secretariat, DocumentEnteredCommitteeReview::class);
});

it('notifies secretariat when a committee report is submitted', function (): void {
    Notification::fake();

    $chair = notifyCommitteeActor(UserRole::CommitteeChair, 'report');
    $secretariat = notifyCommitteeActor(UserRole::Secretariat, 'report-sec');
    $author = notifyCommitteeActor(UserRole::BoardMember, 'report-author');

    $committee = Committee::factory()->create();
    $document = Document::factory()->create(['author_id' => $author->getKey()]);

    $referral = CommitteeReferral::factory()->create([
        'document_id' => $document->getKey(),
        'committee_id' => $committee->getKey(),
        'referred_by' => $chair->getKey(),
        'status' => 'pending',
    ]);

    $report = CommitteeReport::factory()->create([
        'committee_referral_id' => $referral->getKey(),
        'committee_id' => $committee->getKey(),
        'subject_document_id' => $document->getKey(),
        'status' => 'draft',
        'recommendation' => 'approve',
        'submitted_by' => $chair->getKey(),
    ]);

    $this->actingAs($chair)
        ->post(route('reports.submit-for-review', $report))
        ->assertRedirect(route('documents.show', $document));

    $this->actingAs($chair)
        ->post(route('reports.submit', $report))
        ->assertRedirect(route('documents.show', $document));

    Notification::assertSentTo($secretariat, CommitteeReportSubmitted::class);
    Notification::assertSentTo($author, CommitteeReportSubmitted::class);
    Notification::assertNotSentTo($chair, CommitteeReportSubmitted::class);
});

it('notifies a user when appointed to a committee', function (): void {
    Notification::fake();

    $secretariat = notifyCommitteeActor(UserRole::Secretariat, 'appoint');
    $member = User::factory()->seatedMember()->create([
        'email' => 'appointed@sentria.test',
        'is_active' => true,
    ]);
    $committee = Committee::factory()->create();

    $this->actingAs($secretariat)
        ->post(route('committees.members.store', $committee), [
            'user_id' => $member->getKey(),
            'position' => 'member',
            'appointed_on' => '2026-01-15',
        ])
        ->assertRedirect(route('committees.show', $committee));

    Notification::assertSentTo($member, CommitteeMemberAppointed::class);
    Notification::assertNotSentTo($secretariat, CommitteeMemberAppointed::class);
});

it('notifies the author when a document is approved', function (): void {
    Notification::fake();

    $secretariat = notifyCommitteeActor(UserRole::Secretariat, 'approve');
    $author = notifyCommitteeActor(UserRole::BoardMember, 'approve-author');

    $document = Document::factory()->create([
        'status' => Voting::$name,
        'author_id' => $author->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->post(route('documents.transition', $document), ['to' => Approved::$name])
        ->assertRedirect(route('documents.show', $document));

    Notification::assertSentTo($author, DocumentWorkflowOutcome::class);
    Notification::assertNotSentTo($secretariat, DocumentWorkflowOutcome::class);
});

it('notifies committee members when IRP moves to committee referral', function (): void {
    Notification::fake();

    $secretariat = notifyCommitteeActor(UserRole::Secretariat, 'ref-irp');
    $member = notifyCommitteeActor(UserRole::CommitteeMember, 'ref-irp-m');
    $committee = Committee::factory()->create();

    CommitteeMember::factory()->create([
        'committee_id' => $committee->getKey(),
        'user_id' => $member->getKey(),
        'position' => 'member',
        'is_active' => true,
    ]);

    $document = Document::factory()->ofType(DocumentType::Communication)->create([
        'status' => Registered::$name,
        'committee_id' => $committee->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->post(route('documents.transition', $document), [
            'to' => CommitteeReferralState::$name,
            'committee_id' => $committee->getKey(),
        ])
        ->assertRedirect(route('documents.show', $document));

    Notification::assertSentTo($member, DocumentEnteredCommitteeReview::class);
});

it('notifies secretariat when a referral is returned', function (): void {
    Notification::fake();

    $chair = notifyCommitteeActor(UserRole::CommitteeChair, 'return-chair');
    $secretariat = notifyCommitteeActor(UserRole::Secretariat, 'return-sec');
    $committee = Committee::factory()->create();
    $document = Document::factory()->create([
        'status' => CommitteeReviewState::$name,
        'committee_id' => $committee->getKey(),
    ]);
    $referral = CommitteeReferral::factory()->create([
        'document_id' => $document->getKey(),
        'committee_id' => $committee->getKey(),
        'status' => 'in-review',
    ]);

    $this->actingAs($chair)
        ->put(route('referrals.update', $referral), [
            'status' => 'returned',
            'outcome_notes' => 'Wrong committee.',
        ])
        ->assertRedirect(route('committees.show', $committee));

    Notification::assertSentTo($secretariat, DocumentReferralReturned::class);
    Notification::assertNotSentTo($chair, DocumentReferralReturned::class);
});
