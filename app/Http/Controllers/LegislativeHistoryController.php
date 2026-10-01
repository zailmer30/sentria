<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Services\Legislation\LegislativeHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LegislativeHistoryController extends Controller
{
    public function __construct(private readonly LegislativeHistoryService $history) {}

    public function forOrdinance(Request $request, Ordinance $ordinance): Response|JsonResponse
    {
        $this->authorize('view', $ordinance);
        $ordinance->load('document');

        return $this->present($request, [
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

    public function forResolution(Request $request, Resolution $resolution): Response|JsonResponse
    {
        $this->authorize('view', $resolution);
        $resolution->load('document');

        return $this->present($request, [
            'subject' => [
                'type' => 'resolution',
                'id' => $resolution->getKey(),
                'title' => $resolution->title,
                'number' => $resolution->resolution_number,
                'document_slug' => $resolution->document?->slug,
            ],
            'events' => array_map(
                fn ($event) => $event->toArray(),
                $this->history->forResolution($resolution),
            ),
        ]);
    }

    public function forDocument(Request $request, Document $document): Response|JsonResponse
    {
        $this->authorize('view', $document);

        return $this->present($request, [
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

    /**
     * @param  array{subject: array<string, mixed>, events: list<array<string, mixed>>}  $payload
     */
    private function present(Request $request, array $payload): Response|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Legislation/History', $payload);
    }
}
