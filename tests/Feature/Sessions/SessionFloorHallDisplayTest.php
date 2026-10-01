<?php

use App\Enums\Confidentiality;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Events\HallDisplayChanged;
use App\Models\AgendaItem;
use App\Models\Committee;
use App\Models\CommitteeReferral;
use App\Models\CommitteeReport;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Services\Sessions\AgendaService;
use App\States\Document\CommitteeReport as CommitteeReportState;
use App\States\Document\CommitteeReview;
use App\States\Session\InSession;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);

    Storage::fake('local');
});

function hallDisplayActor(UserRole $role, string $suffix): User
{
    return User::factory()->create([
        'email' => "{$role->value}-hall-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => $role !== UserRole::Secretariat,
    ])->assignRole($role->value);
}

function hallDisplayPdf(Document $document, string $suffix): DocumentVersion
{
    $path = "documents/hall-display/{$suffix}.pdf";

    Storage::disk('local')->put($path, "%PDF-1.4\n%%EOF");

    return DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
        'disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'measure.pdf',
        'mime_type' => 'application/pdf',
        'scan_status' => 'skipped',
        'is_current' => true,
    ]);
}

/**
 * @return array{0: Document, 1: AgendaItem, 2: LegislativeSession}
 */
function hallDisplayCommitteeHourItem(User $secretariat, string $suffix): array
{
    $committee = Committee::factory()->create();
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'title' => "Projected report {$suffix}",
        'author_id' => $secretariat->getKey(),
        'confidentiality' => Confidentiality::Internal,
        'status' => CommitteeReview::$name,
        'committee_id' => $committee->getKey(),
        'current_reading' => 1,
    ]);
    $referral = CommitteeReferral::factory()->create([
        'document_id' => $document->getKey(),
        'committee_id' => $committee->getKey(),
        'status' => 'in-review',
        'completed_at' => null,
    ]);

    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => InSession::$name,
        'scheduled_start_at' => now()->setTime(9, 0),
        'actual_start_at' => now()->subHour(),
        'secretary_id' => $secretariat->getKey(),
    ]);

    test()->actingAs($secretariat)->post(route('sessions.prepare-agenda', $session))->assertRedirect();

    CommitteeReport::factory()->create([
        'committee_referral_id' => $referral->getKey(),
        'committee_id' => $committee->getKey(),
        'subject_document_id' => $document->getKey(),
        'recommendation' => 'approve',
        'findings' => 'The trees ordinance should proceed to second reading.',
        'recommendation_notes' => 'No dissenting votes in committee.',
        'report_number' => 'CR-2026-081',
        'status' => 'submitted',
        'submitted_at' => now()->subDay(),
    ]);
    $document->update(['status' => CommitteeReportState::$name]);
    $referral->update(['status' => 'reported', 'completed_at' => now()->subDay()]);

    $heading = $session->agendaItems()->where('category', 'committee-reports')->whereNull('document_id')->firstOrFail();
    app(AgendaService::class)->includeDocument(
        $session,
        $document,
        $secretariat,
        null,
        $heading,
        authorize: false,
    );
    $item = $session->fresh()->agendaItems()->where('document_id', $document->getKey())->firstOrFail();

    $session->agendaItems()->update(['status' => 'pending', 'started_at' => null, 'completed_at' => null]);
    $item->update(['status' => 'in-progress', 'started_at' => now()]);

    return [$document->fresh(), $item->fresh(), $session->fresh()];
}

it('includes default hall_display on the chamber dashboard', function (): void {
    $secretariat = hallDisplayActor(UserRole::Secretariat, 'default');

    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
        'actual_start_at' => now(),
    ]);

    AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'position' => 1,
    ]);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.dashboard', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Dashboard')
            ->where('hall_display.stage', 'item')
            ->where('hall_display.agenda_item_id', null)
            ->where('hall_display.view', null)
            ->where('voting.open', false)
            ->where('can.control_hall_display', true));
});

it('lets the secretariat project and clear a document on the hall display', function (): void {
    Event::fake([HallDisplayChanged::class]);

    $secretariat = hallDisplayActor(UserRole::Secretariat, 'project');

    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
        'actual_start_at' => now(),
    ]);

    $document = Document::factory()->create([
        'author_id' => $secretariat->getKey(),
        'confidentiality' => Confidentiality::Internal,
        'title' => 'Projected Ordinance',
    ]);
    hallDisplayPdf($document, 'project');

    $item = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'document_id' => $document->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'position' => 1,
        'title' => 'Proposed Ordinances',
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.hall.document', $session), [
            'agenda_item_id' => $item->getKey(),
        ])
        ->assertRedirect();

    $session->refresh();

    expect($session->hall_display_stage)->toBe('document')
        ->and($session->hall_display_agenda_item_id)->toBe($item->getKey());

    Event::assertDispatched(HallDisplayChanged::class, function (HallDisplayChanged $event) use ($session, $item): bool {
        return $event->session->is($session)
            && $event->stage === 'document'
            && $event->agendaItemId === $item->getKey();
    });

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.dashboard', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('hall_display.stage', 'document')
            ->where('hall_display.agenda_item_id', $item->getKey())
            ->where('hall_display.view.page', 1));

    $this->actingAs($secretariat)
        ->postJson(route('sessions.hall.view', $session), [
            'zoom' => 1.42,
            'page' => 2,
            'relative_x' => 0.1,
            'relative_y' => 0.35,
        ])
        ->assertOk()
        ->assertJsonPath('view.zoom', 1.42)
        ->assertJsonPath('view.page', 2);

    $session->refresh();

    expect($session->hall_display_view['page'] ?? null)->toBe(2)
        ->and($session->hall_display_view['zoom'] ?? null)->toBe(1.42);

    $this->actingAs($secretariat)
        ->post(route('sessions.hall.item', $session))
        ->assertRedirect();

    $session->refresh();

    expect($session->hall_display_stage)->toBe('item')
        ->and($session->hall_display_agenda_item_id)->toBeNull();

    Event::assertDispatched(HallDisplayChanged::class, function (HallDisplayChanged $event) use ($session): bool {
        return $event->session->is($session)
            && $event->stage === 'item'
            && $event->agendaItemId === null;
    });
});

it('forbids board members from controlling the hall display', function (): void {
    $member = hallDisplayActor(UserRole::BoardMember, 'denied');
    $secretariat = hallDisplayActor(UserRole::Secretariat, 'owner');

    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
        'actual_start_at' => now(),
    ]);

    $document = Document::factory()->create([
        'author_id' => $secretariat->getKey(),
        'confidentiality' => Confidentiality::Internal,
    ]);
    hallDisplayPdf($document, 'denied');

    $item = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'document_id' => $document->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'position' => 1,
    ]);

    $this->actingAs($member)
        ->post(route('sessions.hall.document', $session), [
            'agenda_item_id' => $item->getKey(),
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('sessions.floor.dashboard', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.control_hall_display', false)
            ->where('hall_display.stage', 'item'));
});

it('clears hall document projection when the agenda advances', function (): void {
    Event::fake([HallDisplayChanged::class]);

    $secretariat = hallDisplayActor(UserRole::Secretariat, 'advance');

    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
        'actual_start_at' => now(),
    ]);

    $document = Document::factory()->create([
        'author_id' => $secretariat->getKey(),
        'confidentiality' => Confidentiality::Internal,
    ]);
    hallDisplayPdf($document, 'advance');

    $current = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'document_id' => $document->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'position' => 1,
        'title' => 'Current',
    ]);

    AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'status' => 'pending',
        'position' => 2,
        'title' => 'Next',
    ]);

    $session->forceFill([
        'hall_display_stage' => 'document',
        'hall_display_agenda_item_id' => $current->getKey(),
    ])->save();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.advance', $session))
        ->assertRedirect();

    $session->refresh();

    expect($session->hall_display_stage)->toBe('item')
        ->and($session->hall_display_agenda_item_id)->toBeNull();

    Event::assertDispatched(HallDisplayChanged::class, function (HallDisplayChanged $event) use ($session): bool {
        return $event->session->is($session)
            && $event->stage === 'item'
            && $event->agendaItemId === null;
    });
});

it('lets the secretariat project and clear a committee report on the hall display', function (): void {
    Event::fake([HallDisplayChanged::class]);

    $secretariat = hallDisplayActor(UserRole::Secretariat, 'report');
    [$document, $item, $session] = hallDisplayCommitteeHourItem($secretariat, 'report');
    hallDisplayPdf($document, 'report');

    $this->actingAs($secretariat)
        ->post(route('sessions.hall.report', $session), [
            'agenda_item_id' => $item->getKey(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.hall.report_projected');

    $session->refresh();

    expect($session->hall_display_stage)->toBe('report')
        ->and($session->hall_display_agenda_item_id)->toBe($item->getKey())
        ->and($session->hall_display_view)->toBeNull();

    Event::assertDispatched(HallDisplayChanged::class, function (HallDisplayChanged $event) use ($session, $item): bool {
        return $event->session->is($session)
            && $event->stage === 'report'
            && $event->agendaItemId === $item->getKey();
    });

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.dashboard', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('hall_display.stage', 'report')
            ->where('hall_display.agenda_item_id', $item->getKey())
            ->where('hall_display.view', null)
            ->where('reading_pack', function ($pack) use ($item): bool {
                $row = collect($pack)->firstWhere('id', $item->getKey());

                return is_array($row)
                    && is_array($row['committee_report'] ?? null)
                    && $row['committee_report']['report_number'] === 'CR-2026-081';
            }));

    $this->actingAs($secretariat)
        ->post(route('sessions.hall.document', $session), [
            'agenda_item_id' => $item->getKey(),
        ])
        ->assertRedirect();

    $session->refresh();

    expect($session->hall_display_stage)->toBe('document')
        ->and($session->hall_display_agenda_item_id)->toBe($item->getKey());

    $this->actingAs($secretariat)
        ->post(route('sessions.hall.report', $session), [
            'agenda_item_id' => $item->getKey(),
        ])
        ->assertRedirect();

    $session->refresh();

    expect($session->hall_display_stage)->toBe('report');

    $this->actingAs($secretariat)
        ->post(route('sessions.hall.item', $session))
        ->assertRedirect();

    $session->refresh();

    expect($session->hall_display_stage)->toBe('item')
        ->and($session->hall_display_agenda_item_id)->toBeNull();
});

it('rejects projecting a report when the item has none', function (): void {
    $secretariat = hallDisplayActor(UserRole::Secretariat, 'no-report');

    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
        'actual_start_at' => now(),
    ]);

    $item = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'position' => 1,
    ]);

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->post(route('sessions.hall.report', $session), [
            'agenda_item_id' => $item->getKey(),
        ])
        ->assertRedirect(route('sessions.floor.secretariat', $session))
        ->assertSessionHas('error', 'This agenda item has no committee report to project.');

    expect($session->fresh()->hall_display_stage)->toBe('item');
});

it('forbids board members from projecting a committee report', function (): void {
    $member = hallDisplayActor(UserRole::BoardMember, 'report-denied');
    $secretariat = hallDisplayActor(UserRole::Secretariat, 'report-owner');
    [, $item, $session] = hallDisplayCommitteeHourItem($secretariat, 'report-denied');

    $this->actingAs($member)
        ->post(route('sessions.hall.report', $session), [
            'agenda_item_id' => $item->getKey(),
        ])
        ->assertForbidden();
});

it('clears hall report projection when the agenda advances', function (): void {
    Event::fake([HallDisplayChanged::class]);

    $secretariat = hallDisplayActor(UserRole::Secretariat, 'report-advance');
    [, $current, $session] = hallDisplayCommitteeHourItem($secretariat, 'report-advance');

    AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'status' => 'pending',
        'position' => 99,
        'title' => 'Next',
    ]);

    $session->forceFill([
        'hall_display_stage' => 'report',
        'hall_display_agenda_item_id' => $current->getKey(),
    ])->save();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.advance', $session))
        ->assertRedirect();

    $session->refresh();

    expect($session->hall_display_stage)->toBe('item')
        ->and($session->hall_display_agenda_item_id)->toBeNull();
});

it('keeps voting.open true on the dashboard while a ballot is open', function (): void {
    $secretariat = hallDisplayActor(UserRole::Secretariat, 'vote');

    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
        'actual_start_at' => now(),
    ]);

    AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'position' => 1,
        'requires_vote' => true,
        'voting_round' => 1,
        'voting_open_at' => now(),
    ]);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.dashboard', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('voting.open', true)
            ->where('hall_display.stage', 'item'));
});
