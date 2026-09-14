<?php

use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\CommitteeReferral;
use App\Models\CommitteeReport;
use App\Models\Document;
use App\Models\User;
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

function rosterActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-roster@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('lets the secretariat appoint a member from the committee record', function (): void {
    $secretariat = rosterActor(UserRole::Secretariat);
    $committee = Committee::factory()->create();
    $member = User::factory()->seatedMember()->create();

    $this->actingAs($secretariat)
        ->get(route('committees.show', $committee))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Committees/Show')
            ->where('can.manageMembers', true)
            ->where('can.createReferral', true)
            ->where('can.createReport', true)
            ->has('appointableUsers'));

    $this->actingAs($secretariat)
        ->post(route('committees.members.store', $committee), [
            'user_id' => $member->getKey(),
            'position' => 'chair',
            'appointed_on' => '2026-01-15',
        ])
        ->assertRedirect(route('committees.show', $committee));

    $membership = CommitteeMember::query()
        ->where('committee_id', $committee->getKey())
        ->where('user_id', $member->getKey())
        ->firstOrFail();

    expect($membership->position)->toBe('chair')
        ->and($membership->is_active)->toBeTrue()
        ->and($membership->appointed_on?->toDateString())->toBe('2026-01-15');
});

it('rejects appointing the same person twice', function (): void {
    $secretariat = rosterActor(UserRole::Secretariat);
    $committee = Committee::factory()->create();
    $member = User::factory()->seatedMember()->create();

    CommitteeMember::factory()->create([
        'committee_id' => $committee->getKey(),
        'user_id' => $member->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->from(route('committees.show', $committee))
        ->post(route('committees.members.store', $committee), [
            'user_id' => $member->getKey(),
            'position' => 'member',
        ])
        ->assertRedirect(route('committees.show', $committee))
        ->assertSessionHasErrors('user_id');
});

it('forbids a board member from appointing committee members', function (): void {
    $boardMember = rosterActor(UserRole::BoardMember);
    $committee = Committee::factory()->create();
    $candidate = User::factory()->seatedMember()->create();

    $this->actingAs($boardMember)
        ->get(route('committees.show', $committee))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageMembers', false)
            ->where('appointableUsers', []));

    $this->actingAs($boardMember)
        ->post(route('committees.members.store', $committee), [
            'user_id' => $candidate->getKey(),
            'position' => 'member',
        ])
        ->assertForbidden();
});

it('lets the committee chair refer a measure and draft a report from the record', function (): void {
    $chair = rosterActor(UserRole::CommitteeChair);
    $committee = Committee::factory()->create();
    $document = Document::factory()->create();

    $this->actingAs($chair)
        ->post(route('referrals.store'), [
            'document_id' => $document->getKey(),
            'committee_id' => $committee->getKey(),
            'instructions' => 'Review and report within thirty days.',
            'due_at' => now()->addDays(30)->toDateString(),
            'is_primary' => true,
        ])
        ->assertRedirect(route('committees.show', $committee));

    $referral = CommitteeReferral::query()
        ->where('document_id', $document->getKey())
        ->where('committee_id', $committee->getKey())
        ->firstOrFail();

    $this->actingAs($chair)
        ->post(route('reports.store'), [
            'committee_referral_id' => $referral->getKey(),
            'committee_id' => $committee->getKey(),
            'recommendation' => 'approve',
            'findings' => 'The committee recommends approval as proposed.',
            'report_number' => 'CR-2026-010',
        ])
        ->assertRedirect(route('documents.show', $document));

    $report = CommitteeReport::query()->where('report_number', 'CR-2026-010')->firstOrFail();

    expect($report->status)->toBe('draft')
        ->and($report->subject_document_id)->toBe($document->getKey())
        ->and($report->recommendation)->toBe('approve');

    $this->actingAs($chair)
        ->post(route('reports.submit-for-review', $report))
        ->assertRedirect(route('documents.show', $document));

    expect($report->fresh()->status)->toBe('chair-review');

    $this->actingAs($chair)
        ->post(route('reports.submit', $report))
        ->assertRedirect(route('documents.show', $document));

    expect($report->fresh()->status)->toBe('submitted');
});

it('lets a committee member send a draft to the chair but not submit it to the body', function (): void {
    $member = rosterActor(UserRole::CommitteeMember);
    $chair = rosterActor(UserRole::CommitteeChair);
    $committee = Committee::factory()->create();
    $document = Document::factory()->create();
    $referral = CommitteeReferral::factory()->create([
        'document_id' => $document->getKey(),
        'committee_id' => $committee->getKey(),
        'status' => 'in-review',
    ]);
    $report = CommitteeReport::factory()->create([
        'committee_referral_id' => $referral->getKey(),
        'committee_id' => $committee->getKey(),
        'subject_document_id' => $document->getKey(),
        'status' => 'draft',
        'submitted_by' => $member->getKey(),
    ]);

    $this->actingAs($member)
        ->post(route('reports.submit', $report))
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('reports.submit-for-review', $report))
        ->assertRedirect(route('documents.show', $document));

    expect($report->fresh()->status)->toBe('chair-review');

    $this->actingAs($member)
        ->post(route('reports.submit', $report))
        ->assertForbidden();

    $this->actingAs($chair)
        ->post(route('reports.submit', $report))
        ->assertRedirect(route('documents.show', $document));

    expect($report->fresh()->status)->toBe('submitted');
});
