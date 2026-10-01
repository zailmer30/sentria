<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\Committee;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\States\Document\AgendaInclusion;
use App\States\Document\Archive;
use App\States\Document\CommitteeReferral;
use App\States\Document\CommitteeReport as CommitteeReportState;
use App\States\Document\CommitteeReview;
use App\States\Document\ReadingDeliberation;
use App\States\Document\Registered;
use App\States\Document\SecretariatReview;
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
        'explanatory_note' => null,
        'reference_number' => null,
    ]);

    $this->actingAs($secretariat)
        ->post(route('documents.transition', $document), ['to' => Registered::$name])
        ->assertSessionHasErrors(['enacting_clause', 'explanatory_note', 'reference_number']);

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

it('does not offer laying a measure on the table from committee review', function (): void {
    $secretariat = legislativeFlowActor(UserRole::Secretariat, 'shelve-sec');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => CommitteeReview::$name,
        'current_reading' => 1,
    ]);

    $this->actingAs($secretariat)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.transitions', fn ($transitions): bool => collect($transitions)->every(
                fn (array $item): bool => $item['to'] !== Archive::$name,
            )));
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

it('still offers ready for second reading before the measure is calendared', function (): void {
    $secretariat = legislativeFlowActor(UserRole::Secretariat, 'off-session-second');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => CommitteeReportState::$name,
        'current_reading' => 1,
    ]);

    $this->actingAs($secretariat)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.on_session', false)
            ->where('document.transitions', [
                ['to' => AgendaInclusion::$name, 'label' => 'Ready for second reading'],
            ]));
});

it('does not offer ready for second reading once the measure is on a sitting', function (): void {
    $secretariat = legislativeFlowActor(UserRole::Secretariat, 'on-session-second');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => CommitteeReportState::$name,
        'current_reading' => 1,
    ]);
    AgendaItem::factory()->create([
        'document_id' => $document->getKey(),
        'status' => 'pending',
        'category' => 'committee-reports',
    ]);

    $this->actingAs($secretariat)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.on_session', true)
            ->where('document.transitions', []));
});

it('does not offer send to committee review after a committee hearing is finished', function (): void {
    $secretariat = legislativeFlowActor(UserRole::Secretariat, 'heard');
    $document = Document::factory()->ofType(DocumentType::ProposedResolution)->create([
        'status' => CommitteeReferral::$name,
        'current_reading' => 1,
    ]);
    $hearing = LegislativeSession::factory()->create([
        'type' => 'committee-hearing',
        'status' => 'adjourned',
    ]);
    AgendaItem::factory()->create([
        'session_id' => $hearing->getKey(),
        'document_id' => $document->getKey(),
        'status' => 'completed',
        'category' => 'referred-measures',
        'completed_at' => now(),
    ]);

    $this->actingAs($secretariat)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.transitions', []));
});

it('still offers send to committee review before the committee hearing is finished', function (): void {
    $secretariat = legislativeFlowActor(UserRole::Secretariat, 'not-heard');
    $document = Document::factory()->ofType(DocumentType::ProposedResolution)->create([
        'status' => CommitteeReferral::$name,
        'current_reading' => 1,
    ]);
    $plenary = LegislativeSession::factory()->adjourned()->create([
        'type' => 'regular',
    ]);
    AgendaItem::factory()->create([
        'session_id' => $plenary->getKey(),
        'document_id' => $document->getKey(),
        'status' => 'completed',
        'category' => 'first-reading',
        'reading_number' => 1,
        'completed_at' => now(),
    ]);

    $this->actingAs($secretariat)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.transitions', [
                ['to' => CommitteeReview::$name, 'label' => 'Send to committee review'],
            ]));
});

it('does not offer opening a reading after the measure is attached to a sitting', function (): void {
    $secretariat = legislativeFlowActor(UserRole::Secretariat, 'on-session-open');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
    ]);
    AgendaItem::factory()->create([
        'document_id' => $document->getKey(),
        'status' => 'pending',
        'category' => 'first-reading',
        'reading_number' => 1,
    ]);

    $this->actingAs($secretariat)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.on_session', true)
            ->where('document.transitions', []));
});
