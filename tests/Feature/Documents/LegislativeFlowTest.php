<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentWorkflowOutcome;
use App\States\Document\AgendaInclusion;
use App\States\Document\Archive;
use App\States\Document\CommitteeReview;
use App\States\Document\ReadingDeliberation;
use App\States\Document\Registered;
use App\States\Document\SecretariatReview;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function legislativeFlowActor(UserRole $role, string $suffix = 'flow'): User
{
    return User::factory()->create([
        'email' => "{$role->value}-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('marks a registered measure ready for first reading instead of committee referral', function (): void {
    $secretariat = legislativeFlowActor(UserRole::Secretariat, 'first-read');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => Registered::$name,
    ]);

    $this->actingAs($secretariat)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/Show')
            ->where('document.transitions', [
                ['to' => AgendaInclusion::$name, 'label' => 'Ready for first reading'],
            ])
            ->where('document.is_measure', true));
});

it('requires the filing checklist before registering a measure', function (): void {
    $secretariat = legislativeFlowActor(UserRole::Secretariat, 'checklist');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => SecretariatReview::$name,
        'enacting_clause' => null,
        'proposed_effectivity' => null,
        'explanatory_note' => null,
        'reference_number' => null,
    ]);

    $this->actingAs($secretariat)
        ->post(route('documents.transition', $document), ['to' => Registered::$name])
        ->assertSessionHasErrors(['enacting_clause', 'proposed_effectivity', 'explanatory_note', 'reference_number']);

    expect($document->fresh()->status)->toBeInstanceOf(SecretariatReview::class);
});

it('marks a measure ready for first reading without placing it on a session', function (): void {
    $secretariat = legislativeFlowActor(UserRole::Secretariat, 'agenda');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => Registered::$name,
        'title' => 'An ordinance establishing a municipal nursery',
    ]);

    $this->actingAs($secretariat)
        ->post(route('documents.transition', $document), [
            'to' => AgendaInclusion::$name,
        ])
        ->assertRedirect(route('documents.show', $document));

    $fresh = $document->fresh();

    expect($fresh->status)->toBeInstanceOf(AgendaInclusion::class)
        ->and($fresh->current_reading)->toBe(1);

    $this->assertDatabaseMissing('agenda_items', [
        'document_id' => $document->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.transitions', []));
});

it('lays an unfavorable measure on the table from committee review', function (): void {
    Notification::fake();

    $secretariat = legislativeFlowActor(UserRole::Secretariat, 'shelve-sec');
    $author = legislativeFlowActor(UserRole::BoardMember, 'shelve-author');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => CommitteeReview::$name,
        'author_id' => $author->getKey(),
        'current_reading' => 1,
    ]);

    $this->actingAs($secretariat)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.transitions', fn ($transitions): bool => collect($transitions)->contains(
                fn (array $item): bool => $item['to'] === Archive::$name && $item['label'] === 'Lay on the table',
            )));

    $this->actingAs($secretariat)
        ->post(route('documents.transition', $document), ['to' => Archive::$name])
        ->assertRedirect(route('documents.show', $document));

    expect($document->fresh()->status)->toBeInstanceOf(Archive::class);

    Notification::assertSentTo($author, DocumentWorkflowOutcome::class);
});

it('opens first reading from the calendar then refers to committee', function (): void {
    $secretariat = legislativeFlowActor(UserRole::Secretariat, 'refer-after');
    $committee = Committee::factory()->create();
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
    ]);

    $this->actingAs($secretariat)
        ->post(route('documents.transition', $document), ['to' => ReadingDeliberation::$name])
        ->assertRedirect(route('documents.show', $document));

    expect($document->fresh()->status)->toBeInstanceOf(ReadingDeliberation::class);

    $this->actingAs($secretariat)
        ->post(route('documents.transition', $document), [
            'to' => 'committee-referral',
            'committee_id' => $committee->getKey(),
        ])
        ->assertRedirect(route('documents.show', $document));

    expect($document->fresh()->committee_id)->toBe($committee->getKey());
});
