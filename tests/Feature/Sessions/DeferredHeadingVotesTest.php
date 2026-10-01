<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Enums\VoteChoice;
use App\Http\Resources\SessionResource;
use App\Models\AgendaItem;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Services\Documents\DocumentAccessService;
use App\Services\Sessions\AgendaService;
use App\Services\Sessions\AttendanceService;
use App\Services\Sessions\QuorumService;
use App\Services\Sessions\VotingService;
use App\States\Document\AgendaInclusion;
use App\States\Session\Adjourned;
use App\States\Session\Draft;
use App\States\Session\InSession;
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

function deferredVotesActor(UserRole $role, string $suffix): User
{
    return User::factory()->create([
        'email' => "{$role->value}-defer-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole($role->value);
}

function deferredVotesMeasure(string $title): Document
{
    return Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => AgendaInclusion::$name,
        'current_reading' => 2,
        'title' => $title,
    ]);
}

/**
 * Two second-reading ordinances under Business for the Day, first on the floor.
 *
 * @return array{0: LegislativeSession, 1: AgendaItem, 2: AgendaItem, 3: AgendaItem}
 */
function deferredVotesHeadingOnFloor(User $secretariat, string $suffix): array
{
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => InSession::$name,
        'scheduled_start_at' => now()->addDay()->setTime(9, 0),
        'actual_start_at' => now(),
        'secretary_id' => $secretariat->getKey(),
        'defer_heading_votes' => true,
    ]);
    $agenda = app(AgendaService::class);
    $agenda->prepareStandardTemplate($session);

    $heading = $session->agendaItems()
        ->where('category', 'business-for-the-day')
        ->whereNull('document_id')
        ->firstOrFail();

    $firstDoc = deferredVotesMeasure("Ordinance one {$suffix}");
    $secondDoc = deferredVotesMeasure("Ordinance two {$suffix}");
    $agenda->bindDocuments($session, $heading, [$firstDoc->getKey(), $secondDoc->getKey()], $secretariat);

    $first = $session->agendaItems()->where('document_id', $firstDoc->getKey())->firstOrFail();
    $second = $session->agendaItems()->where('document_id', $secondDoc->getKey())->firstOrFail();

    $session->agendaItems()->update([
        'status' => 'pending',
        'started_at' => null,
        'completed_at' => null,
    ]);
    $session->agendaItems()
        ->where('position', '<', $first->position)
        ->update([
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(30),
        ]);
    $first->update([
        'status' => 'in-progress',
        'started_at' => now(),
    ]);

    return [$session->fresh(), $heading->fresh(), $first->fresh(), $second->fresh()];
}

function deferredHoldVote(
    LegislativeSession $session,
    AgendaItem $item,
    User $presiding,
    User $voter,
    VoteChoice $choice = VoteChoice::Yes,
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

it('still blocks advancing a second-reading measure without a vote in immediate mode', function (): void {
    $secretariat = deferredVotesActor(UserRole::Secretariat, 'immediate-block');
    [$session, $heading, $first] = deferredVotesHeadingOnFloor($secretariat, 'immediate');
    $session->update(['defer_heading_votes' => false]);

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->post(route('sessions.agenda.advance', $session))
        ->assertRedirect(route('sessions.floor.secretariat', $session))
        ->assertSessionHas('error', 'sessions.advance_blocked_second_reading_vote');

    expect($first->fresh()->status)->toBe('in-progress');
});

it('lets a deferred sitting discuss two ordinances then refuses to leave the heading', function (): void {
    $secretariat = deferredVotesActor(UserRole::Secretariat, 'discuss');
    [$session, $heading, $first, $second] = deferredVotesHeadingOnFloor($secretariat, 'discuss');

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->post(route('sessions.agenda.advance', $session))
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.agenda_advanced');

    expect($first->fresh()->status)->toBe('considered')
        ->and($first->fresh()->completed_at)->toBeNull()
        ->and($second->fresh()->status)->toBe('in-progress');

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->post(route('sessions.agenda.advance', $session))
        ->assertRedirect(route('sessions.floor.secretariat', $session))
        ->assertSessionHas('error', 'sessions.advance_blocked_heading_votes');

    expect($second->fresh()->status)->toBe('in-progress');
});

it('opens heading votes in order then leaves once every measure has been voted', function (): void {
    $secretariat = deferredVotesActor(UserRole::Secretariat, 'pass');
    $presiding = deferredVotesActor(UserRole::PresidingOfficer, 'pass');
    $voter = deferredVotesActor(UserRole::BoardMember, 'pass');
    [$session, $heading, $first, $second] = deferredVotesHeadingOnFloor($secretariat, 'pass');

    $this->actingAs($secretariat)->post(route('sessions.agenda.advance', $session))->assertRedirect();

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.begin_heading_votes', true)
            ->where('advance_blocked_reason', 'sessions.advance_blocked_heading_votes'));

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.begin-heading-votes', $session))
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.heading_votes_begun');

    expect($second->fresh()->status)->toBe('considered')
        ->and($first->fresh()->status)->toBe('in-progress');

    deferredHoldVote($session, $first->fresh(), $presiding, $voter);

    $this->actingAs($secretariat)->post(route('sessions.agenda.advance', $session))->assertRedirect();

    expect($first->fresh()->status)->toBe('completed')
        ->and($second->fresh()->status)->toBe('in-progress');

    deferredHoldVote($session, $second->fresh(), $presiding, $voter);

    $this->actingAs($secretariat)->post(route('sessions.agenda.advance', $session))->assertRedirect();

    expect($second->fresh()->status)->toBe('completed')
        ->and($heading->fresh()->status)->not->toBe('in-progress');
});

it('restores the per-item vote gate when deferred mode is turned off', function (): void {
    $secretariat = deferredVotesActor(UserRole::Secretariat, 'toggle-off');
    [$session, $heading, $first, $second] = deferredVotesHeadingOnFloor($secretariat, 'toggle-off');

    $this->actingAs($secretariat)->post(route('sessions.agenda.advance', $session))->assertRedirect();

    $this->actingAs($secretariat)
        ->post(route('sessions.voting-mode.update', $session), [
            'defer_heading_votes' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.heading_votes_immediate');

    expect($session->fresh()->defer_heading_votes)->toBeFalse()
        ->and($first->fresh()->status)->toBe('considered');

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->post(route('sessions.agenda.advance', $session))
        ->assertRedirect(route('sessions.floor.secretariat', $session))
        ->assertSessionHas('error', 'sessions.advance_blocked_second_reading_vote');

    expect($second->fresh()->status)->toBe('in-progress');
});

it('lets secretariat switch voting mode and forbids board members', function (): void {
    $secretariat = deferredVotesActor(UserRole::Secretariat, 'toggle-auth');
    $member = deferredVotesActor(UserRole::BoardMember, 'toggle-auth');
    [$session] = deferredVotesHeadingOnFloor($secretariat, 'toggle-auth');

    $this->actingAs($member)
        ->post(route('sessions.voting-mode.update', $session), [
            'defer_heading_votes' => true,
        ])
        ->assertForbidden();

    $this->actingAs($secretariat)
        ->post(route('sessions.voting-mode.update', $session), [
            'defer_heading_votes' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.heading_votes_deferred');
});

it('offers postpone on a considered measure and unblocks leaving after postpone', function (): void {
    $secretariat = deferredVotesActor(UserRole::Secretariat, 'postpone');
    $presiding = deferredVotesActor(UserRole::PresidingOfficer, 'postpone');
    $voter = deferredVotesActor(UserRole::BoardMember, 'postpone');
    [$session, $heading, $first, $second] = deferredVotesHeadingOnFloor($secretariat, 'postpone');

    $next = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(8)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);
    app(AgendaService::class)->prepareStandardTemplate($next);

    $this->actingAs($secretariat)->post(route('sessions.agenda.advance', $session))->assertRedirect();

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reading_pack', function ($pack) use ($first): bool {
                $row = collect($pack)->firstWhere('id', $first->getKey());

                return is_array($row)
                    && $row['status'] === 'considered'
                    && $row['can_postpone'] === true;
            }));

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.postpone', [$session, $first]))
        ->assertRedirect();

    expect($first->fresh()->status)->toBe('postponed');

    deferredHoldVote($session, $second->fresh(), $presiding, $voter);

    $this->actingAs($secretariat)->post(route('sessions.agenda.advance', $session))->assertRedirect();

    expect($second->fresh()->status)->toBe('completed')
        ->and($heading->fresh()->status)->not->toBe('in-progress');
});

it('carries considered leftovers to unfinished business on adjournment', function (): void {
    $secretariat = deferredVotesActor(UserRole::Secretariat, 'adjourn-carry');
    $presiding = deferredVotesActor(UserRole::PresidingOfficer, 'adjourn-carry');
    [$session, $heading, $first, $second] = deferredVotesHeadingOnFloor($secretariat, 'adjourn-carry');

    $next = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(8)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);
    app(AgendaService::class)->prepareStandardTemplate($next);

    $this->actingAs($secretariat)->post(route('sessions.agenda.advance', $session))->assertRedirect();

    expect($first->fresh()->status)->toBe('considered');

    $this->actingAs($presiding)->post(route('sessions.adjourn', $session))->assertRedirect();

    expect($session->fresh()->status)->toBeInstanceOf(Adjourned::class)
        ->and($first->fresh()->status)->toBe('postponed')
        ->and($second->fresh()->status)->toBe('postponed')
        ->and($next->agendaItems()->where('document_id', $first->document_id)->where('category', 'unfinished-business')->exists())->toBeTrue()
        ->and($next->agendaItems()->where('document_id', $second->document_id)->where('category', 'unfinished-business')->exists())->toBeTrue();
});

it('keeps the heading open for calendar routing while children are considered', function (): void {
    $secretariat = deferredVotesActor(UserRole::Secretariat, 'heading-open');
    [$session, $heading, $first, $second] = deferredVotesHeadingOnFloor($secretariat, 'heading-open');

    $this->actingAs($secretariat)->post(route('sessions.agenda.advance', $session))->assertRedirect();
    $heading->update(['status' => 'completed', 'completed_at' => now()]);
    $second->update(['status' => 'considered', 'started_at' => now()]);

    $pool = deferredVotesMeasure('An ordinance still in the pool');
    $unassigned = $session->agendaItems()
        ->where('category', 'unassigned-business')
        ->whereNull('document_id')
        ->firstOrFail();
    app(AgendaService::class)->bindDocuments($session, $unassigned, [$pool->getKey()], $secretariat);
    $poolItem = $session->agendaItems()->where('document_id', $pool->getKey())->firstOrFail();

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('calendar_docket', function ($docket) use ($poolItem): bool {
                $row = collect($docket)->firstWhere('agenda_item_id', $poolItem->getKey());

                return is_array($row) && $row['can_second_reading'] === true;
            }));
});

it('does not mark items considered just by rendering the floor payload', function (): void {
    $secretariat = deferredVotesActor(UserRole::Secretariat, 'pure-reason');
    [$session, $heading, $first, $second] = deferredVotesHeadingOnFloor($secretariat, 'pure-reason');

    $before = $session->agendaItems()->orderBy('position')->get(['id', 'status', 'completed_at'])->toArray();

    SessionResource::floor(
        $session->fresh(),
        $secretariat,
        app(QuorumService::class),
        app(AgendaService::class),
        app(DocumentAccessService::class),
        app(VotingService::class),
        app(AttendanceService::class),
    );

    $after = $session->agendaItems()->orderBy('position')->get(['id', 'status', 'completed_at'])->toArray();

    expect($after)->toBe($before)
        ->and($first->fresh()->status)->toBe('in-progress')
        ->and($second->fresh()->status)->toBe('pending');
});

it('keeps the considered mark when retreating onto a discussed measure and back', function (): void {
    $secretariat = deferredVotesActor(UserRole::Secretariat, 'retreat');
    [$session, $heading, $first, $second] = deferredVotesHeadingOnFloor($secretariat, 'retreat');

    $this->actingAs($secretariat)->post(route('sessions.agenda.advance', $session))->assertRedirect();

    expect($first->fresh()->status)->toBe('considered')
        ->and($second->fresh()->status)->toBe('in-progress');

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.retreat', $session))
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.agenda_retreated');

    expect($first->fresh()->status)->toBe('in-progress')
        ->and($second->fresh()->status)->toBe('pending');

    $this->actingAs($secretariat)->post(route('sessions.agenda.advance', $session))->assertRedirect();

    expect($first->fresh()->status)->toBe('considered')
        ->and($second->fresh()->status)->toBe('in-progress');
});
