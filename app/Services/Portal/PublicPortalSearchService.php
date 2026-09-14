<?php

namespace App\Services\Portal;

use App\Models\Document;
use App\Models\DocumentSearchIndex;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class PublicPortalSearchService
{
    /**
     * @param  array{
     *     keyword?: string|null,
     *     type?: string|null,
     *     year?: int|string|null,
     *     author?: string|null,
     *     committee?: string|null,
     *     status?: string|null,
     *     date_from?: string|null,
     *     date_to?: string|null,
     * }  $filters
     * @return LengthAwarePaginator<int, Publication>
     */
    public function search(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Publication::query()
            ->live()
            ->with(['document.author', 'document.committee', 'document.ordinance', 'document.resolution']);

        if ($keyword = trim((string) ($filters['keyword'] ?? ''))) {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder->where('title', 'ilike', '%'.$keyword.'%')
                    ->orWhere('summary', 'ilike', '%'.$keyword.'%')
                    ->orWhereHas('document', function (Builder $document) use ($keyword): void {
                        /** @var Builder<Document> $document */
                        $document->published()->search($keyword);
                    })
                    ->orWhereHas('document', function (Builder $document) use ($keyword): void {
                        /** @var Builder<Document> $document */
                        $document->published()->whereHas('searchIndex', function (Builder $index) use ($keyword): void {
                            /** @var Builder<DocumentSearchIndex> $index */
                            $index->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$keyword]);
                        });
                    });
            });
        }

        if ($type = $filters['type'] ?? null) {
            $query->whereHas('document', function (Builder $document) use ($type): void {
                /** @var Builder<Document> $document */
                $document->published()->where('document_type', $type);
            });
        }

        if ($year = $filters['year'] ?? null) {
            $query->whereYear('published_at', (int) $year);
        }

        if ($author = trim((string) ($filters['author'] ?? ''))) {
            $query->whereHas('document.author', fn (Builder $user) => $user
                ->where('display_name', 'ilike', '%'.$author.'%'));
        }

        if ($committee = trim((string) ($filters['committee'] ?? ''))) {
            $query->whereHas('document.committee', fn (Builder $committeeQuery) => $committeeQuery
                ->where('name', 'ilike', '%'.$committee.'%')
                ->orWhere('code', 'ilike', '%'.$committee.'%'));
        }

        if ($status = $filters['status'] ?? null) {
            $query->whereHas('document', function (Builder $document) use ($status): void {
                /** @var Builder<Document> $document */
                $document->published()->where('status', $status);
            });
        }

        if ($dateFrom = $filters['date_from'] ?? null) {
            $query->whereDate('published_at', '>=', $dateFrom);
        }

        if ($dateTo = $filters['date_to'] ?? null) {
            $query->whereDate('published_at', '<=', $dateTo);
        }

        /** @var LengthAwarePaginator<int, Publication> $paginator */
        $paginator = $query->latest('published_at')->paginate($perPage)->withQueryString();

        return $paginator;
    }
}
