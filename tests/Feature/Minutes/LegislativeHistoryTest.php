<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Enums\VoteChoice;
use App\Models\AgendaItem;
use App\Models\Committee;
use App\Models\CommitteeReferral;
use App\Models\CommitteeReport;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\LegislativeSession;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\User;
use App\Models\Vote;
use App\States\Document\Approved;
use App\States\Document\Archive;
use App\States\Document\FinalDocument;
use App\States\Document\Rejected;
use App\States\Document\SecretariatReview;
use App\States\Document\Submitted;
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

function historyViewer(): User
{
    return User::factory()->create([
        'email' => 'secretariat-history@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);
}

it('renders a legislative history timeline for an ordinance with a full lifecycle path', function (): void {
    $viewer = historyViewer();
    $author = User::factory()->create(['is_active' => true]);

    $document = Document::factory()->ofType(DocumentType::Ordinance)->create([
        'author_id' => $author->getKey(),
        'status' => FinalDocument::$name,
        'title' => 'Provincial Scholarship Ordinance',
        'submitted_at' => now()->subMonths(3),
    ]);

    DocumentVersion::factory()->for($document)->create([
        'version_number' => 1,
        'uploaded_by' => $author->getKey(),
        'is_current' => false,
    ]);

    DocumentVersion::factory()->for($document)->create([
        'version_number' => 2,
        'uploaded_by' => $author->getKey(),
        'is_current' => true,
    ]);

    $committee = Committee::factory()->create();
    $referral = CommitteeReferral::factory()->create([
        'document_id' => $document->getKey(),
        'committee_id' => $committee->getKey(),
        'referred_by' => $viewer->getKey(),
        'status' => 'reported',
        'referred_at' => now()->subMonths(2),
    ]);

    CommitteeReport::factory()->adopted()->create([
        'committee_referral_id' => $referral->getKey(),
        'committee_id' => $committee->getKey(),
        'subject_document_id' => $document->getKey(),
        'submitted_by' => $viewer->getKey(),
    ]);

    $session = LegislativeSession::factory()->adjourned()->create([
        'scheduled_start_at' => now()->subMonth(),
    ]);

    $agendaItem = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'document_id' => $document->getKey(),
        'title' => $document->title,
        'category' => 'new-business',
        'requires_vote' => true,
        'started_at' => now()->subMonth(),
    ]);

    Vote::factory()->count(3)->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $agendaItem->getKey(),
        'choice' => VoteChoice::Yes->value,
        'voting_round' => 1,
    ]);

    $document->update(['status' => Approved::$name]);

    $ordinance = Ordinance::factory()->create([
        'document_id' => $document->getKey(),
        'title' => $document->title,
    ]);

    test()->actingAs($viewer)
        ->get(route('ordinances.history', $ordinance))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Legislation/History')
            ->has('events')
            ->where('subject.title', $ordinance->title)
            ->etc()
        );

    $response = test()->actingAs($viewer)->get(route('ordinances.history', $ordinance));
    $events = $response->original->getData()['page']['props']['events'] ?? [];
    $stages = collect($events)->pluck('stage')->unique()->values()->all();

    expect($stages)->toContain('document', 'committee_referral', 'committee_report', 'session', 'amendment', 'vote');
});

it('renders history for a document slug route', function (): void {
    $viewer = historyViewer();

    $document = Document::factory()->create([
        'status' => Submitted::$name,
        'submitted_at' => now()->subWeek(),
    ]);

    DocumentVersion::factory()->for($document)->create([
        'uploaded_by' => $viewer->getKey(),
    ]);

    test()->actingAs($viewer)
        ->get(route('documents.history', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Legislation/History')
            ->has('events')
        );
});

it('returns ordinance and resolution legislative history as json for the register drawer', function (): void {
    $viewer = historyViewer();

    $ordinanceDocument = Document::factory()->ofType(DocumentType::Ordinance)->create([
        'status' => Submitted::$name,
        'submitted_at' => now()->subWeek(),
        'title' => 'Scholarship ordinance',
    ]);

    DocumentVersion::factory()->for($ordinanceDocument)->create([
        'uploaded_by' => $viewer->getKey(),
    ]);

    $ordinance = Ordinance::factory()->create([
        'document_id' => $ordinanceDocument->getKey(),
        'title' => $ordinanceDocument->title,
    ]);

    test()->actingAs($viewer)
        ->getJson(route('ordinances.history', $ordinance))
        ->assertOk()
        ->assertJsonPath('subject.type', 'ordinance')
        ->assertJsonPath('subject.title', $ordinance->title)
        ->assertJsonPath('events.0.stage', 'document')
        ->assertJsonPath('events.0.label', 'Document Submitted');

    $resolutionDocument = Document::factory()->ofType(DocumentType::Resolution)->create([
        'status' => Submitted::$name,
        'submitted_at' => now()->subWeek(),
        'title' => 'Investment plan resolution',
    ]);

    DocumentVersion::factory()->for($resolutionDocument)->create([
        'uploaded_by' => $viewer->getKey(),
    ]);

    $resolution = Resolution::factory()->create([
        'document_id' => $resolutionDocument->getKey(),
        'title' => $resolutionDocument->title,
    ]);

    test()->actingAs($viewer)
        ->getJson(route('resolutions.history', $resolution))
        ->assertOk()
        ->assertJsonPath('subject.type', 'resolution')
        ->assertJsonPath('subject.title', $resolution->title)
        ->assertJsonPath('events.0.stage', 'document');
});

it('embeds legislative history on the document record', function (): void {
    $viewer = historyViewer();

    $document = Document::factory()->create([
        'status' => Submitted::$name,
        'submitted_at' => now()->subWeek(),
        'title' => 'Sample Ordinance',
    ]);

    DocumentVersion::factory()->for($document)->create([
        'uploaded_by' => $viewer->getKey(),
    ]);

    test()->actingAs($viewer)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/Show')
            ->has('history')
            ->where('history.0.stage', 'document')
            ->where('history.0.label', 'Document Submitted')
        );
});

it('includes secretariat review on the legislative history timeline', function (): void {
    $viewer = historyViewer();

    $document = Document::factory()->create([
        'status' => SecretariatReview::$name,
        'submitted_at' => now()->subDays(2),
        'reviewed_at' => now()->subDay(),
        'reviewed_by' => $viewer->getKey(),
        'title' => 'Sample Ordinance',
        'reference_number' => 'PO-2026-01',
    ]);

    DocumentVersion::factory()->for($document)->create([
        'uploaded_by' => $viewer->getKey(),
    ]);

    $response = test()->actingAs($viewer)
        ->get(route('documents.history', $document))
        ->assertOk();

    $events = $response->original->getData()['page']['props']['events'] ?? [];
    $stages = collect($events)->pluck('stage')->all();

    expect($stages)->toContain('document', 'secretariat_review')
        ->and(collect($events)->firstWhere('stage', 'secretariat_review'))
        ->label->toBe('Secretariat Review')
        ->and(collect($events)->firstWhere('stage', 'secretariat_review')['meta']['reviewed_by'] ?? null)
        ->toBe($viewer->display_name);
});

it('records first, second, and third reading dates on the legislative history', function (): void {
    $viewer = historyViewer();

    $document = Document::factory()->ofType(DocumentType::Ordinance)->create([
        'title' => 'An ordinance through three readings',
        'submitted_at' => now()->subMonths(3),
    ]);

    $firstSession = LegislativeSession::factory()->adjourned()->create([
        'title' => 'Regular Session — First reading',
        'scheduled_start_at' => now()->subMonths(2),
    ]);
    $secondSession = LegislativeSession::factory()->adjourned()->create([
        'title' => 'Regular Session — Second reading',
        'scheduled_start_at' => now()->subMonth(),
    ]);
    $thirdSession = LegislativeSession::factory()->adjourned()->create([
        'title' => 'Regular Session — Third reading',
        'scheduled_start_at' => now()->subWeek(),
    ]);

    AgendaItem::factory()->create([
        'session_id' => $firstSession->getKey(),
        'document_id' => $document->getKey(),
        'title' => $document->title,
        'category' => 'first-reading',
        'reading_number' => 1,
        'started_at' => now()->subMonths(2)->setTime(9, 0),
        'completed_at' => now()->subMonths(2)->setTime(9, 20),
    ]);
    AgendaItem::factory()->create([
        'session_id' => $secondSession->getKey(),
        'document_id' => $document->getKey(),
        'title' => $document->title,
        'category' => 'second-reading',
        'reading_number' => 2,
        'started_at' => now()->subMonth()->setTime(10, 0),
        'completed_at' => now()->subMonth()->setTime(11, 0),
    ]);
    AgendaItem::factory()->create([
        'session_id' => $thirdSession->getKey(),
        'document_id' => $document->getKey(),
        'title' => $document->title,
        'category' => 'third-reading',
        'reading_number' => 3,
        'started_at' => now()->subWeek()->setTime(14, 0),
        'completed_at' => now()->subWeek()->setTime(14, 30),
    ]);

    $response = test()->actingAs($viewer)
        ->get(route('documents.history', $document))
        ->assertOk();

    $events = collect($response->original->getData()['page']['props']['events'] ?? []);

    expect($events->firstWhere('stage', 'first_reading'))
        ->label->toBe('First Reading')
        ->and($events->firstWhere('stage', 'first_reading')['occurred_at'])->not->toBeNull()
        ->and($events->firstWhere('stage', 'second_reading'))
        ->label->toBe('Second Reading')
        ->and($events->firstWhere('stage', 'third_reading'))
        ->label->toBe('Third and Final Reading');
});

it('places approval after third and final reading when the vote closed first', function (): void {
    $viewer = historyViewer();

    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'title' => 'Sample Ordinance',
        'submitted_at' => now()->subDays(2),
    ]);

    Document::query()->whereKey($document->getKey())->update([
        'status' => Approved::$name,
        'updated_at' => now()->subDay()->setTime(16, 53, 14),
    ]);
    $document->refresh();

    $version = DocumentVersion::factory()->for($document)->create([
        'uploaded_by' => $viewer->getKey(),
    ]);
    $version->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();

    $session = LegislativeSession::factory()->adjourned()->create([
        'title' => '1st Regular Session',
        'scheduled_start_at' => now()->subDay(),
    ]);

    AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'document_id' => $document->getKey(),
        'title' => $document->title,
        'category' => 'third-reading',
        'reading_number' => 3,
        'status' => 'completed',
        'started_at' => now()->subDay()->setTime(16, 53, 4),
        'completed_at' => now()->subDay()->setTime(16, 53, 16),
        'voting_closed_at' => now()->subDay()->setTime(16, 53, 14),
    ]);

    $response = test()->actingAs($viewer)
        ->get(route('documents.history', $document))
        ->assertOk();

    $events = collect($response->original->getData()['page']['props']['events'] ?? []);
    $readingAt = $events->search(fn (array $event): bool => $event['stage'] === 'third_reading');
    $approvalAt = $events->search(fn (array $event): bool => $event['stage'] === 'approval');

    expect($events->last()['stage'])->toBe('approval')
        ->and($readingAt)->toBeInt()
        ->and($approvalAt)->toBeGreaterThan($readingAt);
});

it('keeps a rejection on the legislative history after the measure is archived', function (): void {
    $viewer = historyViewer();

    $document = Document::factory()->ofType(DocumentType::Ordinance)->create([
        'title' => 'An ordinance rejected on third reading',
        'submitted_at' => now()->subDays(3),
    ]);

    DocumentVersion::factory()->for($document)->create([
        'uploaded_by' => $viewer->getKey(),
    ]);

    $session = LegislativeSession::factory()->adjourned()->create([
        'title' => '1st Regular Session',
        'scheduled_start_at' => now()->subDay(),
    ]);

    AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'document_id' => $document->getKey(),
        'title' => $document->title,
        'category' => 'third-reading',
        'reading_number' => 3,
        'started_at' => now()->subDay()->setTime(14, 0),
        'completed_at' => now()->subDay()->setTime(14, 30),
    ]);

    config(['audit.console' => true]);

    $document->forceFill(['status' => Rejected::$name])->save();
    $document->forceFill([
        'status' => Archive::$name,
        'archived_at' => now()->subDay()->setTime(16, 0),
    ])->save();

    $response = test()->actingAs($viewer)
        ->get(route('documents.history', $document))
        ->assertOk();

    $events = collect($response->original->getData()['page']['props']['events'] ?? []);
    $rejectedAt = $events->search(fn (array $event): bool => $event['stage'] === 'rejected');
    $archiveAt = $events->search(fn (array $event): bool => $event['stage'] === 'archive');
    $readingAt = $events->search(fn (array $event): bool => $event['stage'] === 'third_reading');

    expect($events->firstWhere('stage', 'rejected'))
        ->label->toBe('Rejected')
        ->and($events->firstWhere('stage', 'archive'))
        ->label->toBe('Archive')
        ->and($readingAt)->toBeInt()
        ->and($rejectedAt)->toBeGreaterThan($readingAt)
        ->and($archiveAt)->toBeGreaterThan($rejectedAt);
});

it('records archive without a rejection when the measure was not rejected', function (): void {
    $viewer = historyViewer();

    $document = Document::factory()->ofType(DocumentType::Ordinance)->create([
        'title' => 'An ordinance sent to the archive',
        'submitted_at' => now()->subWeek(),
    ]);

    DocumentVersion::factory()->for($document)->create([
        'uploaded_by' => $viewer->getKey(),
    ]);

    $document->forceFill([
        'status' => Archive::$name,
        'archived_at' => now()->subDay(),
    ])->save();

    $response = test()->actingAs($viewer)
        ->get(route('documents.history', $document))
        ->assertOk();

    $stages = collect($response->original->getData()['page']['props']['events'] ?? [])->pluck('stage');

    expect($stages)->toContain('archive')
        ->and($stages)->not->toContain('rejected');
});
