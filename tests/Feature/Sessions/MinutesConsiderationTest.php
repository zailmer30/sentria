<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\MinutesCorrection;
use App\Models\User;
use App\Services\AI\LegislativeMinutesGenerator;
use App\Services\Sessions\AgendaService;
use App\Services\Sessions\MinutesConsiderationService;
use App\States\Document\Registered;
use App\States\Session\Adjourned;
use App\States\Session\InSession;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);

    Storage::fake('local');
});

function minutesConsiderationActor(UserRole $role, string $suffix): User
{
    return User::factory()->create([
        'email' => "{$role->value}-minutes-consider-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole($role->value);
}

function minutesPdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('minutes.pdf', '%PDF-1.4 fake minutes')->mimeType('application/pdf');
}

it('binds registered minutes under reading and consideration without advancing document status', function (): void {
    $secretariat = minutesConsiderationActor(UserRole::Secretariat, 'bind');
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-09-15 09:00:00',
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);

    $document = Document::factory()->ofType(DocumentType::Minutes)->create([
        'status' => Registered::$name,
        'title' => 'Minutes of the last sitting',
    ]);
    $heading = $session->agendaItems()->where('category', 'approval-minutes')->whereNull('document_id')->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.documents.store', [$session, $heading]), [
            'document_ids' => [$document->getKey()],
        ])
        ->assertRedirect();

    $child = $session->agendaItems()->where('document_id', $document->getKey())->first();

    expect($child)->not->toBeNull()
        ->and($child?->parent_id)->toBe($heading->getKey())
        ->and($child?->category)->toBe('approval-minutes')
        ->and($child?->item_number)->toBe('4.1')
        ->and($child?->requires_vote)->toBeFalse()
        ->and($child?->reading_number)->toBeNull()
        ->and($document->fresh()->status)->toBeInstanceOf(Registered::class);
});

it('rejects binding a measure to the minutes heading', function (): void {
    $secretariat = minutesConsiderationActor(UserRole::Secretariat, 'measure');
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-09-16 09:00:00',
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);

    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'status' => Registered::$name,
    ]);
    $heading = $session->agendaItems()->where('category', 'approval-minutes')->whereNull('document_id')->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.documents.store', [$session, $heading]), [
            'document_ids' => [$document->getKey()],
        ])
        ->assertUnprocessable();

    expect($session->agendaItems()->where('document_id', $document->getKey())->exists())->toBeFalse();
});

it('uploads a pdf as registered minutes of the previous sitting and attaches it', function (): void {
    Queue::fake();
    $secretariat = minutesConsiderationActor(UserRole::Secretariat, 'upload');
    $previous = LegislativeSession::factory()->create([
        'title' => 'First Regular Session',
        'session_number' => 'RS-2026-01',
        'type' => 'regular',
        'scheduled_start_at' => '2026-09-01 09:00:00',
    ]);
    $session = LegislativeSession::factory()->create([
        'title' => 'Second Regular Session',
        'session_number' => 'RS-2026-02',
        'type' => 'regular',
        'scheduled_start_at' => '2026-09-18 09:00:00',
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);
    $heading = $session->agendaItems()->where('category', 'approval-minutes')->whereNull('document_id')->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.minutes.store', [$session, $heading]), [
            'file' => minutesPdf(),
        ])
        ->assertRedirect();

    $document = Document::query()->where('document_type', DocumentType::Minutes)->latest()->first();

    expect($document)->not->toBeNull()
        ->and($document?->status)->toBeInstanceOf(Registered::class)
        ->and($document?->session_id)->toBe($previous->getKey())
        ->and($document?->title)->toContain('First Regular Session')
        ->and($session->agendaItems()->where('document_id', $document?->getKey())->exists())->toBeTrue();
});

it('rejects a non-pdf upload on the minutes heading', function (): void {
    $secretariat = minutesConsiderationActor(UserRole::Secretariat, 'docx');
    $session = LegislativeSession::factory()->create([
        'scheduled_start_at' => '2026-09-19 09:00:00',
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);
    $heading = $session->agendaItems()->where('category', 'approval-minutes')->whereNull('document_id')->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.minutes.store', [$session, $heading]), [
            'file' => UploadedFile::fake()->createWithContent('minutes.docx', 'not a pdf')->mimeType('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
        ->assertSessionHasErrors('file');
});

it('suggests registered minutes tagged to the previous sitting', function (): void {
    $secretariat = minutesConsiderationActor(UserRole::Secretariat, 'suggest');
    $previous = LegislativeSession::factory()->create([
        'scheduled_start_at' => '2026-08-04 09:00:00',
    ]);
    $session = LegislativeSession::factory()->create([
        'scheduled_start_at' => '2026-08-18 09:00:00',
    ]);
    $suggested = Document::factory()->ofType(DocumentType::Minutes)->create([
        'status' => Registered::$name,
        'session_id' => $previous->getKey(),
        'title' => 'Minutes of August 4',
    ]);
    Document::factory()->ofType(DocumentType::Minutes)->create([
        'status' => Registered::$name,
        'title' => 'Unrelated minutes',
    ]);

    $ids = app(MinutesConsiderationService::class)->suggestedDocumentIds($session);

    expect($ids)->toBe([$suggested->getKey()]);

    $this->actingAs($secretariat)
        ->get(route('sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('minutes_consideration.previous_session.id', $previous->getKey())
            ->where('minutes_consideration.suggested_document_ids', [$suggested->getKey()]));
});

it('lets secretariat record a correction only while the minutes packet is on the floor', function (): void {
    $secretariat = minutesConsiderationActor(UserRole::Secretariat, 'correct');
    $member = minutesConsiderationActor(UserRole::BoardMember, 'correct');
    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
        'scheduled_start_at' => '2026-09-18 09:00:00',
        'actual_start_at' => now(),
    ]);
    $heading = AgendaItem::factory()->procedural('approval-minutes', 'Reading and Consideration of the Minutes', 4)->create([
        'session_id' => $session->getKey(),
        'status' => 'completed',
    ]);
    $document = Document::factory()->ofType(DocumentType::Minutes)->create([
        'status' => Registered::$name,
    ]);
    $packet = AgendaItem::factory()->procedural('approval-minutes', 'Minutes of the last sitting', 5)->create([
        'session_id' => $session->getKey(),
        'parent_id' => $heading->getKey(),
        'document_id' => $document->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
    ]);

    $this->actingAs($member)
        ->post(route('sessions.agenda.minutes-corrections.store', [$session, $packet]), [
            'as_written' => 'qurom',
            'should_read' => 'quorum',
            'page_number' => 2,
        ])
        ->assertForbidden();

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.minutes-corrections.store', [$session, $packet]), [
            'as_written' => 'qurom',
            'should_read' => 'quorum',
            'page_number' => 2,
        ])
        ->assertRedirect();

    $correction = MinutesCorrection::query()->where('agenda_item_id', $packet->getKey())->first();

    expect($correction)->not->toBeNull()
        ->and($correction?->as_written)->toBe('qurom')
        ->and($correction?->should_read)->toBe('quorum')
        ->and($correction?->page_number)->toBe(2);

    $packet->update(['status' => 'completed', 'completed_at' => now()]);

    $this->actingAs($secretariat)
        ->post(route('sessions.agenda.minutes-corrections.store', [$session, $packet]), [
            'as_written' => 'late edit',
            'should_read' => 'should not save',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(MinutesCorrection::query()->where('agenda_item_id', $packet->getKey())->count())->toBe(1);

    $this->actingAs($secretariat)
        ->put(route('sessions.agenda.minutes-corrections.update', [$session, $packet, $correction]), [
            'as_written' => 'still frozen',
            'should_read' => 'no',
        ])
        ->assertSessionHas('error');

    $this->actingAs($secretariat)
        ->patch(route('sessions.agenda.minutes-corrections.apply', [$session, $packet, $correction]), [
            'applied' => true,
        ])
        ->assertRedirect();

    expect($correction?->fresh()?->applied_at)->not->toBeNull();
});

it('does not mark a correction applied while the packet is still on the floor', function (): void {
    $secretariat = minutesConsiderationActor(UserRole::Secretariat, 'apply-live');
    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
        'scheduled_start_at' => now(),
    ]);
    $document = Document::factory()->ofType(DocumentType::Minutes)->create([
        'status' => Registered::$name,
    ]);
    $packet = AgendaItem::factory()->procedural('approval-minutes', 'Minutes', 4)->create([
        'session_id' => $session->getKey(),
        'document_id' => $document->getKey(),
        'status' => 'in-progress',
    ]);
    $correction = MinutesCorrection::factory()->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $packet->getKey(),
        'document_id' => $document->getKey(),
        'recorded_by' => $secretariat->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->patch(route('sessions.agenda.minutes-corrections.apply', [$session, $packet, $correction]), [
            'applied' => true,
        ])
        ->assertSessionHas('error');

    expect($correction->fresh()?->applied_at)->toBeNull();
});

it('folds considered minutes and corrections into this sitting\'s draft', function (): void {
    $secretariat = minutesConsiderationActor(UserRole::Secretariat, 'draft');
    $session = LegislativeSession::factory()->adjourned()->create([
        'secretary_id' => $secretariat->getKey(),
        'status' => Adjourned::$name,
    ]);
    $document = Document::factory()->ofType(DocumentType::Minutes)->create([
        'status' => Registered::$name,
        'title' => 'Minutes of Regular Session dated 1 September 2026',
    ]);
    $packet = AgendaItem::factory()->procedural(
        'approval-minutes',
        'Minutes of Regular Session dated 1 September 2026',
        4,
    )->create([
        'session_id' => $session->getKey(),
        'document_id' => $document->getKey(),
        'status' => 'completed',
        'item_number' => '4.1',
    ]);
    MinutesCorrection::factory()->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $packet->getKey(),
        'document_id' => $document->getKey(),
        'as_written' => 'qurom',
        'should_read' => 'quorum',
        'page_number' => 2,
        'recorded_by' => $secretariat->getKey(),
    ]);

    $content = (string) app(LegislativeMinutesGenerator::class)->draftFromSession($secretariat, $session->fresh())->content;

    expect($content)->toContain('Considered the minutes: Minutes of Regular Session dated 1 September 2026')
        ->and($content)->toContain('Correction p.2: "qurom" should read "quorum"');
});
