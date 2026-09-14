<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\Committee;
use App\Models\CommitteeReferral;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\States\Document\AgendaInclusion;
use App\States\Document\CommitteeReferral as CommitteeReferralState;
use App\States\Document\ReadingDeliberation;
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

function floorReferralActor(UserRole $role, string $suffix): User
{
    return User::factory()->create([
        'email' => "{$role->value}-floor-refer-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole($role->value);
}

/**
 * @return array{0: LegislativeSession, 1: AgendaItem, 2: Document}
 */
function floorReferralSitting(Document $document, int $readingNumber, string $itemStatus = 'in-progress'): array
{
    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
        'actual_start_at' => now(),
        'seated_member_count' => 12,
    ]);

    $item = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'document_id' => $document->getKey(),
        'status' => $itemStatus,
        'started_at' => $itemStatus === 'in-progress' ? now() : null,
        'position' => 1,
        'reading_number' => $readingNumber,
        'title' => $document->title,
    ]);

    return [$session, $item, $document];
}

it('lets the secretariat refer a first-reading measure from the floor', function (): void {
    $secretariat = floorReferralActor(UserRole::Secretariat, 'clerk');
    $committee = Committee::factory()->create(['name' => 'Committee on Health']);
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'author_id' => $secretariat->getKey(),
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'Sample Ordinance on Health',
    ]);

    [$session, $item] = floorReferralSitting($document, 1);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Secretariat')
            ->where('can.refer', true)
            ->where('reading_pack.0.can_refer', true)
            ->where('reading_pack.0.reading_number', 1)
            ->has('committees', 1)
            ->where('committees.0.id', $committee->getKey()));

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->post(route('sessions.floor.refer', $session), [
            'agenda_item_id' => $item->getKey(),
            'committee_id' => $committee->getKey(),
        ])
        ->assertRedirect(route('sessions.floor.secretariat', $session))
        ->assertSessionHas('success', 'sessions.floor.referred');

    $fresh = $document->fresh();

    expect($fresh->status)->toBeInstanceOf(CommitteeReferralState::class)
        ->and($fresh->committee_id)->toBe($committee->getKey())
        ->and($fresh->current_reading)->toBe(1);

    $this->assertDatabaseHas('committee_referrals', [
        'document_id' => $document->getKey(),
        'committee_id' => $committee->getKey(),
        'session_id' => $session->getKey(),
        'agenda_item_id' => $item->getKey(),
        'status' => 'pending',
        'referred_by' => $secretariat->getKey(),
    ]);

    expect(CommitteeReferral::query()->where('document_id', $document->getKey())->count())->toBe(1);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reading_pack.0.can_refer', false)
            ->where('reading_pack.0.document.status', CommitteeReferralState::$name)
            ->where('reading_pack.0.document.committee', 'Committee on Health'));
});

it('refers from the floor when first reading is already open', function (): void {
    $secretariat = floorReferralActor(UserRole::Secretariat, 'open-read');
    $committee = Committee::factory()->create();
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'author_id' => $secretariat->getKey(),
        'status' => ReadingDeliberation::$name,
        'current_reading' => 1,
    ]);

    [$session, $item] = floorReferralSitting($document, 1);

    $this->actingAs($secretariat)
        ->post(route('sessions.floor.refer', $session), [
            'agenda_item_id' => $item->getKey(),
            'committee_id' => $committee->getKey(),
        ])
        ->assertRedirect(route('sessions.floor.secretariat', $session));

    expect($document->fresh()->status)->toBeInstanceOf(CommitteeReferralState::class);
});

it('forbids a member from referring on the floor', function (): void {
    $secretariat = floorReferralActor(UserRole::Secretariat, 'owner');
    $member = floorReferralActor(UserRole::BoardMember, 'member');
    $committee = Committee::factory()->create();
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'author_id' => $secretariat->getKey(),
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
    ]);

    [$session, $item] = floorReferralSitting($document, 1);

    $this->actingAs($member)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.refer', false)
            ->where('reading_pack.0.can_refer', false)
            ->where('committees', []));

    $this->actingAs($member)
        ->post(route('sessions.floor.refer', $session), [
            'agenda_item_id' => $item->getKey(),
            'committee_id' => $committee->getKey(),
        ])
        ->assertForbidden();

    expect($document->fresh()->status)->toBeInstanceOf(AgendaInclusion::class);
});

it('rejects floor referral for a second-reading item', function (): void {
    $secretariat = floorReferralActor(UserRole::Secretariat, 'second');
    $committee = Committee::factory()->create();
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'author_id' => $secretariat->getKey(),
        'status' => ReadingDeliberation::$name,
        'current_reading' => 2,
    ]);

    [$session, $item] = floorReferralSitting($document, 2);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.refer', true)
            ->where('reading_pack.0.can_refer', false));

    $this->actingAs($secretariat)
        ->post(route('sessions.floor.refer', $session), [
            'agenda_item_id' => $item->getKey(),
            'committee_id' => $committee->getKey(),
        ])
        ->assertStatus(422);

    expect($document->fresh()->status)->toBeInstanceOf(ReadingDeliberation::class)
        ->and(CommitteeReferral::query()->where('document_id', $document->getKey())->count())->toBe(0);
});

it('still advances a first-reading item that was not referred to committee', function (): void {
    $secretariat = floorReferralActor(UserRole::Secretariat, 'unreferred-advance');
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'author_id' => $secretariat->getKey(),
        'status' => AgendaInclusion::$name,
        'current_reading' => 1,
        'title' => 'Unreferred First Reading Measure',
    ]);

    [$session, $item] = floorReferralSitting($document, 1);

    $next = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'status' => 'pending',
        'position' => 2,
        'title' => 'Next heading',
    ]);

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->post(route('sessions.agenda.advance', $session))
        ->assertRedirect(route('sessions.floor.secretariat', $session))
        ->assertSessionHas('success', 'sessions.agenda_advanced');

    expect($item->fresh()->status)->toBe('completed')
        ->and($next->fresh()->status)->toBe('in-progress')
        ->and($document->fresh()->status)->toBeInstanceOf(AgendaInclusion::class)
        ->and(CommitteeReferral::query()->where('document_id', $document->getKey())->count())->toBe(0);
});
