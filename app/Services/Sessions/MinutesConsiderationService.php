<?php

namespace App\Services\Sessions;

use App\Enums\Confidentiality;
use App\Enums\DocumentOrigin;
use App\Enums\DocumentType;
use App\Events\MinutesCorrectionsChanged;
use App\Models\AgendaItem;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\MinutesCorrection;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentVersionService;
use App\States\Document\Registered;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class MinutesConsiderationService
{
    public const HEADING_CATEGORY = 'approval-minutes';

    public function __construct(
        private readonly AgendaService $agenda,
        private readonly DocumentVersionService $versions,
        private readonly AuditLogger $audit,
    ) {}

    public function previousSitting(LegislativeSession $session): ?LegislativeSession
    {
        $at = $session->scheduled_start_at ?? now();

        return LegislativeSession::query()
            ->whereKeyNot($session->getKey())
            ->whereNotNull('scheduled_start_at')
            ->where('scheduled_start_at', '<', $at)
            ->orderByDesc('scheduled_start_at')
            ->first();
    }

    /**
     * @return Collection<int, LegislativeSession>
     */
    public function priorSittings(LegislativeSession $session, int $limit = 12): Collection
    {
        $at = $session->scheduled_start_at ?? now();

        return LegislativeSession::query()
            ->whereKeyNot($session->getKey())
            ->whereNotNull('scheduled_start_at')
            ->where('scheduled_start_at', '<', $at)
            ->orderByDesc('scheduled_start_at')
            ->limit($limit)
            ->get(['id', 'session_number', 'title', 'scheduled_start_at']);
    }

    /**
     * @return list<string>
     */
    public function suggestedDocumentIds(LegislativeSession $session): array
    {
        $previous = $this->previousSitting($session);

        if (! $previous instanceof LegislativeSession) {
            return [];
        }

        $linked = $session->agendaItems()->pluck('document_id')->filter()->all();

        return Document::query()
            ->where('document_type', DocumentType::Minutes)
            ->where('status', Registered::$name)
            ->where('session_id', $previous->getKey())
            ->when($linked !== [], fn ($query) => $query->whereKeyNot($linked))
            ->orderByDesc('updated_at')
            ->limit(20)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bindableDocuments(LegislativeSession $session): array
    {
        $linked = $session->agendaItems()->pluck('document_id')->filter()->all();
        $suggested = array_flip($this->suggestedDocumentIds($session));

        return Document::query()
            ->with('session:id,session_number,title')
            ->where('document_type', DocumentType::Minutes)
            ->where('status', Registered::$name)
            ->when($linked !== [], fn ($query) => $query->whereKeyNot($linked))
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(function (Document $document) use ($suggested): array {
                $source = $document->session;

                return [
                    'id' => $document->getKey(),
                    'title' => $document->title,
                    'reference_number' => $document->reference_number,
                    'status' => $document->status->getValue(),
                    'document_type' => $document->document_type->value,
                    'current_reading' => $document->current_reading,
                    'suggested' => isset($suggested[$document->getKey()]),
                    'source_session' => $source instanceof LegislativeSession
                        ? [
                            'id' => $source->getKey(),
                            'session_number' => $source->session_number,
                            'title' => $source->title,
                        ]
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     previous_session: array{id: string, session_number: string, title: string}|null,
     *     sessions: list<array{id: string, session_number: string, title: string}>,
     *     suggested_document_ids: list<string>,
     *     documents: list<array<string, mixed>>
     * }
     */
    public function bindPayload(LegislativeSession $session): array
    {
        $previous = $this->previousSitting($session);

        return [
            'previous_session' => $previous instanceof LegislativeSession
                ? [
                    'id' => $previous->getKey(),
                    'session_number' => $previous->session_number,
                    'title' => $previous->title,
                ]
                : null,
            'sessions' => $this->priorSittings($session)
                ->map(fn (LegislativeSession $row): array => [
                    'id' => $row->getKey(),
                    'session_number' => $row->session_number,
                    'title' => $row->title,
                ])
                ->values()
                ->all(),
            'suggested_document_ids' => $this->suggestedDocumentIds($session),
            'documents' => $this->bindableDocuments($session),
        ];
    }

    public function isMinutesHeading(AgendaItem $item): bool
    {
        return $item->document_id === null && $item->category === self::HEADING_CATEGORY;
    }

    public function isMinutesPacket(AgendaItem $item): bool
    {
        return $item->document_id !== null && $item->category === self::HEADING_CATEGORY;
    }

    public function uploadAndBind(
        LegislativeSession $session,
        AgendaItem $heading,
        User $actor,
        UploadedFile $file,
        ?string $title,
        ?string $ofSessionId,
    ): AgendaItem {
        if (! $this->isMinutesHeading($heading) || $heading->session_id !== $session->getKey()) {
            throw new InvalidArgumentException('Minutes can only be attached to Reading and Consideration of the Minutes.');
        }

        if ($ofSessionId !== null && $ofSessionId === $session->getKey()) {
            throw new InvalidArgumentException('Uploaded minutes belong to a previous sitting, not this one.');
        }

        $ofSession = $ofSessionId !== null
            ? LegislativeSession::query()->find($ofSessionId)
            : $this->previousSitting($session);

        if ($ofSessionId !== null && ! $ofSession instanceof LegislativeSession) {
            throw new InvalidArgumentException('Previous session not found.');
        }

        $resolvedTitle = trim((string) $title);

        if ($resolvedTitle === '') {
            $resolvedTitle = $ofSession instanceof LegislativeSession
                ? 'Minutes of '.$ofSession->title
                : 'Minutes';
        }

        $document = $this->versions->createWithUpload(
            $actor,
            [
                'title' => $resolvedTitle,
                'document_type' => DocumentType::Minutes->value,
                'confidentiality' => Confidentiality::Internal->value,
                'origin' => DocumentOrigin::Member->value,
                'status' => Registered::$name,
                'session_id' => $ofSession?->getKey(),
            ],
            $file,
        );

        $document->forceFill(['registered_at' => now()])->save();

        $created = $this->agenda->bindDocuments($session, $heading, [$document->getKey()], $actor);
        $item = $created[0] ?? $session->agendaItems()->where('document_id', $document->getKey())->first();

        if (! $item instanceof AgendaItem) {
            throw new InvalidArgumentException('Minutes could not be attached to the agenda.');
        }

        $this->audit->record(
            event: 'sessions.minutes.uploaded',
            category: 'session',
            auditable: $item,
            actor: $actor,
            new: [
                'document_id' => $document->getKey(),
                'of_session_id' => $ofSession?->getKey(),
            ],
            message: 'Previous minutes uploaded for reading and consideration.',
        );

        return $item;
    }

    /**
     * @param  array{as_written: string, should_read: string, page_number?: int|null}  $attributes
     */
    public function recordCorrection(AgendaItem $item, User $actor, array $attributes): MinutesCorrection
    {
        $this->assertWritablePacket($item);

        $correction = MinutesCorrection::query()->create([
            'session_id' => $item->session_id,
            'agenda_item_id' => $item->getKey(),
            'document_id' => $item->document_id,
            'as_written' => trim($attributes['as_written']),
            'should_read' => trim($attributes['should_read']),
            'page_number' => $attributes['page_number'] ?? null,
            'recorded_by' => $actor->getKey(),
        ]);

        $this->audit->record(
            event: 'sessions.minutes.correction.recorded',
            category: 'session',
            auditable: $correction,
            actor: $actor,
            new: [
                'agenda_item_id' => $item->getKey(),
                'page_number' => $correction->page_number,
            ],
            message: 'Minutes correction recorded from the floor.',
        );

        $this->broadcast($item);

        return $correction;
    }

    /**
     * @param  array{as_written?: string, should_read?: string, page_number?: int|null}  $attributes
     */
    public function updateCorrection(MinutesCorrection $correction, User $actor, array $attributes): MinutesCorrection
    {
        $item = $this->itemFor($correction);
        $this->assertWritablePacket($item);

        $old = $correction->only(['as_written', 'should_read', 'page_number']);

        $correction->update([
            'as_written' => array_key_exists('as_written', $attributes)
                ? trim((string) $attributes['as_written'])
                : $correction->as_written,
            'should_read' => array_key_exists('should_read', $attributes)
                ? trim((string) $attributes['should_read'])
                : $correction->should_read,
            'page_number' => array_key_exists('page_number', $attributes)
                ? $attributes['page_number']
                : $correction->page_number,
        ]);

        $this->audit->record(
            event: 'sessions.minutes.correction.updated',
            category: 'session',
            auditable: $correction,
            actor: $actor,
            old: $old,
            new: $correction->only(['as_written', 'should_read', 'page_number']),
            message: 'Minutes correction edited.',
        );

        $this->broadcast($item);

        return $correction->fresh() ?? $correction;
    }

    public function deleteCorrection(MinutesCorrection $correction, User $actor): void
    {
        $item = $this->itemFor($correction);
        $this->assertWritablePacket($item);

        $correction->delete();

        $this->audit->record(
            event: 'sessions.minutes.correction.deleted',
            category: 'session',
            auditable: $item,
            actor: $actor,
            old: ['id' => $correction->getKey()],
            message: 'Minutes correction removed.',
        );

        $this->broadcast($item);
    }

    public function setApplied(MinutesCorrection $correction, User $actor, bool $applied): MinutesCorrection
    {
        $item = $this->itemFor($correction);
        $this->assertApplyablePacket($item);

        $correction->update([
            'applied_at' => $applied ? now() : null,
            'applied_by' => $applied ? $actor->getKey() : null,
        ]);

        $this->audit->record(
            event: $applied ? 'sessions.minutes.correction.applied' : 'sessions.minutes.correction.unapplied',
            category: 'session',
            auditable: $correction,
            actor: $actor,
            message: $applied
                ? 'Minutes correction marked applied.'
                : 'Minutes correction marked not applied.',
        );

        return $correction->fresh() ?? $correction;
    }

    /**
     * @return Collection<int, MinutesCorrection>
     */
    public function forSession(LegislativeSession $session): Collection
    {
        return MinutesCorrection::query()
            ->with('recorder')
            ->where('session_id', $session->getKey())
            ->orderBy('created_at')
            ->get();
    }

    private function itemFor(MinutesCorrection $correction): AgendaItem
    {
        $item = $correction->agendaItem ?? AgendaItem::query()->find($correction->agenda_item_id);

        if (! $item instanceof AgendaItem) {
            throw new InvalidArgumentException('Agenda item not found.');
        }

        return $item;
    }

    private function assertWritablePacket(AgendaItem $item): void
    {
        if (! $this->isMinutesPacket($item)) {
            throw new InvalidArgumentException('Corrections belong to a minutes packet on the floor.');
        }

        if ($item->status !== 'in-progress') {
            throw new InvalidArgumentException('Minutes corrections are frozen after this item leaves the floor.');
        }
    }

    private function assertApplyablePacket(AgendaItem $item): void
    {
        if (! $this->isMinutesPacket($item)) {
            throw new InvalidArgumentException('Corrections belong to a minutes packet.');
        }

        if ($item->status === 'in-progress') {
            throw new InvalidArgumentException('Applied is recorded after the item leaves the floor.');
        }
    }

    private function broadcast(AgendaItem $item): void
    {
        $session = $item->session ?? LegislativeSession::query()->find($item->session_id);

        if ($session instanceof LegislativeSession) {
            event(new MinutesCorrectionsChanged($session));
        }
    }
}
