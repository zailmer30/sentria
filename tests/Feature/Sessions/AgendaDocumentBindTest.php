<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Services\Sessions\AgendaService;
use App\States\Document\AgendaInclusion;
use App\States\Document\Registered;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function bindAgendaActor(string $suffix): User
{
    return User::factory()->create([
        'email' => "secretariat-bind-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);
}

it('binds ready measures under first reading', function (): void {
    $secretariat = bindAgendaActor('first-reading');
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-08-04 09:00:00',
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);

    $first = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance on nurseries',
    ]);
    $second = Document::factory()->ofType(DocumentType::ProposedResolution)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'A resolution on street lighting',
    ]);
    $heading = $session->agendaItems()->where('category', 'first-reading')->whereNull('document_id')->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.documents.store', [$session, $heading]), [
            'document_ids' => [$first->getKey(), $second->getKey()],
        ])
        ->assertRedirect();

    $children = $session->agendaItems()
        ->where('parent_id', $heading->getKey())
        ->orderBy('position')
        ->get();
    $adjournment = $session->agendaItems()->where('category', 'adjournment')->firstOrFail();

    expect($children)->toHaveCount(2)
        ->and($children->pluck('item_number')->all())->toBe(['7.1', '7.2'])
        ->and($children->pluck('document_id')->all())->toBe([$first->getKey(), $second->getKey()])
        ->and($children[0]?->reading_number)->toBe(1)
        ->and($children[0]?->requires_vote)->toBeFalse()
        ->and($first->fresh()->status)->toBeInstanceOf(AgendaInclusion::class)
        ->and($first->fresh()->current_reading)->toBe(1)
        ->and($second->fresh()->status)->toBeInstanceOf(AgendaInclusion::class)
        ->and($adjournment->fresh()->position)->toBe($session->agendaItems()->count());
});

it('rejects binding documents to a ritual heading', function (): void {
    $secretariat = bindAgendaActor('ritual');
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-08-11 09:00:00',
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);

    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => Registered::$name,
    ]);
    $callToOrder = $session->agendaItems()->where('category', 'call-to-order')->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.documents.store', [$session, $callToOrder]), [
            'document_ids' => [$document->getKey()],
        ])
        ->assertUnprocessable();

    expect($session->agendaItems()->where('document_id', $document->getKey())->exists())->toBeFalse()
        ->and($document->fresh()->status)->toBeInstanceOf(Registered::class);
});

it('is a no-op when the document is already on the heading', function (): void {
    $secretariat = bindAgendaActor('duplicate');
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-08-18 09:00:00',
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);

    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance already on the heading',
    ]);
    $heading = $session->agendaItems()->where('category', 'first-reading')->whereNull('document_id')->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.documents.store', [$session, $heading]), [
            'document_ids' => [$document->getKey()],
        ])
        ->assertRedirect();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.documents.store', [$session, $heading]), [
            'document_ids' => [$document->getKey()],
        ])
        ->assertRedirect();

    expect($session->agendaItems()->where('document_id', $document->getKey())->count())->toBe(1);
});

it('rejects binding a registered measure that is not yet ready for first reading', function (): void {
    $secretariat = bindAgendaActor('not-ready');
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-08-20 09:00:00',
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);

    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => Registered::$name,
    ]);
    $heading = $session->agendaItems()->where('category', 'first-reading')->whereNull('document_id')->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.documents.store', [$session, $heading]), [
            'document_ids' => [$document->getKey()],
        ])
        ->assertUnprocessable();

    expect($session->agendaItems()->where('document_id', $document->getKey())->exists())->toBeFalse()
        ->and($document->fresh()->status)->toBeInstanceOf(Registered::class);
});

it('places a calendared measure that is not yet on the session under first reading', function (): void {
    $secretariat = bindAgendaActor('already-inclusion');
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-08-25 09:00:00',
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);

    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance calendared but not nested',
    ]);
    $heading = $session->agendaItems()->where('category', 'first-reading')->whereNull('document_id')->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.documents.store', [$session, $heading]), [
            'document_ids' => [$document->getKey()],
        ])
        ->assertRedirect();

    $child = $session->agendaItems()->where('document_id', $document->getKey())->first();

    expect($child)->not->toBeNull()
        ->and($child?->parent_id)->toBe($heading->getKey())
        ->and($child?->item_number)->toBe('7.1')
        ->and($child?->reading_number)->toBe(1)
        ->and($document->fresh()->status)->toBeInstanceOf(AgendaInclusion::class);
});
