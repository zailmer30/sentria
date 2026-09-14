<?php

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\LegislativeSession;
use App\Models\User;
use App\States\Session\Adjourned;
use App\States\Session\AgendaPrepared;
use App\States\Session\Draft;
use App\States\Session\InSession;
use App\States\Session\Scheduled;
use App\States\Session\Suspended;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);
});

function sessionActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-sessions@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole($role->value);
}

it('runs the session lifecycle through prepare, start, advance, and adjourn', function (): void {
    $secretariat = sessionActor(UserRole::Secretariat);
    $presiding = sessionActor(UserRole::PresidingOfficer);

    $create = $this->actingAs($secretariat)->post(route('sessions.store'), [
        'session_number' => 'RS-901',
        'title' => '901st Regular Session',
        'type' => 'regular',
        'legislative_year' => 2026,
        'venue' => 'Session Hall',
        'seated_member_count' => 12,
    ]);

    $create->assertRedirect();
    $session = LegislativeSession::query()->firstOrFail();
    expect($session->status)->toBeInstanceOf(Draft::class);

    $this->actingAs($secretariat)->post(route('sessions.prepare-agenda', $session))->assertRedirect();
    $session = $session->fresh();
    expect($session->status)->toBeInstanceOf(AgendaPrepared::class)
        ->and($session->agendaItems()->count())->toBe(18);

    $this->actingAs($secretariat)->post(route('sessions.schedule', $session))->assertRedirect();
    expect($session->fresh()->status)->toBeInstanceOf(Scheduled::class);

    $this->actingAs($secretariat)->post(route('sessions.start', $session))->assertRedirect();
    $session = $session->fresh();
    expect($session->status)->toBeInstanceOf(InSession::class)
        ->and($session->agendaItems()->where('status', 'in-progress')->count())->toBe(1);

    // Starting again while already in session must not throw TransitionNotFound.
    $this->actingAs($secretariat)->post(route('sessions.start', $session))->assertRedirect();
    expect($session->fresh()->status)->toBeInstanceOf(InSession::class);

    $this->actingAs($secretariat)->post(route('sessions.agenda.advance', $session))->assertRedirect();
    expect($session->fresh()->agendaItems()->where('status', 'completed')->count())->toBe(1)
        ->and($session->fresh()->agendaItems()->where('status', 'in-progress')->count())->toBe(1);

    $this->actingAs($presiding)->post(route('sessions.adjourn', $session))->assertRedirect();
    expect($session->fresh()->status)->toBeInstanceOf(Adjourned::class);
});

it('allows adjourning a suspended session without resuming first', function (): void {
    $secretariat = sessionActor(UserRole::Secretariat);
    $presiding = sessionActor(UserRole::PresidingOfficer);
    $session = LegislativeSession::factory()->create([
        'status' => Scheduled::$name,
    ]);

    $this->actingAs($secretariat)->post(route('sessions.start', $session))->assertRedirect();
    expect($session->fresh()->status)->toBeInstanceOf(InSession::class);

    $this->actingAs($presiding)->post(route('sessions.suspend', $session))->assertRedirect();
    expect($session->fresh()->status)->toBeInstanceOf(Suspended::class);

    $this->actingAs($presiding)->post(route('sessions.adjourn', $session))->assertRedirect();
    expect($session->fresh()->status)->toBeInstanceOf(Adjourned::class);
});

it('reorders agenda items by the submitted sequence', function (): void {
    $secretariat = sessionActor(UserRole::Secretariat);
    $session = LegislativeSession::factory()->create();
    $first = AgendaItem::factory()->procedural('call-to-order', 'Call to Order', 1)->create([
        'session_id' => $session->getKey(),
    ]);
    $second = AgendaItem::factory()->procedural('roll-call', 'Roll Call', 2)->create([
        'session_id' => $session->getKey(),
    ]);
    $third = AgendaItem::factory()->procedural('approval-minutes', 'Approval of Previous Minutes', 3)->create([
        'session_id' => $session->getKey(),
    ]);

    $this->actingAs($secretariat)->post(route('sessions.agenda.reorder', $session), [
        'ordered_ids' => [$third->getKey(), $first->getKey(), $second->getKey()],
    ])->assertRedirect();

    expect($third->fresh()->position)->toBe(1)
        ->and($first->fresh()->position)->toBe(2)
        ->and($second->fresh()->position)->toBe(3)
        ->and($third->fresh()->item_number)->toBe('1');
});

it('renders the session edit page when agenda items have linked documents', function (): void {
    $secretariat = sessionActor(UserRole::Secretariat);
    $session = LegislativeSession::factory()->create();
    $document = Document::factory()->create();

    AgendaItem::factory()->procedural('new-business', 'Sample Agenda', 1)->create([
        'session_id' => $session->getKey(),
        'document_id' => $document->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->get(route('sessions.edit', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Edit')
            ->where('session.id', $session->getKey())
            ->where('session.agenda_items.0.document.slug', $document->slug));
});

it('exposes a pdf preview url on agenda documents the viewer may download', function (): void {
    Storage::fake('local');

    $secretariat = sessionActor(UserRole::Secretariat);
    $session = LegislativeSession::factory()->create();
    $document = Document::factory()->create();
    $path = 'documents/agenda-preview/'.$document->getKey().'.pdf';

    Storage::disk('local')->put($path, "%PDF-1.4\n%%EOF");

    $version = DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
        'disk' => 'local',
        'file_path' => $path,
        'original_filename' => 'filing.pdf',
        'mime_type' => 'application/pdf',
        'scan_status' => 'skipped',
        'is_current' => true,
    ]);

    AgendaItem::factory()->procedural('new-business', 'Sample Agenda', 1)->create([
        'session_id' => $session->getKey(),
        'document_id' => $document->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->get(route('sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Show')
            ->where('session.agenda_items.0.document.slug', $document->slug)
            ->where('session.agenda_items.0.document.can_preview', true)
            ->where(
                'session.agenda_items.0.document.preview_url',
                route('documents.versions.preview', [$document, $version]),
            ));
});
