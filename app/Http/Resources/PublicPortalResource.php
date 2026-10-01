<?php

namespace App\Http\Resources;

use App\Enums\SessionType;
use App\Models\LegislativeSession;
use App\Models\Minutes;
use App\Models\Ordinance;
use App\Models\Publication;
use App\Models\Resolution;

class PublicPortalResource
{
    /**
     * @return array<string, mixed>
     */
    public static function publicationSummary(Publication $publication): array
    {
        $document = $publication->document;

        return [
            'slug' => $publication->public_slug,
            'title' => $publication->title,
            'summary' => $publication->summary,
            'categories' => $publication->categories ?? [],
            'published_at' => $publication->published_at?->toIso8601String(),
            'document_type' => $document?->document_type->value,
            'document_type_label' => $document?->document_type->label(),
            'status_label' => $document?->ordinance?->status
                ?? $document?->resolution?->status
                ?? 'Published',
            'author' => $document?->authorName(),
            'committee' => $document?->committee?->name,
            'year' => $publication->published_at?->year,
            'ordinance_number' => $document?->ordinance?->ordinance_number,
            'resolution_number' => $document?->resolution?->resolution_number,
            'reference_number' => $document?->reference_number,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function publicationDetail(Publication $publication): array
    {
        $document = $publication->document;

        return [
            ...self::publicationSummary($publication),
            'abstract' => $document?->abstract,
            'reference_number' => $document?->reference_number,
            'session' => $document?->session?->title,
            'tags' => $document !== null ? ($document->tags ?? []) : [],
            'redaction_applied' => $publication->redaction_applied,
            'ordinance' => $document?->ordinance ? self::ordinance($document->ordinance) : null,
            'resolution' => $document?->resolution ? self::resolution($document->resolution) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function ordinance(Ordinance $ordinance): array
    {
        return [
            'id' => $ordinance->getKey(),
            'ordinance_number' => $ordinance->ordinance_number,
            'series_year' => $ordinance->series_year,
            'title' => $ordinance->title,
            'purpose' => $ordinance->purpose,
            'status' => $ordinance->status,
            'enacted_on' => $ordinance->enacted_on?->toDateString(),
            'effectivity_date' => $ordinance->effectivity_date?->toDateString(),
            'has_signed_copy' => $ordinance->hasSignedCopy(),
            'signed_copy_filename' => $ordinance->hasSignedCopy() ? $ordinance->signed_copy_filename : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolution(Resolution $resolution): array
    {
        return [
            'id' => $resolution->getKey(),
            'resolution_number' => $resolution->resolution_number,
            'series_year' => $resolution->series_year,
            'title' => $resolution->title,
            'purpose' => $resolution->purpose,
            'category' => $resolution->category,
            'status' => $resolution->status,
            'adopted_on' => $resolution->adopted_on?->toDateString(),
            'effectivity_date' => $resolution->effectivity_date?->toDateString(),
            'has_signed_copy' => $resolution->hasSignedCopy(),
            'signed_copy_filename' => $resolution->hasSignedCopy() ? $resolution->signed_copy_filename : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function session(LegislativeSession $session): array
    {
        return [
            'id' => $session->getKey(),
            'session_number' => $session->session_number,
            'title' => $session->title,
            'session_type' => $session->type,
            'session_type_label' => SessionType::tryFrom((string) $session->type)?->label() ?? $session->type,
            'scheduled_start_at' => $session->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $session->scheduled_end_at?->toIso8601String(),
            'location' => $session->venue,
            'status' => $session->status->getValue(),
            'status_label' => $session->status->label(),
            'agenda' => $session->relationLoaded('agendaItems')
                ? $session->agendaItems->take(5)->map(fn ($item) => $item->title)->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function minutes(Minutes $minutes): array
    {
        $session = $minutes->session;

        return [
            'id' => $minutes->getKey(),
            'title' => $session !== null ? $session->title : 'Session minutes',
            'content' => $minutes->content,
            'revision' => $minutes->revision,
            'finalized_at' => $minutes->finalized_at?->toIso8601String(),
            'session' => $session ? self::session($session) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function seo(Publication $publication): array
    {
        $document = $publication->document;
        $description = $publication->summary ?: ($document !== null ? ($document->abstract ?? '') : '');

        return [
            'title' => $publication->title,
            'description' => mb_substr(trim(strip_tags((string) $description)), 0, 160),
            'type' => 'article',
            'url' => url('/portal/documents/'.$publication->public_slug),
        ];
    }
}
