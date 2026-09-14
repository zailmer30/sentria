<?php

use App\Enums\Confidentiality;
use App\Enums\UserRole;
use App\Events\HallDisplayChanged;
use App\Models\AgendaItem;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\LegislativeSession;
use App\Models\User;
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
