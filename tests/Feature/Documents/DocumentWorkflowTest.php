<?php

use App\Enums\DocumentType;
use App\Enums\ProcessingStatus;
use App\Enums\UserRole;
use App\Jobs\Documents\ProcessDocumentVersionJob;
use App\Models\Committee;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\States\Document\CommitteeReferral;
use App\States\Document\Registered;
use App\States\Document\ReturnedForRevision;
use App\States\Document\SecretariatReview;
use App\States\Document\Submitted;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function workflowActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-docwf@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('offers secretariat review on a submitted document to the secretariat', function (): void {
    $secretariat = workflowActor(UserRole::Secretariat);
    $document = Document::factory()->create(['status' => Submitted::$name]);

    $this->actingAs($secretariat)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/Show')
            ->where('can.transition', true)
            ->where('document.transitions', [
                ['to' => SecretariatReview::$name, 'label' => 'Begin secretariat review'],
                ['to' => ReturnedForRevision::$name, 'label' => 'Return for revision'],
            ]));
});

it('hides document workflow actions from board members', function (): void {
    $member = workflowActor(UserRole::BoardMember);
    $document = Document::factory()->create(['status' => Submitted::$name]);

    $this->actingAs($member)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/Show')
            ->where('document.transitions', []));
});

it('lets a legal reviewer begin review but not register', function (): void {
    $reviewer = workflowActor(UserRole::LegalTechnicalReviewer);
    $submitted = Document::factory()->create(['status' => Submitted::$name]);
    $inReview = Document::factory()->create(['status' => SecretariatReview::$name]);

    $this->actingAs($reviewer)
        ->get(route('documents.show', $submitted))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.transitions', [
                ['to' => SecretariatReview::$name, 'label' => 'Begin secretariat review'],
                ['to' => ReturnedForRevision::$name, 'label' => 'Return for revision'],
            ]));

    $this->actingAs($reviewer)
        ->get(route('documents.show', $inReview))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.transitions', [
                ['to' => ReturnedForRevision::$name, 'label' => 'Return for revision'],
            ]));
});

it('advances a submitted document into secretariat review', function (): void {
    $secretariat = workflowActor(UserRole::Secretariat);
    $document = Document::factory()->create(['status' => Submitted::$name]);

    $this->actingAs($secretariat)
        ->post(route('documents.transition', $document), ['to' => SecretariatReview::$name])
        ->assertRedirect(route('documents.show', $document))
        ->assertSessionHas('success', 'documents.transitioned');

    expect($document->fresh()->status)->toBeInstanceOf(SecretariatReview::class)
        ->and($document->fresh()->reviewed_at)->not->toBeNull()
        ->and($document->fresh()->reviewed_by)->toBe($secretariat->getKey());
});

it('registers a document after secretariat review', function (): void {
    $secretariat = workflowActor(UserRole::Secretariat);
    $document = Document::factory()->create([
        'status' => SecretariatReview::$name,
        'registered_at' => null,
    ]);

    $this->actingAs($secretariat)
        ->post(route('documents.transition', $document), ['to' => Registered::$name])
        ->assertRedirect(route('documents.show', $document));

    $fresh = $document->fresh();

    expect($fresh->status)->toBeInstanceOf(Registered::class)
        ->and($fresh->registered_at)->not->toBeNull()
        ->and($fresh->registered_by)->toBe($secretariat->getKey());
});

it('lists committees on the document and prefills an assigned committee', function (): void {
    $secretariat = workflowActor(UserRole::Secretariat);
    $committee = Committee::factory()->create(['name' => 'Committee on Rules']);
    $document = Document::factory()->ofType(DocumentType::Communication)->create([
        'status' => Registered::$name,
        'committee_id' => $committee->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/Show')
            ->where('document.committee_id', $committee->getKey())
            ->where('document.transitions', [
                ['to' => CommitteeReferral::$name, 'label' => 'Refer to committee'],
            ])
            ->has('committees', 1)
            ->where('committees.0.id', $committee->getKey()));
});

it('requires a committee when referring a registered document', function (): void {
    $secretariat = workflowActor(UserRole::Secretariat);
    $document = Document::factory()->ofType(DocumentType::Communication)->create(['status' => Registered::$name]);

    $this->actingAs($secretariat)
        ->post(route('documents.transition', $document), ['to' => CommitteeReferral::$name])
        ->assertSessionHasErrors('committee_id');

    expect($document->fresh()->status)->toBeInstanceOf(Registered::class);
});

it('refers a registered document to a committee and records the referral', function (): void {
    $secretariat = workflowActor(UserRole::Secretariat);
    $committee = Committee::factory()->create();
    $document = Document::factory()->ofType(DocumentType::Communication)->create([
        'status' => Registered::$name,
        'committee_id' => null,
    ]);

    $this->actingAs($secretariat)
        ->post(route('documents.transition', $document), [
            'to' => CommitteeReferral::$name,
            'committee_id' => $committee->getKey(),
        ])
        ->assertRedirect(route('documents.show', $document));

    $fresh = $document->fresh();

    expect($fresh->status)->toBeInstanceOf(CommitteeReferral::class)
        ->and($fresh->committee_id)->toBe($committee->getKey());

    $this->assertDatabaseHas('committee_referrals', [
        'document_id' => $document->getKey(),
        'committee_id' => $committee->getKey(),
        'status' => 'pending',
        'referred_by' => $secretariat->getKey(),
    ]);
});

it('forbids a board member from advancing document workflow', function (): void {
    $member = workflowActor(UserRole::BoardMember);
    $document = Document::factory()->create(['status' => Submitted::$name]);

    $this->actingAs($member)
        ->post(route('documents.transition', $document), ['to' => SecretariatReview::$name])
        ->assertForbidden();

    expect($document->fresh()->status)->toBeInstanceOf(Submitted::class);
});

it('returns a document to the author for revision with a reason', function (): void {
    $secretariat = workflowActor(UserRole::Secretariat);
    $author = workflowActor(UserRole::BoardMember);
    $document = Document::factory()->create([
        'status' => SecretariatReview::$name,
        'author_id' => $author->getKey(),
        'reviewed_at' => now(),
        'reviewed_by' => $secretariat->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->post(route('documents.transition', $document), [
            'to' => ReturnedForRevision::$name,
            'return_reason' => 'Missing supporting annexes.',
        ])
        ->assertRedirect(route('documents.show', $document));

    $fresh = $document->fresh();

    expect($fresh->status)->toBeInstanceOf(ReturnedForRevision::class)
        ->and($fresh->return_reason)->toBe('Missing supporting annexes.')
        ->and($fresh->returned_by)->toBe($secretariat->getKey())
        ->and($fresh->reviewed_at)->toBeNull();
});

it('requires a reason when returning a document for revision', function (): void {
    $secretariat = workflowActor(UserRole::Secretariat);
    $document = Document::factory()->create(['status' => SecretariatReview::$name]);

    $this->actingAs($secretariat)
        ->post(route('documents.transition', $document), ['to' => ReturnedForRevision::$name])
        ->assertSessionHasErrors('return_reason');

    expect($document->fresh()->status)->toBeInstanceOf(SecretariatReview::class);
});

it('lets the author resubmit a returned document', function (): void {
    $author = workflowActor(UserRole::BoardMember);
    $document = Document::factory()->create([
        'status' => ReturnedForRevision::$name,
        'author_id' => $author->getKey(),
        'return_reason' => 'Incomplete filing.',
        'returned_at' => now()->subHour(),
    ]);

    $this->actingAs($author)
        ->post(route('documents.transition', $document), ['to' => Submitted::$name])
        ->assertRedirect(route('documents.show', $document));

    expect($document->fresh()->status)->toBeInstanceOf(Submitted::class)
        ->and($document->fresh()->submitted_at)->not->toBeNull();
});

it('hides version upload from board members after submission', function (): void {
    $author = workflowActor(UserRole::BoardMember);
    $document = Document::factory()->create([
        'status' => Submitted::$name,
        'author_id' => $author->getKey(),
    ]);

    $this->actingAs($author)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.uploadVersion', false));
});

it('allows board members to upload a version only when returned for revision', function (): void {
    $author = workflowActor(UserRole::BoardMember);
    $document = Document::factory()->create([
        'status' => ReturnedForRevision::$name,
        'author_id' => $author->getKey(),
        'return_reason' => 'Fix abstract.',
        'returned_at' => now(),
    ]);

    $this->actingAs($author)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.uploadVersion', true)
            ->where('document.transitions', [
                ['to' => Submitted::$name, 'label' => 'Resubmit to secretariat'],
            ]));
});

it('allows board members to reupload when the current version failed processing', function (): void {
    $author = workflowActor(UserRole::BoardMember);
    $document = Document::factory()->create([
        'status' => Submitted::$name,
        'author_id' => $author->getKey(),
    ]);

    DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
        'version_number' => 1,
        'is_current' => true,
        'uploaded_by' => $author->getKey(),
        'processing_status' => ProcessingStatus::Failed->value,
        'processing_error' => 'OCR produced no extractable text.',
    ]);

    $this->actingAs($author)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.uploadVersion', true));
});

it('queues processing retry for a failed current version without reupload', function (): void {
    Queue::fake();

    $author = workflowActor(UserRole::BoardMember);
    $document = Document::factory()->create([
        'status' => Submitted::$name,
        'author_id' => $author->getKey(),
    ]);

    $version = DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
        'version_number' => 1,
        'is_current' => true,
        'uploaded_by' => $author->getKey(),
        'ocr_status' => 'failed',
        'ocr_error' => 'OCR produced no extractable text.',
        'processing_status' => ProcessingStatus::Failed->value,
        'processing_error' => 'OCR produced no extractable text.',
    ]);

    $this->actingAs($author)
        ->post(route('documents.versions.retry-processing', [$document, $version]))
        ->assertRedirect(route('documents.show', $document));

    $version->refresh();
    expect($version->ocr_status)->toBe('pending')
        ->and($version->processing_status)->toBe(ProcessingStatus::Pending);

    Queue::assertPushed(ProcessDocumentVersionJob::class, fn (ProcessDocumentVersionJob $job): bool => $job->documentVersionId === $version->getKey());
});
