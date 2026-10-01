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

    expect($items)->toHaveCount(17)
        ->and($items->whereNull('parent_id')->pluck('title')->values()->all())->toBe([
            'Call to Order',
            'Invocation',
            'Roll Call',
            'Reading and Consideration of the Minutes',
            'Privilege Hour',
            'Reference of Business',
            'Committee Hour',
            'Calendar of Business',
            'Business on Third and Final Reading',
            'Other Matters / Announcements',
            'Adjournment',
        ])
        ->and($items->firstWhere('category', 'convocation')?->description)->toBe(
            "Opening Prayer, National Anthem, Municipal Hymn and Councilor's Creed"
        )
        ->and($committeeHour)->not->toBeNull()
        ->and($calendar)->not->toBeNull()
        ->and($items->where('parent_id', $committeeHour->getKey())->pluck('item_number')->values()->all())->toBe(['7.1', '7.2', '7.3'])
        ->and($items->where('parent_id', $calendar->getKey())->pluck('item_number')->values()->all())->toBe(['8.1', '8.2', '8.3'])
        ->and($privilege?->time_allotment_minutes)->toBe(10)
        ->and($items->firstWhere('category', 'recess'))->toBeNull()
        ->and($items->last()->category)->toBe('adjournment')
        ->and($items->last()->item_number)->toBe('11');
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

    $convocation = $later->agendaItems()->where('category', 'convocation')->firstOrFail();

    expect($convocation->title)->toBe('Invocation')
        ->and($convocation->description)->toBe("Opening Prayer, Municipal Hymn and Councilor's Creed");
});

it('places a first-reading measure after heading 6 and keeps adjournment last', function (): void {
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
        ->and($created->item_number)->toBe('6.1')
        ->and($created->position)->toBe($heading->position + 1)
        ->and($created->position)->toBeLessThan($committeeHour->position)
        ->and($adjournment->position)->toBe($items->count())
        ->and($adjournment->item_number)->toBe('11');
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
        ->and($fresh?->item_number)->toBe('6.1')
        ->and($reports->fresh()->item_number)->toBe('7.1')
        ->and($session->agendaItems()->where('category', 'adjournment')->first()?->item_number)->toBe('11');
});

it('renumbers a referred measure when it is moved under a different heading', function (): void {
    $session = LegislativeSession::factory()->create([
        'type' => 'committee-hearing',
        'scheduled_start_at' => '2026-04-28 09:00:00',
    ]);
    $agenda = app(AgendaService::class);
    $agenda->prepareStandardTemplate($session);

    $four = $session->agendaItems()->where('item_number', '4')->firstOrFail();
    $five = $session->agendaItems()->where('item_number', '5')->firstOrFail();
    $document = Document::factory()->create([
        'title' => 'Sample Ordinance',
    ]);
    $created = $agenda->createItem($session, [
        'title' => $document->title,
        'document_id' => $document->getKey(),
        'category' => 'referred-measures',
        'parent_id' => $five->getKey(),
    ]);

    expect($created->item_number)->toBe('5.1')
        ->and($created->parent_id)->toBe($five->getKey());

    $orderedIds = $session->agendaItems()->orderBy('position')->pluck('id')->all();
    $orderedIds = array_values(array_filter($orderedIds, fn (string $id): bool => $id !== $created->getKey()));
    $fourIndex = array_search($four->getKey(), $orderedIds, true);
    array_splice($orderedIds, (int) $fourIndex + 1, 0, [$created->getKey()]);

    $agenda->reorder($session, $orderedIds);

    $fresh = $created->fresh();

    expect($fresh)->not->toBeNull()
        ->and($fresh?->parent_id)->toBe($four->getKey())
        ->and($fresh?->item_number)->toBe('4.1')
        ->and($four->fresh()?->item_number)->toBe('4')
        ->and($five->fresh()?->item_number)->toBe('5');
});

it('seeds a six-item committee hearing order of business', function (): void {
    $session = LegislativeSession::factory()->create([
        'type' => 'committee-hearing',
        'scheduled_start_at' => '2026-04-14 09:00:00',
    ]);

    app(AgendaService::class)->prepareStandardTemplate($session);

    $items = $session->agendaItems()->orderBy('position')->get();

    expect($items)->toHaveCount(6)
        ->and($items->pluck('title')->values()->all())->toBe([
            'Call to Order',
            'Invocation',
            'Roll Call',
            'Committee Concerns',
            'Other Matters',
            'Adjournment',
        ])
        ->and($items->pluck('item_number')->values()->all())->toBe(['1', '2', '3', '4', '5', '6'])
        ->and($items->firstWhere('category', 'referred-measures')?->title)->toBe('Committee Concerns')
        ->and($items->firstWhere('category', 'other-matters')?->title)->toBe('Other Matters')
        ->and($items->last()->category)->toBe('adjournment');
});
