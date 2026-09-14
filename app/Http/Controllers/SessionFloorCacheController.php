<?php

namespace App\Http\Controllers;

use App\Http\Resources\SessionResource;
use App\Models\LegislativeSession;
use App\Services\Documents\DocumentAccessService;
use App\Services\Sessions\AgendaService;
use App\Services\Sessions\AttendanceService;
use App\Services\Sessions\QuorumService;
use App\Services\Sessions\VotingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionFloorCacheController extends Controller
{
    public function __construct(
        private readonly QuorumService $quorum,
        private readonly AgendaService $agenda,
        private readonly DocumentAccessService $access,
        private readonly VotingService $voting,
        private readonly AttendanceService $attendance,
    ) {}

    public function __invoke(Request $request, LegislativeSession $session): JsonResponse
    {
        $this->authorize('view', $session);

        $user = $this->requireUser($request);
        $payload = SessionResource::floor(
            $session,
            $user,
            $this->quorum,
            $this->agenda,
            $this->access,
            $this->voting,
            $this->attendance,
        );

        $documentUrls = [];

        $session->loadMissing(['agendaItems.document.currentVersion']);

        foreach ($session->agendaItems as $item) {
            $document = $item->document;
            if ($document === null || ! $this->access->userCanView($user, $document)) {
                continue;
            }

            $version = $document->currentVersion;
            if ($version === null || ! $version->isSafeToServe()) {
                continue;
            }

            $documentUrls[] = route('documents.versions.download', [
                'document' => $document->slug,
                'version' => $version->getKey(),
            ]);
        }

        return response()->json([
            'cached_at' => now()->toIso8601String(),
            'floor' => $payload,
            'document_urls' => array_values(array_unique($documentUrls)),
        ]);
    }
}
