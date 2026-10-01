<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Enums\VoteChoice;
use App\Models\AgendaItem;
use App\Models\Committee;
use App\Models\CommitteeReferral;
use App\Models\CommitteeReport;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Models\Vote;
use App\Services\Documents\CommitteeReferralService;
use App\Services\Sessions\AgendaService;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Document\AgendaInclusion;
use App\States\Document\Approved;
use App\States\Document\CommitteeReferral as CommitteeReferralState;
use App\States\Document\CommitteeReport as CommitteeReportState;
use App\States\Document\CommitteeReview;
use App\States\Document\ReadingDeliberation;
use App\States\Document\Rejected;
use App\States\Session\Adjourned;
use App\States\Session\Draft;
use App\States\Session\InSession;
use App\States\Session\Scheduled;
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

function calendarActor(UserRole $role, string $suffix): User
{
    return User::factory()->create([
        'email' => "{$role->value}-calendar-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole($role->value);
}

function calendarReadyMeasure(
    string $title,
    string $status = 'agenda-inclusion',
    DocumentType $type = DocumentType::ProposedOrdinance,
): Document {
    return Document::factory()->ofType($type)->create([
        'status' => $status,
        'current_reading' => $status === AgendaInclusion::$name ? 2 : null,
        'title' => $title,
    ]);
}

/**
 * @return array{0: LegislativeSession, 1: AgendaItem, 2: Document, 3: AgendaItem}
 */
function calendarSittingWithUnassigned(
    Document $document,
    User $actor,
    string $sessionStatus = 'draft',
): array {
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => $sessionStatus,
        'scheduled_start_at' => now()->addDay()->setTime(9, 0),
        'actual_start_at' => $sessionStatus === InSession::$name ? now() : null,
        'secretary_id' => $actor->getKey(),
    ]);
    $agenda = app(AgendaService::class);
    $agenda->prepareStandardTemplate($session);

    $unassigned = $session->agendaItems()
        ->where('category', 'unassigned-business')
        ->whereNull('document_id')
        ->firstOrFail();

    $agenda->bindDocuments($session, $unassigned, [$document->getKey()], $actor);
    $item = $session->agendaItems()->where('document_id', $document->getKey())->firstOrFail();

    return [$session->fresh(), $item->fresh(), $document->fresh(), $unassigned];
}

/**
 * @return array{0: LegislativeSession, 1: AgendaItem, 2: Document, 3: Committee}
 */
function calendarSittingAfterFirstReadingReferral(
    Document $document,
    User $actor,
    ?Committee $committee = null,
): array {
    $committee ??= Committee::factory()->create();

    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => InSession::$name,
        'scheduled_start_at' => now()->addDay()->setTime(9, 0),
        'actual_start_at' => now(),
        'secretary_id' => $actor->getKey(),
    ]);
    $agenda = app(AgendaService::class);
    $agenda->prepareStandardTemplate($session);

    $firstReading = $session->agendaItems()
        ->where('category', 'first-reading')
        ->whereNull('document_id')
        ->firstOrFail();

    $agenda->bindDocuments($session, $firstReading, [$document->getKey()], $actor);
    $item = $session->agendaItems()->where('document_id', $document->getKey())->firstOrFail();

    $session->agendaItems()->update([
        'status' => 'pending',
        'started_at' => null,
        'completed_at' => null,
    ]);
    $item->update([
        'status' => 'in-progress',
        'started_at' => now(),
    ]);

    return [$session->fresh(), $item->fresh(), $document->fresh(), $committee];
}

function completeFirstReadingSection(LegislativeSession $session, AgendaItem $item): void
{
    $item->update([
        'status' => 'completed',
        'completed_at' => now(),
        'started_at' => $item->started_at ?? now()->subMinute(),
    ]);

    $session->agendaItems()
        ->where('category', 'first-reading')
        ->whereNull('document_id')
        ->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
}

it('places an unassigned ready measure under business for the day on second reading', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'second-reading');
    $document = calendarReadyMeasure('An ordinance on market stalls');
    [$session, $item] = calendarSittingWithUnassigned($document, $secretariat);

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.second-reading', [$session, $item]))
        ->assertRedirect();

    $item = $item->fresh();
    $heading = $session->agendaItems()->where('category', 'business-for-the-day')->whereNull('document_id')->firstOrFail();
    $document = $document->fresh();

    expect($item->parent_id)->toBe($heading->getKey())
        ->and($item->category)->toBe('second-reading')
        ->and($item->reading_number)->toBe(2)
        ->and($item->status)->toBe('pending')
        ->and($document->status)->toBeInstanceOf(AgendaInclusion::class)
        ->and($document->current_reading)->toBe(2)
        ->and($document->status)->not->toBeInstanceOf(ReadingDeliberation::class);
});

it('rejects second reading after business for the day is closed', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'bft-closed');
    $document = calendarReadyMeasure('An ordinance on drainage');
    [$session, $item] = calendarSittingWithUnassigned($document, $secretariat, InSession::$name);

    $heading = $session->agendaItems()->where('category', 'business-for-the-day')->whereNull('document_id')->firstOrFail();
    $heading->update(['status' => 'completed']);

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.second-reading', [$session, $item]))
        ->assertRedirect()
        ->assertSessionHas('error', 'sessions.calendar.bft_closed');

    expect($item->fresh()->category)->toBe('unassigned-business');
});

it('postpones a live unassigned item onto the next sitting unfinished business', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'postpone-next');
    $document = calendarReadyMeasure('An ordinance on street vendors');
    [$session, $item] = calendarSittingWithUnassigned($document, $secretariat, InSession::$name);

    $next = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(8)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);
    app(AgendaService::class)->prepareStandardTemplate($next);

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.postpone', [$session, $item]))
        ->assertRedirect();

    $item = $item->fresh();
    $unfinished = $next->agendaItems()
        ->where('category', 'unfinished-business')
        ->where('document_id', $document->getKey())
        ->first();

    expect($item->status)->toBe('postponed')
        ->and($item->carried_to_session_id)->toBe($next->getKey())
        ->and($unfinished)->not->toBeNull()
        ->and($unfinished?->reading_number)->toBe(2)
        ->and($unfinished?->parent_id)->toBe(
            $next->agendaItems()->where('category', 'unfinished-business')->whereNull('document_id')->value('id')
        );
});

it('queues a postpone when no next sitting exists yet', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'postpone-queue');
    $document = calendarReadyMeasure('An ordinance on street lighting');
    [$session, $item] = calendarSittingWithUnassigned($document, $secretariat, InSession::$name);

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.postpone', [$session, $item]))
        ->assertRedirect();

    expect($item->fresh()->status)->toBe('postponed')
        ->and($item->fresh()->carried_to_session_id)->toBeNull();

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk();
});

it('consumes the postpone queue when the next sitting prepares its agenda', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'consume-queue');
    $document = calendarReadyMeasure('An ordinance on public markets');
    [$session, $item] = calendarSittingWithUnassigned($document, $secretariat, InSession::$name);

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.postpone', [$session, $item]))
        ->assertRedirect();

    $next = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(8)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.prepare-agenda', $next))
        ->assertRedirect();

    $carried = $next->agendaItems()
        ->where('document_id', $document->getKey())
        ->where('category', 'unfinished-business')
        ->first();

    expect($carried)->not->toBeNull()
        ->and($item->fresh()->carried_to_session_id)->toBe($next->getKey())
        ->and($carried?->reading_number)->toBe(2);
});

it('carries leftover pool and unfinished business-for-the-day items on adjournment', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'adjourn-carry');
    $presiding = calendarActor(UserRole::PresidingOfficer, 'adjourn-carry');
    $pool = calendarReadyMeasure('An ordinance still unassigned');
    $onDay = calendarReadyMeasure('An ordinance already on the day', AgendaInclusion::$name);

    [$session, $poolItem] = calendarSittingWithUnassigned($pool, $secretariat, Scheduled::$name);

    $bft = $session->agendaItems()->where('category', 'business-for-the-day')->whereNull('document_id')->firstOrFail();
    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.documents.store', [$session, $bft]), [
            'document_ids' => [$onDay->getKey()],
        ])
        ->assertRedirect();
    $dayItem = $session->agendaItems()->where('document_id', $onDay->getKey())->firstOrFail();

    $next = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(8)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);
    app(AgendaService::class)->prepareStandardTemplate($next);

    $this->actingAs($secretariat)->post(route('sessions.start', $session))->assertRedirect();
    $this->actingAs($presiding)->post(route('sessions.adjourn', $session))->assertRedirect();

    expect($session->fresh()->status)->toBeInstanceOf(Adjourned::class)
        ->and($poolItem->fresh()->status)->toBe('postponed')
        ->and($dayItem->fresh()->status)->toBe('postponed')
        ->and($next->agendaItems()->where('document_id', $pool->getKey())->where('category', 'unfinished-business')->exists())->toBeTrue()
        ->and($next->agendaItems()->where('document_id', $onDay->getKey())->where('category', 'unfinished-business')->exists())->toBeTrue();
});

it('does not carry first-reading items on adjournment', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'first-reading-stay');
    $presiding = calendarActor(UserRole::PresidingOfficer, 'first-reading-stay');
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Scheduled::$name,
        'scheduled_start_at' => now()->addDay()->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);

    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance on first reading only',
    ]);
    $first = $session->agendaItems()->where('category', 'first-reading')->whereNull('document_id')->firstOrFail();
    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.documents.store', [$session, $first]), [
            'document_ids' => [$document->getKey()],
        ])
        ->assertRedirect();

    $next = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(8)->setTime(9, 0),
    ]);
    app(AgendaService::class)->prepareStandardTemplate($next);

    $this->actingAs($secretariat)->post(route('sessions.start', $session))->assertRedirect();
    $this->actingAs($presiding)->post(route('sessions.adjourn', $session))->assertRedirect();

    $item = $session->agendaItems()->where('document_id', $document->getKey())->firstOrFail();

    expect($item->fresh()->status)->not->toBe('postponed')
        ->and($next->agendaItems()->where('document_id', $document->getKey())->exists())->toBeFalse();
});

it('undoes a postpone until the next sitting has started', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'undo');
    $document = calendarReadyMeasure('An ordinance to restore');
    [$session, $item] = calendarSittingWithUnassigned($document, $secretariat, InSession::$name);

    $next = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(8)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);
    app(AgendaService::class)->prepareStandardTemplate($next);

    $this->actingAs($secretariat)->post(route('sessions.agenda.postpone', [$session, $item]))->assertRedirect();

    $carriedId = $item->fresh()->carried_to_agenda_item_id;

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.undo-postpone', [$session, $item]))
        ->assertRedirect();

    expect($item->fresh()->status)->toBe('pending')
        ->and($item->fresh()->carried_to_session_id)->toBeNull()
        ->and(AgendaItem::query()->whereKey($carriedId)->exists())->toBeFalse();
});

it('rejects undo after the next sitting has started', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'undo-late');
    $document = calendarReadyMeasure('An ordinance frozen on the next sitting');
    [$session, $item] = calendarSittingWithUnassigned($document, $secretariat, InSession::$name);

    $next = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(8)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);
    app(AgendaService::class)->prepareStandardTemplate($next);

    $this->actingAs($secretariat)->post(route('sessions.agenda.postpone', [$session, $item]))->assertRedirect();

    $next->forceFill([
        'status' => InSession::$name,
        'actual_start_at' => now(),
    ])->save();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.undo-postpone', [$session, $item]))
        ->assertRedirect()
        ->assertSessionHas('error', 'sessions.calendar.undo_next_started');

    expect($item->fresh()->status)->toBe('postponed');
});

it('does not offer second reading on unfinished carry-overs', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'unfinished-flags');
    $document = calendarReadyMeasure('An ordinance carried over');
    [$previous, $item] = calendarSittingWithUnassigned($document, $secretariat, InSession::$name);

    $next = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(8)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);
    app(AgendaService::class)->prepareStandardTemplate($next);

    $this->actingAs($secretariat)->post(route('sessions.agenda.postpone', [$previous, $item]))->assertRedirect();

    $next->forceFill([
        'status' => InSession::$name,
        'actual_start_at' => now(),
    ])->save();
    $carried = $next->agendaItems()->where('document_id', $document->getKey())->firstOrFail();

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $next))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Secretariat')
            ->has('reading_pack')
            ->where('reading_pack', function ($pack) use ($carried): bool {
                $row = collect($pack)->firstWhere('id', $carried->getKey());

                return is_array($row)
                    && $row['can_second_reading'] === false
                    && $row['can_postpone'] === true;
            })
        );
});

it('forbids postpone to a board member', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'member-forbid-sec');
    $member = calendarActor(UserRole::BoardMember, 'member-forbid');
    $document = calendarReadyMeasure('An ordinance members cannot postpone');
    [$session, $item] = calendarSittingWithUnassigned($document, $secretariat, InSession::$name);

    $this->actingAs($member)
        ->post(route('sessions.agenda.postpone', [$session, $item]))
        ->assertForbidden();
});

it('lists a first-reading measure on the calendar from the start of the sitting', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'docket-from-start');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance on the opening calendar',
        'author_id' => $secretariat->getKey(),
    ]);
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => InSession::$name,
        'scheduled_start_at' => now()->addDay()->setTime(9, 0),
        'actual_start_at' => now(),
        'secretary_id' => $secretariat->getKey(),
    ]);
    $agenda = app(AgendaService::class);
    $agenda->prepareStandardTemplate($session);
    $firstReading = $session->agendaItems()
        ->where('category', 'first-reading')
        ->whereNull('document_id')
        ->firstOrFail();
    $agenda->bindDocuments($session, $firstReading, [$document->getKey()], $secretariat);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Secretariat')
            ->has('calendar_docket')
            ->where('calendar_docket', function ($docket) use ($document): bool {
                $row = collect($docket)->firstWhere('document_id', $document->getKey());

                return is_array($row)
                    && $row['agenda_item_id'] === null
                    && $row['can_second_reading'] === false
                    && $row['can_postpone'] === false
                    && $row['can_refer'] === true
                    && $row['can_edit_referral'] === false
                    && $row['title'] === $document->title;
            })
        );
});

it('offers calendar routing after referral even while first reading is still on the floor', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'docket-referred-early');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance referred before first reading ends',
        'author_id' => $secretariat->getKey(),
    ]);
    [$session, $item, $document, $committee] = calendarSittingAfterFirstReadingReferral($document, $secretariat);

    $this->actingAs($secretariat)
        ->post(route('sessions.floor.refer', $session), [
            'agenda_item_id' => $item->getKey(),
            'committee_id' => $committee->getKey(),
        ])
        ->assertRedirect();

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('calendar_docket', function ($docket) use ($document): bool {
                $row = collect($docket)->firstWhere('document_id', $document->getKey());

                return is_array($row)
                    && $row['can_second_reading'] === true
                    && $row['can_postpone'] === true
                    && $row['can_refer'] === false
                    && $row['can_edit_referral'] === true;
            })
        );
});

it('lists a referred first-reading measure on the calendar after first reading is done', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'docket-referred');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance after first reading',
        'author_id' => $secretariat->getKey(),
    ]);
    [$session, $item, $document, $committee] = calendarSittingAfterFirstReadingReferral($document, $secretariat);

    $this->actingAs($secretariat)
        ->post(route('sessions.floor.refer', $session), [
            'agenda_item_id' => $item->getKey(),
            'committee_id' => $committee->getKey(),
        ])
        ->assertRedirect();

    completeFirstReadingSection($session, $item);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Secretariat')
            ->has('calendar_docket')
            ->where('calendar_docket', function ($docket) use ($document): bool {
                $row = collect($docket)->firstWhere('document_id', $document->getKey());

                return is_array($row)
                    && $row['agenda_item_id'] === null
                    && $row['can_second_reading'] === true
                    && $row['can_postpone'] === true
                    && $row['can_refer'] === false
                    && $row['can_edit_referral'] === true
                    && $row['title'] === $document->title;
            })
        );
});

it('lists an unreferred first-reading measure without calendar routing actions', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'docket-unreferred');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance never referred',
        'author_id' => $secretariat->getKey(),
    ]);
    [$session, $item] = calendarSittingAfterFirstReadingReferral($document, $secretariat);
    completeFirstReadingSection($session, $item);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('calendar_docket', function ($docket) use ($document): bool {
                $row = collect($docket)->firstWhere('document_id', $document->getKey());

                return is_array($row)
                    && $row['can_second_reading'] === false
                    && $row['can_postpone'] === false
                    && $row['can_refer'] === true;
            })
        );
});

it('lists a committee hour report measure on the calendar docket', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'docket-reports');
    $committee = Committee::factory()->create();
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'title' => 'Sample Ordinance',
        'status' => CommitteeReview::$name,
        'committee_id' => $committee->getKey(),
        'current_reading' => 1,
        'author_id' => $secretariat->getKey(),
    ]);
    $referral = CommitteeReferral::factory()->create([
        'document_id' => $document->getKey(),
        'committee_id' => $committee->getKey(),
        'status' => 'reported',
        'completed_at' => now()->subDay(),
    ]);
    CommitteeReport::factory()->create([
        'committee_referral_id' => $referral->getKey(),
        'committee_id' => $committee->getKey(),
        'subject_document_id' => $document->getKey(),
        'recommendation' => 'approve',
        'status' => 'submitted',
        'submitted_at' => now()->subDay(),
    ]);
    $document->update(['status' => CommitteeReportState::$name]);

    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => InSession::$name,
        'scheduled_start_at' => now()->setTime(9, 0),
        'actual_start_at' => now()->subHour(),
        'secretary_id' => $secretariat->getKey(),
    ]);
    $agenda = app(AgendaService::class);
    $agenda->prepareStandardTemplate($session);
    $heading = $session->agendaItems()
        ->where('category', 'committee-reports')
        ->whereNull('document_id')
        ->firstOrFail();
    $agenda->includeDocument($session, $document, $secretariat, null, $heading, authorize: false);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Secretariat')
            ->where('calendar_docket', function ($docket) use ($document): bool {
                $row = collect($docket)->firstWhere('document_id', $document->getKey());

                return is_array($row)
                    && $row['title'] === $document->title
                    && $row['can_second_reading'] === true
                    && $row['can_postpone'] === true;
            })
        );
});

it('lets the body send a committee hour report to second reading', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'reports-second');
    $committee = Committee::factory()->create();
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'title' => 'Sample Ordinance',
        'status' => CommitteeReview::$name,
        'committee_id' => $committee->getKey(),
        'current_reading' => 1,
        'author_id' => $secretariat->getKey(),
    ]);
    $referral = CommitteeReferral::factory()->create([
        'document_id' => $document->getKey(),
        'committee_id' => $committee->getKey(),
        'status' => 'reported',
        'completed_at' => now()->subDay(),
    ]);
    CommitteeReport::factory()->create([
        'committee_referral_id' => $referral->getKey(),
        'committee_id' => $committee->getKey(),
        'subject_document_id' => $document->getKey(),
        'recommendation' => 'approve',
        'status' => 'submitted',
        'submitted_at' => now()->subDay(),
    ]);
    $document->update(['status' => CommitteeReportState::$name]);

    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => InSession::$name,
        'scheduled_start_at' => now()->setTime(9, 0),
        'actual_start_at' => now()->subHour(),
        'secretary_id' => $secretariat->getKey(),
    ]);
    $agenda = app(AgendaService::class);
    $agenda->prepareStandardTemplate($session);
    $heading = $session->agendaItems()
        ->where('category', 'committee-reports')
        ->whereNull('document_id')
        ->firstOrFail();
    $agenda->includeDocument($session, $document, $secretariat, null, $heading, authorize: false);
    $item = $session->agendaItems()->where('document_id', $document->getKey())->firstOrFail();

    $this->actingAs($secretariat)
        ->get(route('sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('session.agenda_items', function ($items) use ($item): bool {
                $row = collect($items)->firstWhere('id', $item->getKey());

                return is_array($row) && $row['can_second_reading'] === true;
            })
            ->where('calendar_docket', function ($docket) use ($document): bool {
                $row = collect($docket)->firstWhere('document_id', $document->getKey());

                return is_array($row) && $row['can_second_reading'] === true;
            })
        );

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.second-reading', [$session, $item]))
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.calendar.second_reading_done');

    $business = $session->agendaItems()->where('category', 'business-for-the-day')->whereNull('document_id')->firstOrFail();
    $unassigned = $session->agendaItems()->where('category', 'unassigned-business')->whereNull('document_id')->firstOrFail();
    $placed = $session->agendaItems()
        ->where('document_id', $document->getKey())
        ->where('reading_number', 2)
        ->first();

    expect($item->fresh()?->status)->toBe('completed')
        ->and($item->fresh()?->parent_id)->not->toBe($business->getKey())
        ->and($placed)->not->toBeNull()
        ->and($placed?->getKey())->not->toBe($item->getKey())
        ->and($placed?->parent_id)->toBe($business->getKey())
        ->and($placed?->category)->toBe('second-reading')
        ->and($placed?->item_number)->toBe('8.2.1')
        ->and($placed?->position)->toBeGreaterThan($business->fresh()->position)
        ->and($placed?->position)->toBeLessThan($unassigned->fresh()->position)
        ->and($document->fresh()->status)->toBeInstanceOf(AgendaInclusion::class)
        ->and($document->fresh()->current_reading)->toBe(2);
});

it('sends a committee hour report to second reading from the calendar list', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'reports-docket-second');
    $committee = Committee::factory()->create();
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'title' => 'Sample Ordinance',
        'status' => CommitteeReview::$name,
        'committee_id' => $committee->getKey(),
        'current_reading' => 1,
        'author_id' => $secretariat->getKey(),
    ]);
    $referral = CommitteeReferral::factory()->create([
        'document_id' => $document->getKey(),
        'committee_id' => $committee->getKey(),
        'status' => 'reported',
        'completed_at' => now()->subDay(),
    ]);
    CommitteeReport::factory()->create([
        'committee_referral_id' => $referral->getKey(),
        'committee_id' => $committee->getKey(),
        'subject_document_id' => $document->getKey(),
        'recommendation' => 'approve',
        'status' => 'submitted',
        'submitted_at' => now()->subDay(),
    ]);
    $document->update(['status' => CommitteeReportState::$name]);

    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => InSession::$name,
        'scheduled_start_at' => now()->setTime(9, 0),
        'actual_start_at' => now()->subHour(),
        'secretary_id' => $secretariat->getKey(),
    ]);
    $agenda = app(AgendaService::class);
    $agenda->prepareStandardTemplate($session);
    $heading = $session->agendaItems()
        ->where('category', 'committee-reports')
        ->whereNull('document_id')
        ->firstOrFail();
    $agenda->includeDocument($session, $document, $secretariat, null, $heading, authorize: false);

    $this->actingAs($secretariat)
        ->post(route('sessions.calendar.second-reading', $session), [
            'document_id' => $document->getKey(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.calendar.second_reading_done');

    $business = $session->agendaItems()->where('category', 'business-for-the-day')->whereNull('document_id')->firstOrFail();
    $unassigned = $session->agendaItems()->where('category', 'unassigned-business')->whereNull('document_id')->firstOrFail();
    $placed = $session->agendaItems()->where('document_id', $document->getKey())->where('reading_number', 2)->first();

    expect($placed?->parent_id)->toBe($business->getKey())
        ->and($placed?->item_number)->toBe('8.2.1')
        ->and($placed?->position)->toBeGreaterThan($business->fresh()->position)
        ->and($placed?->position)->toBeLessThan($unassigned->fresh()->position)
        ->and($document->fresh()->current_reading)->toBe(2);
});

it('does not list a register measure that was not referred on this sitting', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'docket-pool');
    $document = calendarReadyMeasure('An ordinance waiting for the calendar');
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => InSession::$name,
        'scheduled_start_at' => now()->addDay()->setTime(9, 0),
        'actual_start_at' => now(),
        'secretary_id' => $secretariat->getKey(),
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Secretariat')
            ->where('calendar_docket', function ($docket) use ($document): bool {
                return collect($docket)->firstWhere('document_id', $document->getKey()) === null;
            })
        );
});

it('places a referred first-reading measure onto business for the day', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'pool-second-reading');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance from first reading',
        'author_id' => $secretariat->getKey(),
    ]);
    [$session, $item, $document, $committee] = calendarSittingAfterFirstReadingReferral($document, $secretariat);

    $this->actingAs($secretariat)
        ->post(route('sessions.floor.refer', $session), [
            'agenda_item_id' => $item->getKey(),
            'committee_id' => $committee->getKey(),
        ])
        ->assertRedirect();

    completeFirstReadingSection($session, $item);

    $this->actingAs($secretariat)
        ->post(route('sessions.calendar.second-reading', $session), [
            'document_id' => $document->getKey(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.calendar.second_reading_done');

    $heading = $session->agendaItems()->where('category', 'business-for-the-day')->whereNull('document_id')->firstOrFail();
    $placed = $session->agendaItems()
        ->where('document_id', $document->getKey())
        ->where('parent_id', $heading->getKey())
        ->first();
    $referral = $document->referrals()->first();

    expect($placed)->not->toBeNull()
        ->and($placed?->category)->toBe('second-reading')
        ->and($placed?->reading_number)->toBe(2)
        ->and($item->fresh()->status)->toBe('completed')
        ->and($document->fresh()->status)->toBeInstanceOf(AgendaInclusion::class)
        ->and($document->fresh()->current_reading)->toBe(2)
        ->and($referral?->status)->toBe('closed')
        ->and($referral?->completed_at)->not->toBeNull();
});

it('places a referred first-reading measure on second reading without leaving first reading', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'second-reading-during-first');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance calendared during first reading',
        'author_id' => $secretariat->getKey(),
    ]);
    [$session, $item, $document, $committee] = calendarSittingAfterFirstReadingReferral($document, $secretariat);

    $this->actingAs($secretariat)
        ->post(route('sessions.floor.refer', $session), [
            'agenda_item_id' => $item->getKey(),
            'committee_id' => $committee->getKey(),
        ])
        ->assertRedirect();

    $this->actingAs($secretariat)
        ->post(route('sessions.calendar.second-reading', $session), [
            'document_id' => $document->getKey(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.calendar.second_reading_done');

    $heading = $session->agendaItems()->where('category', 'business-for-the-day')->whereNull('document_id')->firstOrFail();
    $placed = $session->agendaItems()
        ->where('document_id', $document->getKey())
        ->where('parent_id', $heading->getKey())
        ->first();

    expect($placed)->not->toBeNull()
        ->and($placed?->reading_number)->toBe(2)
        ->and($item->fresh()->status)->toBe('in-progress')
        ->and($item->fresh()->parent_id)->not->toBe($heading->getKey());
});

it('postpones a referred first-reading measure onto the next sitting unfinished business', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'referred-postpone');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance postponed after first reading',
        'author_id' => $secretariat->getKey(),
    ]);
    [$session, $item, $document, $committee] = calendarSittingAfterFirstReadingReferral($document, $secretariat);

    $this->actingAs($secretariat)
        ->post(route('sessions.floor.refer', $session), [
            'agenda_item_id' => $item->getKey(),
            'committee_id' => $committee->getKey(),
        ])
        ->assertRedirect();

    completeFirstReadingSection($session, $item);

    $next = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(8)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);
    app(AgendaService::class)->prepareStandardTemplate($next);

    $this->actingAs($secretariat)
        ->post(route('sessions.calendar.postpone', $session), [
            'document_id' => $document->getKey(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.calendar.postponed');

    $unfinished = $next->agendaItems()
        ->where('category', 'unfinished-business')
        ->where('document_id', $document->getKey())
        ->firstOrFail();

    expect($document->referrals()->first()?->completed_at)->toBeNull()
        ->and($document->referrals()->first()?->hearing_waived)->toBeTrue();

    $next->forceFill([
        'status' => InSession::$name,
        'actual_start_at' => now(),
    ])->save();

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $next))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('calendar_docket', function ($docket) use ($document): bool {
                $row = collect($docket)->firstWhere('document_id', $document->getKey());

                return is_array($row)
                    && $row['can_second_reading'] === true
                    && $row['can_postpone'] === true;
            })
        );

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.second-reading', [$next, $unfinished]))
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.calendar.second_reading_done');

    expect($unfinished->fresh()->category)->toBe('second-reading')
        ->and($document->referrals()->first()?->status)->toBe('closed');
});

it('returns a referred first-reading measure to the calendar instead of unassigned business on undo', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'referred-undo');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance restored after postpone',
        'author_id' => $secretariat->getKey(),
    ]);
    [$session, $item, $document, $committee] = calendarSittingAfterFirstReadingReferral($document, $secretariat);

    $this->actingAs($secretariat)
        ->post(route('sessions.floor.refer', $session), [
            'agenda_item_id' => $item->getKey(),
            'committee_id' => $committee->getKey(),
        ])
        ->assertRedirect();

    completeFirstReadingSection($session, $item);

    $next = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(8)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);
    app(AgendaService::class)->prepareStandardTemplate($next);

    $this->actingAs($secretariat)
        ->post(route('sessions.calendar.postpone', $session), [
            'document_id' => $document->getKey(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.calendar.postponed');

    $parked = $session->agendaItems()
        ->where('document_id', $document->getKey())
        ->where('status', 'postponed')
        ->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.undo-postpone', [$session, $parked]))
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.calendar.undo_done');

    expect($session->agendaItems()->where('document_id', $document->getKey())->where('status', 'postponed')->exists())->toBeFalse()
        ->and($next->agendaItems()->where('document_id', $document->getKey())->exists())->toBeFalse()
        ->and($session->agendaItems()->where('document_id', $document->getKey())->where('category', 'unassigned-business')->exists())->toBeFalse();

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('calendar_docket', function ($docket) use ($document): bool {
                $row = collect($docket)->firstWhere('document_id', $document->getKey());

                return is_array($row)
                    && $row['can_second_reading'] === true
                    && $row['can_postpone'] === true;
            })
        );
});

it('hides same-sitting buttons when the first referral has a meeting date', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'dated-referral');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance sent to hearing',
        'author_id' => $secretariat->getKey(),
    ]);
    [$session, $item, $document, $committee] = calendarSittingAfterFirstReadingReferral($document, $secretariat);
    $meetingOn = now()->addDays(4)->toDateString();

    $this->actingAs($secretariat)
        ->post(route('sessions.floor.refer', $session), [
            'agenda_item_id' => $item->getKey(),
            'committee_id' => $committee->getKey(),
            'meeting_on' => $meetingOn,
        ])
        ->assertRedirect();

    completeFirstReadingSection($session, $item);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('calendar_docket', function ($docket) use ($document): bool {
                $row = collect($docket)->firstWhere('document_id', $document->getKey());

                return is_array($row)
                    && $row['can_second_reading'] === false
                    && $row['can_postpone'] === false;
            })
        );

    $this->actingAs($secretariat)
        ->put(route('documents.referral.update', $document), [
            'committee_id' => $committee->getKey(),
            'meeting_on' => null,
        ])
        ->assertRedirect();

    expect($document->referrals()->first()?->hearing_waived)->toBeFalse()
        ->and($document->referrals()->first()?->meeting_on)->toBeNull();

    $hearing = LegislativeSession::factory()->create([
        'type' => 'committee-hearing',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(10)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.prepare-agenda', $hearing))
        ->assertRedirect();

    expect($hearing->agendaItems()->where('document_id', $document->getKey())->exists())->toBeTrue();
});

it('keeps a blank-date referral off committee hearings after the date is filled in', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'waived-referral');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance taken up today',
        'author_id' => $secretariat->getKey(),
    ]);
    [$session, $item, $document, $committee] = calendarSittingAfterFirstReadingReferral($document, $secretariat);

    $this->actingAs($secretariat)
        ->post(route('sessions.floor.refer', $session), [
            'agenda_item_id' => $item->getKey(),
            'committee_id' => $committee->getKey(),
        ])
        ->assertRedirect();

    $this->actingAs($secretariat)
        ->put(route('documents.referral.update', $document), [
            'committee_id' => $committee->getKey(),
            'meeting_on' => now()->addDays(3)->toDateString(),
        ])
        ->assertRedirect();

    expect($document->referrals()->first()?->hearing_waived)->toBeTrue();

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('calendar_docket', function ($docket) use ($document): bool {
                $row = collect($docket)->firstWhere('document_id', $document->getKey());

                return is_array($row)
                    && $row['can_second_reading'] === true
                    && $row['can_postpone'] === true;
            })
        );

    $hearing = LegislativeSession::factory()->create([
        'type' => 'committee-hearing',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(2)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.prepare-agenda', $hearing))
        ->assertRedirect();

    expect($hearing->agendaItems()->where('document_id', $document->getKey())->exists())->toBeFalse();
});

/**
 * @return array{0: LegislativeSession, 1: AgendaItem, 2: Document}
 */
function calendarSecondReadingOnFloor(
    User $secretariat,
    string $title,
    DocumentType $type = DocumentType::ProposedOrdinance,
): array {
    $document = calendarReadyMeasure($title, AgendaInclusion::$name, $type);
    [$session, $item] = calendarSittingWithUnassigned($document, $secretariat, InSession::$name);

    test()->actingAs($secretariat)
        ->post(route('sessions.agenda.second-reading', [$session, $item]))
        ->assertRedirect();

    $item = $item->fresh();
    $session->agendaItems()->update([
        'status' => 'pending',
        'started_at' => null,
        'completed_at' => null,
    ]);
    $item->update([
        'status' => 'in-progress',
        'started_at' => now(),
    ]);

    return [$session->fresh(), $item->fresh(), $document->fresh()];
}

/**
 * @return array{0: LegislativeSession, 1: AgendaItem, 2: Document}
 */
function calendarThirdReadingOnFloor(User $secretariat, string $title): array
{
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 3,
        'title' => $title,
        'author_id' => $secretariat->getKey(),
    ]);

    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => InSession::$name,
        'scheduled_start_at' => now()->addDay()->setTime(9, 0),
        'actual_start_at' => now(),
        'secretary_id' => $secretariat->getKey(),
    ]);
    $agenda = app(AgendaService::class);
    $agenda->prepareStandardTemplate($session);

    $heading = $session->agendaItems()
        ->where('category', 'third-reading')
        ->whereNull('document_id')
        ->firstOrFail();

    $agenda->bindDocuments($session, $heading, [$document->getKey()], $secretariat);

    $item = $session->agendaItems()->where('document_id', $document->getKey())->firstOrFail();

    $session->agendaItems()->update([
        'status' => 'pending',
        'started_at' => null,
        'completed_at' => null,
    ]);
    $item->update([
        'status' => 'in-progress',
        'started_at' => now(),
    ]);

    return [$session->fresh(), $item->fresh(), $document->fresh()];
}

function holdFloorVote(
    LegislativeSession $session,
    AgendaItem $item,
    User $presiding,
    User $voter,
    VoteChoice $choice,
): void {
    test()->actingAs($presiding)
        ->post(route('sessions.voting.open', $session), [
            'agenda_item_id' => $item->getKey(),
        ])
        ->assertRedirect();

    $item = $item->fresh();

    test()->actingAs($voter)
        ->postJson(route('sessions.voting.cast', $session), [
            'agenda_item_id' => $item->getKey(),
            'choice' => $choice->value,
            'voting_round' => (int) $item->voting_round,
        ])
        ->assertCreated();

    test()->actingAs($presiding)
        ->post(route('sessions.voting.close', $session), [
            'agenda_item_id' => $item->getKey(),
        ])
        ->assertRedirect();
}

it('blocks advancing a second-reading measure until a vote is held', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'second-vote-block');
    [$session, $item] = calendarSecondReadingOnFloor($secretariat, 'An ordinance that needs a vote');

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->post(route('sessions.agenda.advance', $session))
        ->assertRedirect(route('sessions.floor.secretariat', $session))
        ->assertSessionHas('error', 'sessions.advance_blocked_second_reading_vote');

    expect($item->fresh()->status)->toBe('in-progress');
});

it('blocks advancing a third-reading measure until a final vote is held', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'third-vote-block');
    [$session, $item] = calendarThirdReadingOnFloor($secretariat, 'An ordinance on third reading that needs a vote');

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->post(route('sessions.agenda.advance', $session))
        ->assertRedirect(route('sessions.floor.secretariat', $session))
        ->assertSessionHas('error', 'sessions.advance_blocked_third_reading_vote');

    expect($item->fresh()->status)->toBe('in-progress');
});

it('lets the floor leave a third-reading measure after a final vote', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'third-vote-held');
    $presiding = calendarActor(UserRole::PresidingOfficer, 'third-vote-held');
    $member = calendarActor(UserRole::BoardMember, 'third-vote-held');
    [$session, $item] = calendarThirdReadingOnFloor($secretariat, 'An ordinance that received a final vote');

    holdFloorVote($session, $item, $presiding, $member, VoteChoice::Yes);

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.advance', $session))
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.agenda_advanced');

    expect($item->fresh()->status)->toBe('completed')
        ->and($item->document?->fresh()?->status)->toBeInstanceOf(Approved::class);
});

it('marks a passed third-reading measure approved when the final vote closes', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'third-pass-approved');
    $presiding = calendarActor(UserRole::PresidingOfficer, 'third-pass-approved');
    $member = calendarActor(UserRole::BoardMember, 'third-pass-approved');
    [$session, $item, $document] = calendarThirdReadingOnFloor($secretariat, 'An ordinance that passed third reading');

    holdFloorVote($session, $item, $presiding, $member, VoteChoice::Yes);

    expect($document->fresh()->status)->toBeInstanceOf(Approved::class);
});

it('marks a failed third-reading measure rejected when the final vote closes', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'third-fail-rejected');
    $presiding = calendarActor(UserRole::PresidingOfficer, 'third-fail-rejected');
    $member = calendarActor(UserRole::BoardMember, 'third-fail-rejected');
    [$session, $item, $document] = calendarThirdReadingOnFloor($secretariat, 'An ordinance that failed third reading');

    holdFloorVote($session, $item, $presiding, $member, VoteChoice::No);

    expect($document->fresh()->status)->toBeInstanceOf(Rejected::class);
});

it('brings a document in line with a completed third-reading vote when the record is opened', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'third-sync-show');
    $member = calendarActor(UserRole::BoardMember, 'third-sync-show');
    [$session, $item, $document] = calendarThirdReadingOnFloor($secretariat, 'An ordinance already voted on third reading');

    $item->update([
        'voting_round' => 1,
        'voting_opened_at' => now()->subMinutes(5),
        'voting_closed_at' => now()->subMinute(),
    ]);

    Vote::factory()->choice(VoteChoice::Yes)->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $item->getKey(),
        'user_id' => $member->getKey(),
        'voting_round' => 1,
    ]);

    expect($document->fresh()->status)->toBeInstanceOf(AgendaInclusion::class);

    $this->actingAs($secretariat)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/Show')
            ->where('document.status', Approved::$name)
        );
});

it('places a passed second-reading measure on third reading when voting closes', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'second-pass-third');
    $presiding = calendarActor(UserRole::PresidingOfficer, 'second-pass-third');
    $member = calendarActor(UserRole::BoardMember, 'second-pass-third');
    [$session, $item, $document] = calendarSecondReadingOnFloor($secretariat, 'An ordinance that passed second reading');

    holdFloorVote($session, $item, $presiding, $member, VoteChoice::Yes);

    $heading = $session->agendaItems()->where('category', 'third-reading')->whereNull('document_id')->firstOrFail();
    $third = $session->agendaItems()
        ->where('document_id', $document->getKey())
        ->where('parent_id', $heading->getKey())
        ->first();

    expect($third)->not->toBeNull()
        ->and($third?->reading_number)->toBe(3)
        ->and($document->fresh()->current_reading)->toBe(3)
        ->and($item->fresh()->status)->toBe('in-progress');

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.advance', $session))
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.agenda_advanced');

    expect($item->fresh()->status)->toBe('completed');
});

it('does not send a failed second-reading measure to third reading', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'second-fail-third');
    $presiding = calendarActor(UserRole::PresidingOfficer, 'second-fail-third');
    $member = calendarActor(UserRole::BoardMember, 'second-fail-third');
    [$session, $item, $document] = calendarSecondReadingOnFloor($secretariat, 'An ordinance that failed second reading');

    holdFloorVote($session, $item, $presiding, $member, VoteChoice::No);

    expect($session->agendaItems()->where('document_id', $document->getKey())->where('reading_number', 3)->exists())->toBeFalse();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.advance', $session))
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.agenda_advanced');

    expect($item->fresh()->status)->toBe('completed');
});

it('lets the secretariat place a passed measure on third reading from the calendar', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'calendar-third');
    $presiding = calendarActor(UserRole::PresidingOfficer, 'calendar-third');
    $member = calendarActor(UserRole::BoardMember, 'calendar-third');
    [$session, $item, $document] = calendarSecondReadingOnFloor($secretariat, 'An ordinance calendared for third reading');

    holdFloorVote($session, $item, $presiding, $member, VoteChoice::Yes);

    $this->actingAs($secretariat)
        ->post(route('sessions.calendar.third-reading', $session), [
            'document_id' => $document->getKey(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.calendar.third_reading_done');

    $heading = $session->agendaItems()->where('category', 'third-reading')->whereNull('document_id')->firstOrFail();

    expect($session->agendaItems()->where('document_id', $document->getKey())->where('parent_id', $heading->getKey())->count())->toBe(1);
});

it('lists a passed second-reading measure once after it is placed on third reading', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'docket-one-ordinance');
    $presiding = calendarActor(UserRole::PresidingOfficer, 'docket-one-ordinance');
    $member = calendarActor(UserRole::BoardMember, 'docket-one-ordinance');
    [$session, $item, $document] = calendarSecondReadingOnFloor($secretariat, 'Sample Ordinance');

    holdFloorVote($session->fresh(), $item->fresh(), $presiding, $member, VoteChoice::Yes);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Secretariat')
            ->where('calendar_docket', function ($docket) use ($document, $item): bool {
                $rows = collect($docket)->where('document_id', $document->getKey())->values();
                $row = $rows->first();

                return $rows->count() === 1
                    && is_array($row)
                    && $row['agenda_item_id'] === $item->getKey()
                    && $row['can_second_reading'] === false
                    && $row['can_third_reading'] === false
                    && $row['can_postpone'] === false
                    && $row['placed_on_third_reading'] === true;
            })
        );
});

it('offers proceed to third reading when a passing vote has not yet been placed', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'docket-third-button');
    $presiding = calendarActor(UserRole::PresidingOfficer, 'docket-third-button');
    $member = calendarActor(UserRole::BoardMember, 'docket-third-button');
    [$session, $item, $document] = calendarSecondReadingOnFloor($secretariat, 'An ordinance waiting for third reading');

    holdFloorVote($session, $item, $presiding, $member, VoteChoice::Yes);

    $session->agendaItems()
        ->where('document_id', $document->getKey())
        ->where('reading_number', 3)
        ->delete();

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('calendar_docket', function ($docket) use ($document): bool {
                $row = collect($docket)->firstWhere('document_id', $document->getKey());

                return is_array($row)
                    && $row['can_third_reading'] === true
                    && $row['can_second_reading'] === false
                    && $row['placed_on_third_reading'] === false;
            })
        );
});

it('refers several unassigned measures to second reading in one request', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'bulk-second');
    $first = calendarReadyMeasure('An ordinance on bulk second reading one');
    $second = calendarReadyMeasure('An ordinance on bulk second reading two');
    [$session, $firstItem] = calendarSittingWithUnassigned($first, $secretariat);
    $unassigned = $session->agendaItems()->where('category', 'unassigned-business')->whereNull('document_id')->firstOrFail();
    app(AgendaService::class)->bindDocuments($session, $unassigned, [$second->getKey()], $secretariat);
    $secondItem = $session->agendaItems()->where('document_id', $second->getKey())->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.calendar.bulk', $session), [
            'action' => 'second-reading',
            'items' => [
                ['agenda_item_id' => $firstItem->getKey()],
                ['agenda_item_id' => $secondItem->getKey()],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.calendar.bulk_second_reading_done');

    $heading = $session->agendaItems()->where('category', 'business-for-the-day')->whereNull('document_id')->firstOrFail();

    expect($firstItem->fresh()->parent_id)->toBe($heading->getKey())
        ->and($firstItem->fresh()->category)->toBe('second-reading')
        ->and($firstItem->fresh()->reading_number)->toBe(2)
        ->and($secondItem->fresh()->parent_id)->toBe($heading->getKey())
        ->and($secondItem->fresh()->category)->toBe('second-reading');
});

it('postpones several live measures to unfinished business in one request', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'bulk-postpone');
    $first = calendarReadyMeasure('An ordinance on bulk postpone one');
    $second = calendarReadyMeasure('An ordinance on bulk postpone two');
    [$session, $firstItem] = calendarSittingWithUnassigned($first, $secretariat, InSession::$name);
    $unassigned = $session->agendaItems()->where('category', 'unassigned-business')->whereNull('document_id')->firstOrFail();
    app(AgendaService::class)->bindDocuments($session, $unassigned, [$second->getKey()], $secretariat);
    $secondItem = $session->agendaItems()->where('document_id', $second->getKey())->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.calendar.bulk', $session), [
            'action' => 'postpone',
            'items' => [
                ['agenda_item_id' => $firstItem->getKey()],
                ['agenda_item_id' => $secondItem->getKey()],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.calendar.bulk_postponed');

    expect($firstItem->fresh()->status)->toBe('postponed')
        ->and($secondItem->fresh()->status)->toBe('postponed');
});

it('refers several first-reading measures to second reading by document', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'bulk-docs');
    $first = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance bulk first reading one',
        'author_id' => $secretariat->getKey(),
    ]);
    $second = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance bulk first reading two',
        'author_id' => $secretariat->getKey(),
    ]);
    [$session, $firstItem, $first, $committee] = calendarSittingAfterFirstReadingReferral($first, $secretariat);
    $heading = $session->agendaItems()->where('category', 'first-reading')->whereNull('document_id')->firstOrFail();
    app(AgendaService::class)->bindDocuments($session, $heading, [$second->getKey()], $secretariat);
    $secondItem = $session->agendaItems()->where('document_id', $second->getKey())->firstOrFail();

    $transitions = app(GuardedStateTransition::class);
    $referrals = app(CommitteeReferralService::class);

    foreach ([$firstItem, $secondItem] as $item) {
        $document = $item->document;
        expect($document)->not->toBeNull();
        $transitions->transition($document, ReadingDeliberation::class, $secretariat);
        $document = $document->fresh() ?? $document;
        $referrals->refer($document, [$committee->getKey()], $secretariat, $session, $item);
        $transitions->transition($document, CommitteeReferralState::class, $secretariat);
    }

    completeFirstReadingSection($session, $firstItem);
    $secondItem->update([
        'status' => 'completed',
        'completed_at' => now(),
        'started_at' => $secondItem->started_at ?? now()->subMinute(),
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.calendar.bulk', $session), [
            'action' => 'second-reading',
            'items' => [
                ['document_id' => $first->getKey()],
                ['document_id' => $second->getKey()],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.calendar.bulk_second_reading_done');

    $business = $session->agendaItems()->where('category', 'business-for-the-day')->whereNull('document_id')->firstOrFail();

    expect($session->agendaItems()->where('document_id', $first->getKey())->where('parent_id', $business->getKey())->exists())->toBeTrue()
        ->and($session->agendaItems()->where('document_id', $second->getKey())->where('parent_id', $business->getKey())->exists())->toBeTrue()
        ->and($first->referrals()->whereNull('completed_at')->exists())->toBeFalse()
        ->and($second->referrals()->whereNull('completed_at')->exists())->toBeFalse();
});

it('rejects bulk second reading when a measure is scheduled for a committee hearing', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'bulk-hearing-second');
    $dated = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance scheduled for hearing',
        'author_id' => $secretariat->getKey(),
    ]);
    $ready = calendarReadyMeasure('An ordinance ready beside a hearing');
    [$session, $datedItem, $dated, $committee] = calendarSittingAfterFirstReadingReferral($dated, $secretariat);
    $unassigned = $session->agendaItems()->where('category', 'unassigned-business')->whereNull('document_id')->firstOrFail();
    app(AgendaService::class)->bindDocuments($session, $unassigned, [$ready->getKey()], $secretariat);
    $readyItem = $session->agendaItems()->where('document_id', $ready->getKey())->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.floor.refer', $session), [
            'agenda_item_id' => $datedItem->getKey(),
            'committee_id' => $committee->getKey(),
            'meeting_on' => now()->addDays(4)->toDateString(),
        ])
        ->assertRedirect();

    $this->actingAs($secretariat)
        ->post(route('sessions.calendar.bulk', $session), [
            'action' => 'second-reading',
            'items' => [
                ['agenda_item_id' => $datedItem->getKey()],
                ['agenda_item_id' => $readyItem->getKey()],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'sessions.calendar.bulk_hearing_blocks_second_reading')
        ->assertSessionHas('error_replacements', [
            'number' => 1,
            'title' => $dated->title,
        ]);

    expect($readyItem->fresh()->category)->toBe('unassigned-business')
        ->and($dated->referrals()->whereNull('completed_at')->exists())->toBeTrue();
});

it('rejects bulk unfinished business when a later measure is scheduled for a committee hearing', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'bulk-hearing-postpone');
    $dated = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance waiting on a committee meeting',
        'author_id' => $secretariat->getKey(),
    ]);
    $ready = calendarReadyMeasure('An ordinance ready to postpone');
    [$session, $datedItem, , $committee] = calendarSittingAfterFirstReadingReferral($dated, $secretariat);
    $unassigned = $session->agendaItems()->where('category', 'unassigned-business')->whereNull('document_id')->firstOrFail();
    app(AgendaService::class)->bindDocuments($session, $unassigned, [$ready->getKey()], $secretariat);
    $readyItem = $session->agendaItems()->where('document_id', $ready->getKey())->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.floor.refer', $session), [
            'agenda_item_id' => $datedItem->getKey(),
            'committee_id' => $committee->getKey(),
            'meeting_on' => now()->addDays(5)->toDateString(),
        ])
        ->assertRedirect();

    $this->actingAs($secretariat)
        ->post(route('sessions.calendar.bulk', $session), [
            'action' => 'postpone',
            'items' => [
                ['agenda_item_id' => $readyItem->getKey()],
                ['document_id' => $dated->getKey()],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'sessions.calendar.bulk_hearing_blocks_postpone')
        ->assertSessionHas('error_replacements', [
            'number' => 2,
            'title' => $dated->title,
        ]);

    expect($readyItem->fresh()->status)->not->toBe('postponed');
});

it('still bulk-refers a same-sitting measure after a meeting date is added later', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'bulk-hearing-waived');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'An ordinance kept on this sitting',
        'author_id' => $secretariat->getKey(),
    ]);
    [$session, $item, $document, $committee] = calendarSittingAfterFirstReadingReferral($document, $secretariat);

    $this->actingAs($secretariat)
        ->post(route('sessions.floor.refer', $session), [
            'agenda_item_id' => $item->getKey(),
            'committee_id' => $committee->getKey(),
        ])
        ->assertRedirect();

    $this->actingAs($secretariat)
        ->put(route('documents.referral.update', $document), [
            'committee_id' => $committee->getKey(),
            'meeting_on' => now()->addDays(3)->toDateString(),
        ])
        ->assertRedirect();

    $this->actingAs($secretariat)
        ->post(route('sessions.calendar.bulk', $session), [
            'action' => 'second-reading',
            'items' => [
                ['document_id' => $document->getKey()],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.calendar.bulk_second_reading_done');
});

it('rolls back a bulk referral when one measure cannot be routed', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'bulk-rollback');
    $ready = calendarReadyMeasure('An ordinance bulk rollback keep');
    $other = calendarReadyMeasure('An ordinance bulk rollback foreign');
    [$session, $readyItem] = calendarSittingWithUnassigned($ready, $secretariat);
    [, $foreignItem] = calendarSittingWithUnassigned($other, $secretariat);

    $this->actingAs($secretariat)
        ->post(route('sessions.calendar.bulk', $session), [
            'action' => 'second-reading',
            'items' => [
                ['agenda_item_id' => $readyItem->getKey()],
                ['agenda_item_id' => $foreignItem->getKey()],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'sessions.calendar.bulk_item_missing');

    expect($readyItem->fresh()->category)->toBe('unassigned-business');
});

it('approves a resolution on a passing second-reading vote and does not calendar third reading', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'resolution-second-pass');
    $presiding = calendarActor(UserRole::PresidingOfficer, 'resolution-second-pass');
    $member = calendarActor(UserRole::BoardMember, 'resolution-second-pass');
    [$session, $item, $document] = calendarSecondReadingOnFloor(
        $secretariat,
        'A resolution that passed second reading',
        DocumentType::ProposedResolution,
    );

    holdFloorVote($session, $item, $presiding, $member, VoteChoice::Yes);

    expect($document->fresh()->status)->toBeInstanceOf(Approved::class)
        ->and($session->agendaItems()->where('document_id', $document->getKey())->where('reading_number', 3)->exists())->toBeFalse();

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('calendar_docket', function ($docket) use ($document): bool {
                $row = collect($docket)->firstWhere('document_id', $document->getKey());

                return $row === null
                    || (is_array($row)
                        && $row['can_third_reading'] === false
                        && $row['placed_on_third_reading'] === false);
            })
        );
});

it('rejects a resolution on a failing second-reading vote', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'resolution-second-fail');
    $presiding = calendarActor(UserRole::PresidingOfficer, 'resolution-second-fail');
    $member = calendarActor(UserRole::BoardMember, 'resolution-second-fail');
    [$session, $item, $document] = calendarSecondReadingOnFloor(
        $secretariat,
        'A resolution that failed second reading',
        DocumentType::ProposedResolution,
    );

    holdFloorVote($session, $item, $presiding, $member, VoteChoice::No);

    expect($document->fresh()->status)->toBeInstanceOf(Rejected::class)
        ->and($session->agendaItems()->where('document_id', $document->getKey())->where('reading_number', 3)->exists())->toBeFalse();
});

it('refuses to place a resolution on third reading from the calendar', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'resolution-no-third');
    $presiding = calendarActor(UserRole::PresidingOfficer, 'resolution-no-third');
    $member = calendarActor(UserRole::BoardMember, 'resolution-no-third');
    [$session, $item, $document] = calendarSecondReadingOnFloor(
        $secretariat,
        'A resolution that cannot go to third reading',
        DocumentType::ProposedResolution,
    );

    holdFloorVote($session, $item, $presiding, $member, VoteChoice::Yes);

    $this->actingAs($secretariat)
        ->post(route('sessions.calendar.third-reading', $session), [
            'document_id' => $document->getKey(),
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'sessions.calendar.resolutions_skip_third_reading');

    $this->actingAs($secretariat)
        ->post(route('sessions.calendar.bulk', $session), [
            'action' => 'third-reading',
            'items' => [
                ['document_id' => $document->getKey()],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'sessions.calendar.resolutions_skip_third_reading');

    expect($session->agendaItems()->where('document_id', $document->getKey())->where('reading_number', 3)->exists())->toBeFalse();
});

it('brings a resolution in line with a completed second-reading vote when the record is opened', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'resolution-sync-show');
    $member = calendarActor(UserRole::BoardMember, 'resolution-sync-show');
    [$session, $item, $document] = calendarSecondReadingOnFloor(
        $secretariat,
        'A resolution already voted on second reading',
        DocumentType::ProposedResolution,
    );

    $item->update([
        'voting_round' => 1,
        'voting_opened_at' => now()->subMinutes(5),
        'voting_closed_at' => now()->subMinute(),
    ]);

    Vote::factory()->choice(VoteChoice::Yes)->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $item->getKey(),
        'user_id' => $member->getKey(),
        'voting_round' => 1,
    ]);

    expect($document->fresh()->status)->toBeInstanceOf(AgendaInclusion::class);

    $this->actingAs($secretariat)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/Show')
            ->where('document.status', Approved::$name)
        );
});

it('still applies a third-reading vote to a resolution already on that heading', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'resolution-grandfather-third');
    $presiding = calendarActor(UserRole::PresidingOfficer, 'resolution-grandfather-third');
    $member = calendarActor(UserRole::BoardMember, 'resolution-grandfather-third');
    $document = Document::factory()->ofType(DocumentType::ProposedResolution)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 3,
        'title' => 'A resolution already on third reading',
        'author_id' => $secretariat->getKey(),
    ]);

    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => InSession::$name,
        'scheduled_start_at' => now()->addDay()->setTime(9, 0),
        'actual_start_at' => now(),
        'secretary_id' => $secretariat->getKey(),
    ]);
    $agenda = app(AgendaService::class);
    $agenda->prepareStandardTemplate($session);

    $heading = $session->agendaItems()
        ->where('category', 'third-reading')
        ->whereNull('document_id')
        ->firstOrFail();

    $item = $agenda->createItem($session, [
        'title' => $document->title,
        'document_id' => $document->getKey(),
        'category' => 'third-reading',
        'parent_id' => $heading->getKey(),
        'reading_number' => 3,
        'requires_vote' => true,
        'status' => 'in-progress',
    ]);
    $item->update(['started_at' => now()]);

    holdFloorVote($session->fresh(), $item->fresh(), $presiding, $member, VoteChoice::Yes);

    expect($document->fresh()->status)->toBeInstanceOf(Approved::class);
});

it('forbids bulk calendar routing to a board member', function (): void {
    $secretariat = calendarActor(UserRole::Secretariat, 'bulk-member-sec');
    $member = calendarActor(UserRole::BoardMember, 'bulk-member');
    $document = calendarReadyMeasure('An ordinance members cannot bulk route');
    [$session, $item] = calendarSittingWithUnassigned($document, $secretariat, InSession::$name);

    $this->actingAs($member)
        ->post(route('sessions.calendar.bulk', $session), [
            'action' => 'postpone',
            'items' => [
                ['agenda_item_id' => $item->getKey()],
            ],
        ])
        ->assertForbidden();
});
