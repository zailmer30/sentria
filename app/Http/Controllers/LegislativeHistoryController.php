<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Ordinance;
use App\Services\Legislation\LegislativeHistoryService;
use Inertia\Inertia;
use Inertia\Response;

class LegislativeHistoryController extends Controller
{
    public function __construct(private readonly LegislativeHistoryService $history) {}

    public function forOrdinance(Ordinance $ordinance): Response
    {
        $this->authorize('view', $ordinance);
        $ordinance->load('document');

        return Inertia::render('Legislation/History', [
            'subject' => [
                'type' => 'ordinance',
                'id' => $ordinance->getKey(),
                'title' => $ordinance->title,
                'number' => $ordinance->ordinance_number,
                'document_slug' => $ordinance->document?->slug,
            ],
            'events' => array_map(
                fn ($event) => $event->toArray(),
                $this->history->forOrdinance($ordinance),
            ),
        ]);
    }

    public function forDocument(Document $document): Response
    {
        $this->authorize('view', $document);

        return Inertia::render('Legislation/History', [
            'subject' => [
                'type' => 'document',
                'id' => $document->getKey(),
                'title' => $document->title,
                'number' => $document->reference_number,
                'document_slug' => $document->slug,
            ],
            'events' => array_map(
                fn ($event) => $event->toArray(),
                $this->history->forDocument($document),
            ),
        ]);
    }
}
