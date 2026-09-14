<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Enums\VoteChoice;
use App\Models\AgendaItem;
use App\Models\Committee;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Models\Vote;
use App\Services\Sessions\AgendaService;
use App\States\Document\AgendaInclusion;
use App\States\Document\Approved;
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

function calendarReadyMeasure(string $title, string $status = 'committee-report'): Document
{
    return Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
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
                    && $row['title'] === 'An ordinance after first reading';
            })
        );
});

it('does not list a first-reading measure that was never referred', function (): void {
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
                return collect($docket)->firstWhere('document_id', $document->getKey()) === null;
            })
        );
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
        ->assertRedirect();

    $heading = $session->agendaItems()->where('category', 'business-for-the-day')->whereNull('document_id')->firstOrFail();
    $placed = $session->agendaItems()
        ->where('document_id', $document->getKey())
        ->where('parent_id', $heading->getKey())
        ->first();

    expect($placed)->not->toBeNull()
        ->and($placed?->category)->toBe('second-reading')
        ->and($placed?->reading_number)->toBe(2)
        ->and($document->fresh()->status)->toBeInstanceOf(AgendaInclusion::class)
        ->and($document->fresh()->current_reading)->toBe(2)
        ->and($item->fresh()->status)->toBe('completed');
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
        ->assertRedirect();

    $unfinished = $next->agendaItems()
        ->where('category', 'unfinished-business')
        ->where('document_id', $document->getKey())
        ->first();

    expect($unfinished)->not->toBeNull()
        ->and($unfinished?->reading_number)->toBe(2)
        ->and($document->fresh()->current_reading)->toBe(2);
});

/**
 * @return array{0: LegislativeSession, 1: AgendaItem, 2: Document}
 */
function calendarSecondReadingOnFloor(User $secretariat, string $title): array
{
    $document = calendarReadyMeasure($title);
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
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'Sample Ordinance',
        'author_id' => $secretariat->getKey(),
    ]);
    [$session, $firstReading, $document, $committee] = calendarSittingAfterFirstReadingReferral($document, $secretariat);

    $this->actingAs($secretariat)
        ->post(route('sessions.floor.refer', $session), [
            'agenda_item_id' => $firstReading->getKey(),
            'committee_id' => $committee->getKey(),
        ])
        ->assertRedirect();

    completeFirstReadingSection($session, $firstReading);

    $this->actingAs($secretariat)
        ->post(route('sessions.calendar.second-reading', $session), [
            'document_id' => $document->getKey(),
        ])
        ->assertRedirect();

    $item = $session->agendaItems()
        ->where('document_id', $document->getKey())
        ->where('reading_number', 2)
        ->firstOrFail();

    $session->agendaItems()->update([
        'status' => 'pending',
        'started_at' => null,
        'completed_at' => null,
    ]);
    $firstReading->update([
        'status' => 'completed',
        'completed_at' => now(),
    ]);
    $item->update([
        'status' => 'in-progress',
        'started_at' => now(),
    ]);

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
