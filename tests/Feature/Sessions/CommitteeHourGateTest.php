<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\CommitteeReferral;
use App\Models\CommitteeReport;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Services\Sessions\AgendaService;
use App\States\Document\AgendaInclusion;
use App\States\Document\Archive;
use App\States\Document\CommitteeReport as CommitteeReportState;
use App\States\Document\CommitteeReview;
use App\States\Session\AgendaPrepared;
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

function committeeHourActor(UserRole $role, string $suffix): User
{
    return User::factory()->create([
        'email' => "{$role->value}-hour-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole($role->value);
}

/**
 * @return array{0: Document, 1: Committee, 2: CommitteeReferral}
 */
function committeeHourReferredMeasure(string $title, string $recommendation = 'approve'): array
{
    $committee = Committee::factory()->create();
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'title' => $title,
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

    return [$document, $committee, $referral];
}

it('lets secretariat file a draft report and queues it on the next regular session', function (): void {
    $secretariat = committeeHourActor(UserRole::Secretariat, 'file');
    [$document, $committee, $referral] = committeeHourReferredMeasure('An ordinance on street trees');

    $next = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(3)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.prepare-agenda', $next))
        ->assertRedirect();

    $this->actingAs($secretariat)
        ->post(route('reports.store'), [
            'committee_referral_id' => $referral->getKey(),
            'committee_id' => $committee->getKey(),
            'recommendation' => 'approve',
            'findings' => 'The committee recommends approval.',
            'file_now' => true,
        ])
        ->assertRedirect(route('documents.show', $document));

    $report = CommitteeReport::query()->where('committee_referral_id', $referral->getKey())->firstOrFail();
    $heading = $next->fresh()->agendaItems()
        ->where('category', 'committee-reports')
        ->whereNull('document_id')
        ->firstOrFail();
    $item = $next->fresh()->agendaItems()
        ->where('document_id', $document->getKey())
        ->where('parent_id', $heading->getKey())
        ->first();

    expect($report->status)->toBe('submitted')
        ->and($referral->fresh()->status)->toBe('reported')
        ->and($document->fresh()->status)->toBeInstanceOf(CommitteeReportState::class)
        ->and($item)->not->toBeNull()
        ->and($item?->reading_number)->toBeNull();
});

it('does not queue a deferred report for Committee Hour', function (): void {
    $secretariat = committeeHourActor(UserRole::Secretariat, 'defer');
    [$document, $committee, $referral] = committeeHourReferredMeasure('An ordinance deferred');

    $next = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(4)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);
    $this->actingAs($secretariat)->post(route('sessions.prepare-agenda', $next))->assertRedirect();

    $this->actingAs($secretariat)
        ->post(route('reports.store'), [
            'committee_referral_id' => $referral->getKey(),
            'committee_id' => $committee->getKey(),
            'recommendation' => 'defer',
            'findings' => 'Need another hearing.',
            'file_now' => true,
        ])
        ->assertRedirect(route('documents.show', $document));

    expect($referral->fresh()->status)->toBe('in-review')
        ->and($document->fresh()->status)->toBeInstanceOf(CommitteeReview::class)
        ->and($next->fresh()->agendaItems()->where('document_id', $document->getKey())->exists())->toBeFalse();
});

it('skips a live regular sitting when queuing a submitted report', function (): void {
    $secretariat = committeeHourActor(UserRole::Secretariat, 'skip-live');
    [$document, $committee, $referral] = committeeHourReferredMeasure('An ordinance after tonight');

    $live = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => InSession::$name,
        'scheduled_start_at' => now()->setTime(9, 0),
        'actual_start_at' => now()->subHour(),
        'secretary_id' => $secretariat->getKey(),
    ]);
    $this->actingAs($secretariat)->post(route('sessions.prepare-agenda', $live))->assertRedirect();

    $later = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(7)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);
    $this->actingAs($secretariat)->post(route('sessions.prepare-agenda', $later))->assertRedirect();

    $this->actingAs($secretariat)
        ->post(route('reports.store'), [
            'committee_referral_id' => $referral->getKey(),
            'committee_id' => $committee->getKey(),
            'recommendation' => 'amend',
            'file_now' => true,
        ])
        ->assertRedirect(route('documents.show', $document));

    expect($live->fresh()->agendaItems()->where('document_id', $document->getKey())->exists())->toBeFalse()
        ->and($later->fresh()->agendaItems()->where('document_id', $document->getKey())->exists())->toBeTrue();
});

it('includes submitted reports when preparing a regular session agenda', function (): void {
    $secretariat = committeeHourActor(UserRole::Secretariat, 'prepare');
    [$document, $committee, $referral] = committeeHourReferredMeasure('An ordinance queued on prepare');

    CommitteeReport::factory()->create([
        'committee_referral_id' => $referral->getKey(),
        'committee_id' => $committee->getKey(),
        'subject_document_id' => $document->getKey(),
        'recommendation' => 'approve',
        'status' => 'submitted',
        'submitted_at' => now()->subDay(),
    ]);
    $document->update(['status' => CommitteeReportState::$name]);
    $referral->update(['status' => 'reported', 'completed_at' => now()->subDay()]);

    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(2)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.prepare-agenda', $session))
        ->assertRedirect();

    expect($session->fresh()->status)->toBeInstanceOf(AgendaPrepared::class)
        ->and($session->fresh()->agendaItems()->where('document_id', $document->getKey())->where('category', 'committee-reports')->exists())->toBeTrue();
});

it('records a favorable Committee Hour motion onto this sitting second reading', function (): void {
    $secretariat = committeeHourActor(UserRole::Secretariat, 'motion-second');
    [$document, $committee, $referral] = committeeHourReferredMeasure('An ordinance out of committee');

    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => InSession::$name,
        'scheduled_start_at' => now()->setTime(9, 0),
        'actual_start_at' => now()->subHour(),
        'secretary_id' => $secretariat->getKey(),
    ]);
    $this->actingAs($secretariat)->post(route('sessions.prepare-agenda', $session))->assertRedirect();

    $report = CommitteeReport::factory()->create([
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

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_item.committee_report.report_number', 'CR-2026-081')
            ->where('current_item.committee_report.findings', 'The trees ordinance should proceed to second reading.')
            ->where('reading_pack', function ($pack) use ($item): bool {
                $row = collect($pack)->firstWhere('id', $item->getKey());

                return is_array($row)
                    && $row['can_record_committee_hour_motion'] === true
                    && $row['committee_hour_action'] === 'second-reading'
                    && is_array($row['committee_report'] ?? null)
                    && $row['committee_report']['report_number'] === 'CR-2026-081'
                    && $row['committee_report']['findings'] === 'The trees ordinance should proceed to second reading.'
                    && $row['committee_report']['recommendation'] === 'approve';
            }));

    $member = committeeHourActor(UserRole::BoardMember, 'report-reader');

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reading_pack', function ($pack) use ($item): bool {
                $row = collect($pack)->firstWhere('id', $item->getKey());

                return is_array($row)
                    && is_array($row['committee_report'] ?? null)
                    && $row['committee_report']['findings'] === 'The trees ordinance should proceed to second reading.';
            }));

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.committee-hour-motion', [$session, $item]))
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.committee_hour.recorded');

    $business = $session->fresh()->agendaItems()
        ->where('category', 'business-for-the-day')
        ->whereNull('document_id')
        ->firstOrFail();
    $placed = $session->fresh()->agendaItems()
        ->where('document_id', $document->getKey())
        ->where('parent_id', $business->getKey())
        ->first();

    expect($report->fresh()->status)->toBe('adopted')
        ->and($item->fresh()->status)->toBe('completed')
        ->and($placed)->not->toBeNull()
        ->and($placed?->category)->toBe('second-reading')
        ->and($placed?->reading_number)->toBe(2)
        ->and($document->fresh()->status)->toBeInstanceOf(AgendaInclusion::class)
        ->and($document->fresh()->current_reading)->toBe(2);
});

it('records an unfavorable Committee Hour motion as archive', function (): void {
    $secretariat = committeeHourActor(UserRole::Secretariat, 'motion-archive');
    [$document, $committee, $referral] = committeeHourReferredMeasure('An ordinance not to proceed', 'disapprove');

    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => InSession::$name,
        'scheduled_start_at' => now()->setTime(9, 0),
        'actual_start_at' => now()->subHour(),
        'secretary_id' => $secretariat->getKey(),
    ]);
    $this->actingAs($secretariat)->post(route('sessions.prepare-agenda', $session))->assertRedirect();

    CommitteeReport::factory()->create([
        'committee_referral_id' => $referral->getKey(),
        'committee_id' => $committee->getKey(),
        'subject_document_id' => $document->getKey(),
        'recommendation' => 'disapprove',
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

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.committee-hour-motion', [$session, $item]))
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.committee_hour.recorded');

    expect($document->fresh()->status)->toBeInstanceOf(Archive::class)
        ->and($item->fresh()->status)->toBe('completed')
        ->and($session->fresh()->agendaItems()->where('document_id', $document->getKey())->where('category', 'second-reading')->exists())->toBeFalse();
});

it('rejects second reading from the calendar before the Committee Hour motion', function (): void {
    $secretariat = committeeHourActor(UserRole::Secretariat, 'gate');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'title' => 'An ordinance still in committee report',
        'status' => CommitteeReportState::$name,
    ]);
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDay()->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);
    $agenda = app(AgendaService::class);
    $agenda->prepareStandardTemplate($session);
    $unassigned = $session->agendaItems()->where('category', 'unassigned-business')->whereNull('document_id')->firstOrFail();
    $agenda->includeDocument($session, $document, $secretariat, null, $unassigned, authorize: false);
    $item = $session->agendaItems()->where('document_id', $document->getKey())->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.second-reading', [$session, $item]))
        ->assertRedirect()
        ->assertSessionHas('error', 'sessions.calendar.cannot_second_reading');
});

it('closes every open referral when one joint report is filed', function (): void {
    $secretariat = committeeHourActor(UserRole::Secretariat, 'joint');
    $primary = Committee::factory()->create();
    $secondary = Committee::factory()->create();
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'title' => 'An ordinance referred jointly',
        'status' => CommitteeReview::$name,
        'committee_id' => $primary->getKey(),
        'current_reading' => 1,
    ]);
    $primaryReferral = CommitteeReferral::factory()->create([
        'document_id' => $document->getKey(),
        'committee_id' => $primary->getKey(),
        'status' => 'in-review',
        'is_primary' => true,
        'completed_at' => null,
    ]);
    $secondaryReferral = CommitteeReferral::factory()->create([
        'document_id' => $document->getKey(),
        'committee_id' => $secondary->getKey(),
        'status' => 'pending',
        'is_primary' => false,
        'completed_at' => null,
    ]);

    $next = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(5)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);
    $this->actingAs($secretariat)->post(route('sessions.prepare-agenda', $next))->assertRedirect();

    $this->actingAs($secretariat)
        ->post(route('reports.store'), [
            'committee_referral_id' => $secondaryReferral->getKey(),
            'committee_id' => $secondary->getKey(),
            'recommendation' => 'approve',
            'findings' => 'The joint committees recommend approval.',
            'file_now' => true,
        ])
        ->assertRedirect(route('documents.show', $document));

    $heading = $next->fresh()->agendaItems()
        ->where('category', 'committee-reports')
        ->whereNull('document_id')
        ->firstOrFail();

    expect($primaryReferral->fresh()->status)->toBe('reported')
        ->and($secondaryReferral->fresh()->status)->toBe('reported')
        ->and($document->fresh()->status)->toBeInstanceOf(CommitteeReportState::class)
        ->and($next->fresh()->agendaItems()->where('document_id', $document->getKey())->where('parent_id', $heading->getKey())->exists())->toBeTrue();

    $this->actingAs($secretariat)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('document.open_referral', null));
});

it('returns a postponed committee report to the next regular reports heading', function (): void {
    $secretariat = committeeHourActor(UserRole::Secretariat, 'postpone-report');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'title' => 'An ordinance whose report is postponed',
        'status' => CommitteeReportState::$name,
        'current_reading' => 1,
    ]);
    CommitteeReport::factory()->submitted()->create([
        'subject_document_id' => $document->getKey(),
        'recommendation' => 'approve',
    ]);

    $session = LegislativeSession::factory()->inSession()->create([
        'type' => 'regular',
        'scheduled_start_at' => now()->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);
    $next = LegislativeSession::factory()->create([
        'type' => 'regular',
        'status' => Draft::$name,
        'scheduled_start_at' => now()->addDays(7)->setTime(9, 0),
        'secretary_id' => $secretariat->getKey(),
    ]);
    $agenda = app(AgendaService::class);
    $agenda->prepareStandardTemplate($session);
    $agenda->prepareStandardTemplate($next);

    $heading = $session->agendaItems()->where('category', 'committee-reports')->whereNull('document_id')->firstOrFail();
    $agenda->includeDocument($session, $document, $secretariat, null, $heading, authorize: false);
    $item = $session->agendaItems()->where('document_id', $document->getKey())->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.postpone', [$session, $item]))
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.calendar.postponed');

    $carried = $next->fresh()->agendaItems()->where('document_id', $document->getKey())->first();
    $reportsHeading = $next->agendaItems()->where('category', 'committee-reports')->whereNull('document_id')->firstOrFail();

    expect($item->fresh()->status)->toBe('postponed')
        ->and($item->fresh()->postponed_from_category)->toBe('committee-reports')
        ->and($carried)->not->toBeNull()
        ->and($carried?->parent_id)->toBe($reportsHeading->getKey())
        ->and($carried?->category)->toBe('committee-reports')
        ->and($carried?->reading_number)->toBeNull()
        ->and($document->fresh()->status)->toBeInstanceOf(CommitteeReportState::class)
        ->and($document->fresh()->current_reading)->toBe(1)
        ->and($next->agendaItems()->where('document_id', $document->getKey())->where('category', 'unfinished-business')->exists())->toBeFalse();
});
