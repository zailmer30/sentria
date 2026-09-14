<?php

namespace App\Http\Controllers;

use App\Contracts\Legislation\HoldsSignedCopy;
use App\Http\Resources\PublicPortalResource;
use App\Models\Committee;
use App\Models\LegislativeSession;
use App\Models\Publication;
use App\Models\User;
use App\Services\Legislation\LegislativeHistoryService;
use App\Services\Legislation\LegislativeSignedCopyService;
use App\Services\Portal\PublicPortal;
use App\Services\Portal\PublicPortalSearchService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalController extends Controller
{
    /**
     * Everything `PublicPortalResource::publicationDetail()` reads off a record.
     *
     * @var list<string>
     */
    private array $documentRelations = [
        'document.author',
        'document.committee',
        'document.ordinance',
        'document.resolution',
        'document.session',
    ];

    public function __construct(
        private readonly PublicPortal $portal,
        private readonly PublicPortalSearchService $search,
        private readonly LegislativeHistoryService $history,
        private readonly LegislativeSignedCopyService $signedCopies,
    ) {}

    public function home(Request $request): Response
    {
        $filters = $this->filters($request);

        /** @var LengthAwarePaginator<int, Publication> $results */
        $results = $this->search->search($filters, 6);

        $publishedCount = Publication::query()->live()->count();
        $earliest = Publication::query()->live()->min('published_at');

        return Inertia::render('Portal/Home', [
            'featured' => collect($results->items())
                ->map(fn (Publication $publication): array => PublicPortalResource::publicationSummary($publication))
                ->values()
                ->all(),
            'filters' => $filters,
            'stats' => [
                'published' => $publishedCount,
                'coverage_since' => $earliest !== null ? Carbon::parse($earliest)->year : 2024,
            ],
        ]);
    }

    public function search(Request $request): Response
    {
        $filters = $this->filters($request);

        /** @var LengthAwarePaginator<int, Publication> $results */
        $results = $this->search->search($filters);

        return Inertia::render('Portal/Search', [
            'results' => $results->through(
                fn (Publication $publication): array => PublicPortalResource::publicationSummary($publication)
            ),
            'filters' => $filters,
            'committees' => Committee::query()->orderBy('name')->pluck('name')->values()->all(),
            'years' => range((int) date('Y'), 2024),
        ]);
    }

    public function document(string $publicSlug): Response
    {
        $publication = $this->portal->findPublishedOrAbort404($publicSlug);
        $publication->increment('view_count');
        $document = $publication->document;

        $related = [];
        $committeeId = $document?->committee_id;
        if ($committeeId !== null) {
            $related = Publication::query()
                ->live()
                ->whereKeyNot($publication->getKey())
                ->whereHas('document', fn ($query) => $query->where('committee_id', $committeeId))
                ->with(['document.author', 'document.committee', 'document.ordinance', 'document.resolution'])
                ->latest('published_at')
                ->limit(3)
                ->get()
                ->map(fn (Publication $item): array => PublicPortalResource::publicationSummary($item))
                ->values()
                ->all();
        }

        return Inertia::render('Portal/DocumentShow', [
            'publication' => PublicPortalResource::publicationDetail($publication),
            'seo' => PublicPortalResource::seo($publication),
            'related' => $related,
        ]);
    }

    public function ordinance(string $identifier): Response
    {
        $ordinance = $this->portal->findPublishedOrdinanceOrAbort404($identifier);
        $publication = $this->portal->livePublicationFor($ordinance);

        if ($publication === null) {
            abort(404);
        }

        $publication->increment('view_count');
        $publication->loadMissing($this->documentRelations);

        return Inertia::render('Portal/DocumentShow', [
            'publication' => PublicPortalResource::publicationDetail($publication),
            'seo' => PublicPortalResource::seo($publication),
            'variant' => 'ordinance',
        ]);
    }

    public function resolution(string $identifier): Response
    {
        $resolution = $this->portal->findPublishedResolutionOrAbort404($identifier);
        $publication = $this->portal->livePublicationFor($resolution);

        if ($publication === null) {
            abort(404);
        }

        $publication->increment('view_count');
        $publication->loadMissing($this->documentRelations);

        return Inertia::render('Portal/DocumentShow', [
            'publication' => PublicPortalResource::publicationDetail($publication),
            'seo' => PublicPortalResource::seo($publication),
            'variant' => 'resolution',
        ]);
    }

    public function sessions(Request $request): Response
    {
        $type = $request->string('type')->toString() ?: null;

        $query = LegislativeSession::query()
            ->where('is_public', true)
            ->whereNotNull('scheduled_start_at')
            ->with(['agendaItems' => fn ($items) => $items->orderBy('position')])
            ->orderBy('scheduled_start_at');

        if ($type !== null) {
            $query->where('type', $type);
        }

        /** @var LengthAwarePaginator<int, LegislativeSession> $sessions */
        $sessions = $query->paginate(20);

        return Inertia::render('Portal/Sessions', [
            'sessions' => $sessions->through(
                fn (LegislativeSession $session): array => PublicPortalResource::session($session)
            ),
            'filters' => ['type' => $type],
        ]);
    }

    public function minutes(string $minute): Response
    {
        $record = $this->portal->findPublicMinutesOrAbort404($minute);
        $record->loadMissing('session');
        $session = $record->session;
        $title = $session !== null ? $session->title : 'Session minutes';

        return Inertia::render('Portal/MinutesShow', [
            'minutes' => PublicPortalResource::minutes($record),
            'seo' => [
                'title' => $title,
                'description' => mb_substr(trim(strip_tags((string) $record->content)), 0, 160),
                'type' => 'article',
                'url' => url('/portal/minutes/'.$record->getKey()),
            ],
        ]);
    }

    public function documentSignedCopyPreview(Request $request, string $publicSlug): StreamedResponse
    {
        return $this->streamSignedCopy(
            $this->portal->findPublishedSignedCopyOrAbort404($publicSlug),
            false,
            $request,
        );
    }

    public function documentSignedCopyDownload(Request $request, string $publicSlug): StreamedResponse
    {
        return $this->streamSignedCopy(
            $this->portal->findPublishedSignedCopyOrAbort404($publicSlug),
            true,
            $request,
        );
    }

    public function ordinanceSignedCopyPreview(Request $request, string $identifier): StreamedResponse
    {
        return $this->streamSignedCopy(
            $this->portal->findPublishedOrdinanceSignedCopyOrAbort404($identifier),
            false,
            $request,
        );
    }

    public function ordinanceSignedCopyDownload(Request $request, string $identifier): StreamedResponse
    {
        return $this->streamSignedCopy(
            $this->portal->findPublishedOrdinanceSignedCopyOrAbort404($identifier),
            true,
            $request,
        );
    }

    public function resolutionSignedCopyPreview(Request $request, string $identifier): StreamedResponse
    {
        return $this->streamSignedCopy(
            $this->portal->findPublishedResolutionSignedCopyOrAbort404($identifier),
            false,
            $request,
        );
    }

    public function resolutionSignedCopyDownload(Request $request, string $identifier): StreamedResponse
    {
        return $this->streamSignedCopy(
            $this->portal->findPublishedResolutionSignedCopyOrAbort404($identifier),
            true,
            $request,
        );
    }

    public function history(string $slug): Response
    {
        $document = $this->portal->findPublishedHistoryDocumentOrAbort404($slug);
        $publication = $this->portal->livePublicationFor($document);
        $title = $publication !== null ? $publication->title : $document->title;
        $historySlug = $publication !== null ? $publication->public_slug : $document->slug;

        return Inertia::render('Portal/History', [
            'subject' => [
                'title' => $title,
                'slug' => $historySlug,
                'reference_number' => $document->reference_number,
            ],
            'events' => array_map(
                fn ($event) => $event->toArray(),
                $this->history->forDocument($document),
            ),
            'seo' => [
                'title' => $title.' — Legislative history',
                'description' => 'Public legislative history for '.$title,
                'type' => 'website',
                'url' => url('/portal/history/'.$slug),
            ],
        ]);
    }

    /**
     * @return array{
     *     keyword?: string|null,
     *     type?: string|null,
     *     year?: int|string|null,
     *     author?: string|null,
     *     committee?: string|null,
     *     status?: string|null,
     *     date_from?: string|null,
     *     date_to?: string|null,
     * }
     */
    private function filters(Request $request): array
    {
        return [
            'keyword' => $request->string('keyword')->toString() ?: null,
            'type' => $request->string('type')->toString() ?: null,
            'year' => $request->integer('year') ?: null,
            'author' => $request->string('author')->toString() ?: null,
            'committee' => $request->string('committee')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'date_from' => $request->string('date_from')->toString() ?: null,
            'date_to' => $request->string('date_to')->toString() ?: null,
        ];
    }

    private function streamSignedCopy(HoldsSignedCopy $record, bool $download, Request $request): StreamedResponse
    {
        $actor = $request->user();

        return $this->signedCopies->stream(
            $record,
            $download,
            $actor instanceof User ? $actor : null,
        );
    }
}
