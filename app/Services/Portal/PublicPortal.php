<?php

namespace App\Services\Portal;

use App\Contracts\Legislation\HoldsSignedCopy;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\Minutes;
use App\Models\Ordinance;
use App\Models\Publication;
use App\Models\Resolution;
use App\States\Minutes\FinalMinutes;
use App\States\Publication\Published;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Single gate for anonymous public portal access. Unpublished or restricted
 * records must abort with 404 — never 403 — so existence is not confirmed.
 */
class PublicPortal
{
    public function findPublishedOrAbort404(string $slug): Publication
    {
        $publication = Publication::query()
            ->live()
            ->where('public_slug', $slug)
            ->with([
                'document.author',
                'document.committee',
                'document.ordinance',
                'document.resolution',
                'document.session',
                'documentVersion',
            ])
            ->first();

        if ($publication === null) {
            abort(404);
        }

        return $publication;
    }

    public function findPublishedOrdinanceOrAbort404(string $identifier): Ordinance
    {
        $publication = Publication::query()
            ->live()
            ->where('public_slug', $identifier)
            ->whereHas('document.ordinance')
            ->with(['document.ordinance.document'])
            ->first();

        if ($publication !== null && $publication->document?->ordinance !== null) {
            return $publication->document->ordinance;
        }

        $ordinance = Ordinance::query()
            ->whereKey($identifier)
            ->whereHas('document', fn (Builder $query) => $query
                ->where('is_public', true)
                ->whereNotNull('published_at'))
            ->whereHas('document.publications', fn (Builder $query) => $query
                ->where('status', Published::$name)
                ->whereNotNull('published_at')
                ->whereNull('unpublished_at'))
            ->with('document')
            ->first();

        if ($ordinance === null) {
            abort(404);
        }

        return $ordinance;
    }

    public function findPublishedResolutionOrAbort404(string $identifier): Resolution
    {
        $publication = Publication::query()
            ->live()
            ->where('public_slug', $identifier)
            ->whereHas('document.resolution')
            ->with(['document.resolution.document'])
            ->first();

        if ($publication !== null && $publication->document?->resolution !== null) {
            return $publication->document->resolution;
        }

        $resolution = Resolution::query()
            ->whereKey($identifier)
            ->whereHas('document', fn (Builder $query) => $query
                ->where('is_public', true)
                ->whereNotNull('published_at'))
            ->whereHas('document.publications', fn (Builder $query) => $query
                ->where('status', Published::$name)
                ->whereNotNull('published_at')
                ->whereNull('unpublished_at'))
            ->with('document')
            ->first();

        if ($resolution === null) {
            abort(404);
        }

        return $resolution;
    }

    public function findPublishedDocumentOrAbort404(string $slug): Document
    {
        $publication = $this->findPublishedOrAbort404($slug);

        $document = $publication->document;

        if ($document === null || ! $document->is_public || $document->published_at === null) {
            abort(404);
        }

        return $document->load(['author', 'committee', 'ordinance', 'resolution']);
    }

    public function findPublicMinutesOrAbort404(string $id): Minutes
    {
        $minutes = Minutes::query()
            ->with('session')
            ->whereKey($id)
            ->first();

        if ($minutes === null) {
            abort(404);
        }

        $session = $minutes->session;

        if ($session === null || ! $session->is_public) {
            abort(404);
        }

        if (! in_array($minutes->status->getValue(), [FinalMinutes::$name, 'archive'], true)) {
            abort(404);
        }

        return $minutes;
    }

    public function findPublishedHistoryDocumentOrAbort404(string $slug): Document
    {
        $publication = Publication::query()
            ->live()
            ->where('public_slug', $slug)
            ->with('document')
            ->first();

        if ($publication !== null && $publication->document !== null) {
            return $publication->document;
        }

        $document = Document::query()
            ->published()
            ->where('slug', $slug)
            ->whereHas('publications', fn (Builder $query) => $query
                ->where('status', Published::$name)
                ->whereNotNull('published_at')
                ->whereNull('unpublished_at'))
            ->first();

        if ($document === null) {
            abort(404);
        }

        return $document;
    }

    public function livePublicationFor(Model $model): ?Publication
    {
        if ($model instanceof Publication) {
            return $model->status instanceof Published
                && $model->published_at !== null
                && $model->unpublished_at === null
                ? $model
                : null;
        }

        if ($model instanceof Document) {
            return $model->publications()->live()->first();
        }

        if ($model instanceof Ordinance || $model instanceof Resolution) {
            return $model->document?->publications()->live()->first();
        }

        return null;
    }

    public function findPublishedSignedCopyOrAbort404(string $slug): HoldsSignedCopy
    {
        $publication = $this->findPublishedOrAbort404($slug);

        return $this->signedCopyFromPublicationOrAbort404($publication);
    }

    public function findPublishedOrdinanceSignedCopyOrAbort404(string $identifier): HoldsSignedCopy
    {
        $ordinance = $this->findPublishedOrdinanceOrAbort404($identifier);

        if (! $ordinance->hasSignedCopy()) {
            abort(404);
        }

        return $ordinance;
    }

    public function findPublishedResolutionSignedCopyOrAbort404(string $identifier): HoldsSignedCopy
    {
        $resolution = $this->findPublishedResolutionOrAbort404($identifier);

        if (! $resolution->hasSignedCopy()) {
            abort(404);
        }

        return $resolution;
    }

    public function assertPublicSession(LegislativeSession $session): void
    {
        if (! $session->is_public) {
            throw new NotFoundHttpException;
        }
    }

    private function signedCopyFromPublicationOrAbort404(Publication $publication): HoldsSignedCopy
    {
        $record = $publication->document?->ordinance ?? $publication->document?->resolution;

        if (! $record instanceof HoldsSignedCopy || ! $record->hasSignedCopy()) {
            abort(404);
        }

        return $record;
    }
}
