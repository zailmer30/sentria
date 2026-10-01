<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\CommitteeReferral as CommitteeReferralModel;
use App\Models\CommitteeReport;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Services\Sessions\AgendaService;
use App\States\Document\AgendaInclusion;
use App\States\Document\CommitteeReferral;
use App\States\Document\CommitteeReport as CommitteeReportState;
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
        ->and($children->pluck('item_number')->all())->toBe(['6.1', '6.2'])
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
        ->and($child?->item_number)->toBe('6.1')
        ->and($child?->reading_number)->toBe(1)
        ->and($document->fresh()->status)->toBeInstanceOf(AgendaInclusion::class);
});

it('toasts and refuses a committee reports attach when the measure has no filed report', function (): void {
    $secretariat = bindAgendaActor('reports-missing');
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-09-01 09:00:00',
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);

    $document = Document::factory()->ofType(DocumentType::ProposedResolution)->create([
        'status' => CommitteeReferral::$name,
        'current_reading' => 1,
        'title' => 'A resolution with no committee report',
    ]);
    $heading = $session->agendaItems()->where('category', 'committee-reports')->whereNull('document_id')->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.documents.store', [$session, $heading]), [
            'document_ids' => [$document->getKey()],
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'sessions.agenda_committee_report_required')
        ->assertSessionHas('error_replacements.title', $document->fresh()->title);

    expect($session->agendaItems()->where('document_id', $document->getKey())->exists())->toBeFalse();
});

it('attaches a measure under committee reports when a plenary report is filed', function (): void {
    $secretariat = bindAgendaActor('reports-filed');
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-09-02 09:00:00',
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);

    $document = Document::factory()->ofType(DocumentType::ProposedResolution)->create([
        'status' => CommitteeReportState::$name,
        'current_reading' => 1,
        'title' => 'A resolution with a filed committee report',
    ]);
    CommitteeReport::factory()->submitted()->create([
        'subject_document_id' => $document->getKey(),
        'recommendation' => 'approve',
    ]);
    $heading = $session->agendaItems()->where('category', 'committee-reports')->whereNull('document_id')->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.documents.store', [$session, $heading]), [
            'document_ids' => [$document->getKey()],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.agenda_documents_bound');

    expect($session->agendaItems()->where('document_id', $document->getKey())->where('parent_id', $heading->getKey())->exists())->toBeTrue();
});

function markHeardInCommittee(Document $document): void
{
    $hearing = LegislativeSession::factory()->adjourned()->create([
        'type' => 'committee-hearing',
    ]);

    AgendaItem::factory()->create([
        'session_id' => $hearing->getKey(),
        'document_id' => $document->getKey(),
        'status' => 'completed',
        'category' => 'referred-measures',
        'completed_at' => now(),
    ]);
}

it('refuses a heard measure on a plenary agenda until a report is filed', function (): void {
    $secretariat = bindAgendaActor('heard-blocked');
    $session = LegislativeSession::factory()->create([
        'type' => 'special',
        'scheduled_start_at' => '2026-09-08 09:00:00',
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);

    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => CommitteeReferral::$name,
        'current_reading' => 1,
        'title' => 'An ordinance heard without a report',
    ]);
    CommitteeReferralModel::factory()->create([
        'document_id' => $document->getKey(),
        'status' => 'in-review',
    ]);
    markHeardInCommittee($document);
    $heading = $session->agendaItems()->where('category', 'unfinished-business')->whereNull('document_id')->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.documents.store', [$session, $heading]), [
            'document_ids' => [$document->getKey()],
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'sessions.agenda_heard_report_required')
        ->assertSessionHas('error_replacements.title', $document->title);

    expect($session->agendaItems()->where('document_id', $document->getKey())->exists())->toBeFalse();
});

it('still allows a heard measure on a committee hearing and a public hearing', function (): void {
    $secretariat = bindAgendaActor('heard-hearing');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => CommitteeReferral::$name,
        'current_reading' => 1,
        'title' => 'An ordinance that can be reheard',
    ]);
    CommitteeReferralModel::factory()->create([
        'document_id' => $document->getKey(),
        'status' => 'in-review',
    ]);
    markHeardInCommittee($document);

    $hearing = LegislativeSession::factory()->create([
        'type' => 'committee-hearing',
        'scheduled_start_at' => '2026-09-09 09:00:00',
    ]);
    $public = LegislativeSession::factory()->create([
        'type' => 'public-hearing',
        'scheduled_start_at' => '2026-09-10 09:00:00',
    ]);
    $agenda = app(AgendaService::class);
    $agenda->prepareStandardTemplate($hearing);
    $agenda->prepareStandardTemplate($public);

    $hearingHeading = $hearing->agendaItems()->where('category', 'referred-measures')->whereNull('document_id')->firstOrFail();
    $publicHeading = $public->agendaItems()->where('category', 'unfinished-business')->whereNull('document_id')->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.documents.store', [$hearing, $hearingHeading]), [
            'document_ids' => [$document->getKey()],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.agenda_documents_bound');

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.documents.store', [$public, $publicHeading]), [
            'document_ids' => [$document->getKey()],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.agenda_documents_bound');

    expect($hearing->agendaItems()->where('document_id', $document->getKey())->exists())->toBeTrue()
        ->and($public->agendaItems()->where('document_id', $document->getKey())->exists())->toBeTrue();
});

it('refuses a hand attach after the report is filed and still queues it automatically', function (): void {
    $secretariat = bindAgendaActor('heard-automatic');
    $chosen = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-09-15 09:00:00',
    ]);
    $next = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-09-12 09:00:00',
        'secretary_id' => $secretariat->getKey(),
    ]);
    $agenda = app(AgendaService::class);
    $agenda->prepareStandardTemplate($chosen);
    $agenda->prepareStandardTemplate($next);

    $document = Document::factory()->ofType(DocumentType::ProposedResolution)->create([
        'status' => CommitteeReportState::$name,
        'current_reading' => 1,
        'title' => 'A resolution filed after the hearing',
    ]);
    CommitteeReport::factory()->submitted()->create([
        'subject_document_id' => $document->getKey(),
        'recommendation' => 'amend',
    ]);
    markHeardInCommittee($document);

    $chosenHeading = $chosen->agendaItems()->where('category', 'committee-reports')->whereNull('document_id')->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.documents.store', [$chosen, $chosenHeading]), [
            'document_ids' => [$document->getKey()],
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'sessions.agenda_heard_report_automatic');

    expect($chosen->agendaItems()->where('document_id', $document->getKey())->exists())->toBeFalse();

    $this->actingAs($secretariat)
        ->post(route('sessions.prepare-agenda', $next))
        ->assertRedirect();

    $reports = $next->fresh()->agendaItems()->where('category', 'committee-reports')->whereNull('document_id')->firstOrFail();

    expect($next->fresh()->agendaItems()->where('document_id', $document->getKey())->where('parent_id', $reports->getKey())->exists())->toBeTrue();
});

it('rejects binding a resolution under third reading', function (): void {
    $secretariat = bindAgendaActor('resolution-third');
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-08-27 09:00:00',
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);

    $document = Document::factory()->ofType(DocumentType::ProposedResolution)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 3,
        'title' => 'A resolution that should not sit on third reading',
    ]);
    $heading = $session->agendaItems()->where('category', 'third-reading')->whereNull('document_id')->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.documents.store', [$session, $heading]), [
            'document_ids' => [$document->getKey()],
        ])
        ->assertUnprocessable();

    expect($session->agendaItems()->where('document_id', $document->getKey())->exists())->toBeFalse();
});
