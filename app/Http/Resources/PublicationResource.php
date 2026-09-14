<?php

namespace App\Http\Resources;

use App\Enums\Confidentiality;
use App\Models\AuditLog;
use App\Models\Publication;
use App\States\Publication\InternalDocument;
use App\States\Publication\MarkPublic;
use App\States\Publication\PublicationReview;
use App\States\Publication\Published;
use App\States\Publication\SecretariatReview;

class PublicationResource
{
    /**
     * Visual publication stages shown on the record. Mark Public is the
     * last beat of "Published" rather than a fifth card.
     *
     * @var list<array{key: string, label: string, statuses: list<string>}>
     */
    private const STAGES = [
        [
            'key' => 'drafting',
            'label' => 'Drafting',
            'statuses' => ['internal-document'],
        ],
        [
            'key' => 'secretariat-review',
            'label' => 'Secretariat Review',
            'statuses' => ['secretariat-review'],
        ],
        [
            'key' => 'publication-review',
            'label' => 'Publication Review',
            'statuses' => ['publication-review'],
        ],
        [
            'key' => 'published',
            'label' => 'Published',
            'statuses' => ['mark-public', 'published'],
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function summary(Publication $publication): array
    {
        $document = $publication->document;
        $visibility = self::visibility($publication);
        $status = $publication->status->getValue();

        return [
            'id' => $publication->getKey(),
            'public_slug' => $publication->public_slug,
            'title' => $publication->title,
            'summary' => $publication->summary,
            'status' => $status,
            'status_label' => $publication->status->label(),
            'published_at' => $publication->published_at?->toIso8601String(),
            'updated_at' => $publication->updated_at?->toIso8601String(),
            'visibility' => $visibility['key'],
            'visibility_label' => $visibility['label'],
            'workflow' => self::workflow($publication),
            'document' => $document ? [
                'id' => $document->getKey(),
                'slug' => $document->slug,
                'title' => $document->title,
                'reference_number' => $document->reference_number,
                'document_type' => $document->document_type->value,
                'document_type_label' => $document->document_type->label(),
                'confidentiality' => $document->confidentiality->value,
                'confidentiality_label' => $document->confidentiality->label(),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Publication $publication): array
    {
        return [
            ...self::summary($publication),
            'categories' => $publication->categories ?? [],
            'redaction_applied' => $publication->redaction_applied,
            'reviewed_at' => $publication->reviewed_at?->toIso8601String(),
            'reviewer' => $publication->reviewer?->display_name,
            'publisher' => $publication->publisher?->display_name,
            'view_count' => $publication->view_count,
            'transitions' => self::availableTransitions($publication),
            'activity' => self::activity($publication),
            'release_targets' => self::releaseTargets($publication),
        ];
    }

    /**
     * @return list<array{to: string, label: string}>
     */
    public static function availableTransitions(Publication $publication): array
    {
        $next = match ($publication->status::class) {
            InternalDocument::class => [SecretariatReview::class],
            SecretariatReview::class => [PublicationReview::class],
            PublicationReview::class => [MarkPublic::class],
            MarkPublic::class => [Published::class],
            default => [],
        };

        return array_map(
            static fn (string $class): array => [
                'to' => $class::$name,
                'label' => self::transitionLabel($class),
            ],
            $next,
        );
    }

    /**
     * @return list<array{key: string, label: string, state: string}>
     */
    public static function workflow(Publication $publication): array
    {
        $current = $publication->status->getValue();
        $currentIndex = 0;

        foreach (self::STAGES as $index => $stage) {
            if (in_array($current, $stage['statuses'], true)) {
                $currentIndex = $index;
                break;
            }
        }

        $published = $current === Published::$name;

        return array_map(static function (array $stage, int $index) use ($currentIndex, $published): array {
            $state = 'pending';

            if ($published || $index < $currentIndex) {
                $state = 'cleared';
            } elseif ($index === $currentIndex) {
                $state = 'current';
            }

            return [
                'key' => $stage['key'],
                'label' => $stage['label'],
                'state' => $state,
            ];
        }, self::STAGES, array_keys(self::STAGES));
    }

    /**
     * @return array{key: string, label: string}
     */
    public static function visibility(Publication $publication): array
    {
        if ($publication->status->getValue() === Published::$name) {
            return ['key' => 'public', 'label' => 'Public'];
        }

        $confidentiality = $publication->document?->confidentiality;

        if ($confidentiality === Confidentiality::Restricted || $confidentiality === Confidentiality::Confidential) {
            return ['key' => $confidentiality->value, 'label' => $confidentiality->label()];
        }

        return ['key' => 'internal', 'label' => 'Internal'];
    }

    /**
     * @return list<array{id: string, actor: string|null, kind: string, stage: string|null, occurred_at: string|null}>
     */
    public static function activity(Publication $publication): array
    {
        $logs = AuditLog::query()
            ->where('auditable_type', $publication->getMorphClass())
            ->where('auditable_id', $publication->getKey())
            ->orderByDesc('occurred_at')
            ->limit(12)
            ->get();

        $items = [];

        foreach ($logs as $log) {
            $to = is_array($log->new_values) ? ($log->new_values['status'] ?? null) : null;

            $items[] = [
                'id' => (string) $log->getKey(),
                'actor' => $log->actor_label,
                'kind' => $log->event === 'workflow.transition' ? 'moved' : 'event',
                'stage' => is_string($to) ? self::stageLabel($to) : null,
                'occurred_at' => $log->occurred_at?->toIso8601String(),
            ];
        }

        if ($items !== []) {
            return $items;
        }

        return [[
            'id' => 'created-'.$publication->getKey(),
            'actor' => null,
            'kind' => 'created',
            'stage' => null,
            'occurred_at' => $publication->created_at?->toIso8601String(),
        ]];
    }

    /**
     * @return list<array{key: string, label: string, detail: string, href: string|null, available: bool}>
     */
    public static function releaseTargets(Publication $publication): array
    {
        $live = $publication->status->getValue() === Published::$name;
        $portalHref = '/portal/documents/'.$publication->public_slug;
        $document = $publication->document;

        $gazetteHref = null;
        $gazetteDetail = 'Assigned after enactment numbering.';

        if ($document?->ordinance?->ordinance_number) {
            $gazetteHref = '/portal/ordinances/'.$document->ordinance->ordinance_number;
            $gazetteDetail = $document->ordinance->ordinance_number;
        } elseif ($document?->resolution?->resolution_number) {
            $gazetteHref = '/portal/resolutions/'.$document->resolution->resolution_number;
            $gazetteDetail = $document->resolution->resolution_number;
        }

        return [
            [
                'key' => 'portal',
                'label' => 'Public portal',
                'detail' => $portalHref,
                'href' => $live ? $portalHref : null,
                'available' => $live,
            ],
            [
                'key' => 'feed',
                'label' => 'Open-data feed',
                'detail' => 'Public record page',
                'href' => $live ? $portalHref : null,
                'available' => $live,
            ],
            [
                'key' => 'gazette',
                'label' => 'Official gazette',
                'detail' => $gazetteDetail,
                'href' => $live ? $gazetteHref : null,
                'available' => $live && $gazetteHref !== null,
            ],
        ];
    }

    private static function transitionLabel(string $class): string
    {
        return match ($class) {
            SecretariatReview::class => 'Secretariat Review',
            PublicationReview::class => 'Publication Review',
            MarkPublic::class => 'Mark Public',
            Published::class => 'Published',
            default => $class::$name,
        };
    }

    private static function stageLabel(string $status): string
    {
        return match ($status) {
            InternalDocument::$name => 'drafting',
            SecretariatReview::$name => 'secretariat review',
            PublicationReview::$name => 'publication review',
            MarkPublic::$name => 'mark public',
            Published::$name => 'published',
            default => str_replace('-', ' ', $status),
        };
    }
}
