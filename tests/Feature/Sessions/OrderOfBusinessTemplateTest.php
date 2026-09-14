<?php

use App\Models\Document;
use App\Models\LegislativeSession;
use App\Services\Sessions\AgendaService;

it('seeds the Sanggunian order of business with committee and calendar subsections', function (): void {
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-04-07 09:00:00',
    ]);

    app(AgendaService::class)->prepareStandardTemplate($session);

    $items = $session->agendaItems()->orderBy('position')->get();
    $committeeHour = $items->firstWhere('category', 'committee-hour');
    $calendar = $items->firstWhere('category', 'calendar-of-business');
    $privilege = $items->firstWhere('category', 'privilege-hour');

    expect($items)->toHaveCount(18)
        ->and($items->whereNull('parent_id')->pluck('title')->values()->all())->toBe([
            'Call to Order',
            'Opening Prayer',
            "National Anthem, Municipal Hymn and Councilor's Creed",
            'Roll Call',
            'Reading and Approval of the Minutes of the Previous Session',
            'Privilege Hour',
            'First Reading and Referral to Committee',
            'Committee Hour',
            'Calendar of Business',
            'Business on Third and Final Reading',
            'Other Matters / Announcements',
            'Adjournment',
        ])
        ->and($committeeHour)->not->toBeNull()
        ->and($calendar)->not->toBeNull()
        ->and($items->where('parent_id', $committeeHour->getKey())->pluck('item_number')->values()->all())->toBe(['8.1', '8.2', '8.3'])
        ->and($items->where('parent_id', $calendar->getKey())->pluck('item_number')->values()->all())->toBe(['9.1', '9.2', '9.3'])
        ->and($privilege?->time_allotment_minutes)->toBe(10)
        ->and($items->firstWhere('category', 'recess'))->toBeNull()
        ->and($items->last()->category)->toBe('adjournment')
        ->and($items->last()->item_number)->toBe('12');
});

it('omits the National Anthem when the session is not the first regular sitting of the month', function (): void {
    LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-05-05 09:00:00',
    ]);

    $later = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-05-19 09:00:00',
    ]);

    app(AgendaService::class)->prepareStandardTemplate($later);

    $anthem = $later->agendaItems()->where('category', 'anthem')->firstOrFail();

    expect($anthem->title)->toBe("Municipal Hymn and Councilor's Creed");
});

it('places a first-reading measure after heading 7 and keeps adjournment last', function (): void {
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-06-02 09:00:00',
    ]);
    $document = Document::factory()->create([
        'title' => 'An ordinance on first reading',
    ]);

    $agenda = app(AgendaService::class);
    $agenda->prepareStandardTemplate($session);

    $created = $agenda->createItem($session, [
        'title' => $document->title,
        'document_id' => $document->getKey(),
        'category' => 'first-reading',
        'reading_number' => 1,
        'requires_vote' => false,
    ]);

    $items = $session->agendaItems()->orderBy('position')->get();
    $heading = $items->firstWhere('category', 'first-reading');
    $committeeHour = $items->firstWhere('category', 'committee-hour');
    $adjournment = $items->firstWhere('category', 'adjournment');

    expect($heading)->not->toBeNull()
        ->and($committeeHour)->not->toBeNull()
        ->and($adjournment)->not->toBeNull()
        ->and($created->parent_id)->toBe($heading->getKey())
        ->and($created->item_number)->toBe('7.1')
        ->and($created->position)->toBe($heading->position + 1)
        ->and($created->position)->toBeLessThan($committeeHour->position)
        ->and($adjournment->position)->toBe($items->count())
        ->and($adjournment->item_number)->toBe('12');
});

it('keeps dotted item numbers and parent_id when the agenda is reordered', function (): void {
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-07-07 09:00:00',
    ]);
    $agenda = app(AgendaService::class);
    $agenda->prepareStandardTemplate($session);

    $document = Document::factory()->create([
        'title' => 'An ordinance nested under first reading',
    ]);
    $created = $agenda->createItem($session, [
        'title' => $document->title,
        'document_id' => $document->getKey(),
        'category' => 'first-reading',
        'reading_number' => 1,
    ]);

    $orderedIds = $session->agendaItems()->orderBy('position')->pluck('id')->all();
    $agenda->reorder($session, $orderedIds);

    $heading = $session->agendaItems()->where('category', 'first-reading')->whereNull('document_id')->firstOrFail();
    $reports = $session->agendaItems()->where('category', 'committee-reports')->firstOrFail();
    $fresh = $created->fresh();

    expect($fresh)->not->toBeNull()
        ->and($fresh?->parent_id)->toBe($heading->getKey())
        ->and($fresh?->item_number)->toBe('7.1')
        ->and($reports->fresh()->item_number)->toBe('8.1')
        ->and($session->agendaItems()->where('category', 'adjournment')->first()?->item_number)->toBe('12');
});
