<?php

namespace App\Http\Resources;

use App\Models\Committee;
use App\Models\CommitteeReferral;
use App\Models\CommitteeReport;
use App\Models\Document;
use App\Models\DocumentGrant;
use App\Models\DocumentVersion;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\User;
use App\Services\Documents\DocumentAccessService;
use App\States\Document\Archive;
use App\States\Document\CommitteeReport as CommitteeReportState;
use App\States\Document\CommitteeReview;
use App\States\Document\DocumentWorkflowStatus;
use App\States\Document\ReadingDeliberation;
use App\States\Document\ReturnedForRevision;
use App\States\Document\Submitted;
use Illuminate\Support\Str;

class DocumentResource
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Document $document): array
    {
        $currentVersion = $document->relationLoaded('currentVersion')
            ? $document->currentVersion
            : null;
        $committee = $document->relationLoaded('committee') ? $document->committee : null;

        return [
            'id' => $document->getKey(),
            'slug' => $document->slug,
            'title' => $document->title,
            'reference_number' => $document->reference_number,
            'document_type' => $document->document_type->value,
            'document_type_label' => $document->document_type->label(),
            'status' => $document->status->getValue(),
            'status_label' => $document->status->label(),
            'confidentiality' => $document->confidentiality->value,
            'version_count' => $document->version_count,
            'processing_status' => $currentVersion?->processing_status?->value,
            'processing_status_label' => $currentVersion?->processing_status?->label(),
            'submitted_at' => $document->submitted_at?->toIso8601String(),
            'author' => $document->authorName(),
            'committee' => $committee?->name,
            'archived_at' => $document->archived_at?->toIso8601String(),
            'deleted_at' => $document->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Document $document, ?User $user = null): array
    {
        $document->loadMissing('versions.uploader');

        return [
            ...self::summary($document),
            'abstract' => $document->abstract,
            'external_author' => $document->external_author,
            'enacting_clause' => $document->enacting_clause,
            'explanatory_note' => $document->explanatory_note,
            'current_reading' => $document->current_reading,
            'tracking_number' => $document->tracking_number,
            'committee_id' => $document->committee_id,
            'session_id' => $document->session_id,
            'tags' => $document->tags ?? [],
            'registered_at' => $document->registered_at?->toIso8601String(),
            'published_at' => $document->published_at?->toIso8601String(),
            'sealed_at' => $document->sealed_at?->toIso8601String(),
            'is_public' => $document->is_public,
            'is_measure' => $document->document_type->isMeasure(),
            'return_reason' => $document->return_reason,
            'returned_at' => $document->returned_at?->toIso8601String(),
            'on_session' => $document->isPlacedInSession(),
            'versions' => $document->versions->map(fn (DocumentVersion $v): array => self::version($v))->values()->all(),
            'grants' => $document->grants->map(fn (DocumentGrant $g): array => self::grant($g))->values()->all(),
            'transitions' => $user === null ? [] : self::availableTransitions($document, $user),
            'returned_referral' => self::returnedReferral($document),
            'open_referral' => self::openReferral($document),
            'reports' => $user === null ? [] : self::subjectReports($document, $user),
        ];
    }

    /**
     * Next IRP steps the actor is permitted to take from the current status.
     *
     * @return list<array{to: string, label: string}>
     */
    public static function availableTransitions(Document $document, User $user): array
    {
        $status = $document->status;

        if (! $status instanceof DocumentWorkflowStatus) {
            return [];
        }

        $access = app(DocumentAccessService::class);
        $transitions = [];
        $placedInSession = $document->isPlacedInSession();

        foreach ($status->successors() as $class) {
            // Committee report status advances when a report is submitted or
            // adopted — not via a bare status button on the measure.
            if ($class === CommitteeReportState::class || ($class === Archive::class && $status instanceof CommitteeReportState)) {
                continue;
            }

            // A finished committee hearing already did this step.
            if ($class === CommitteeReview::class && $document->wasHeardInCommittee()) {
                continue;
            }

            // Opening a reading belongs on the floor. Before the measure is
            // attached it waits in the agenda pool; after it is attached the
            // presiding officer opens the reading from the sitting.
            if ($class === ReadingDeliberation::class) {
                continue;
            }

            if ($placedInSession && DocumentWorkflowStatus::isSessionOwnedHop($class, $status::class)) {
                continue;
            }

            if ($class === Submitted::class && $status instanceof ReturnedForRevision) {
                $isAuthor = $document->author_id !== null
                    && (string) $document->author_id === (string) $user->getKey();

                if (! $isAuthor && ! $access->hasElevatedUploadAccess($user)) {
                    continue;
                }
            } else {
                $permissions = DocumentWorkflowStatus::permissionsFor($class, $status::class, $document);

                if ($permissions !== [] && ! self::userHasAnyPermission($user, $permissions)) {
                    continue;
                }
            }

            $transitions[] = [
                'to' => $class::$name,
                'label' => DocumentWorkflowStatus::actionLabelFor($class, $status::class, $document),
            ];
        }

        return $transitions;
    }

    /**
     * @return array{
     *     id: string,
     *     committee_id: string,
     *     committee: string|null,
     *     committee_ids: list<string>,
     *     committees: list<string>,
     *     status: string,
     *     meeting_on: string|null,
     *     remarks: string|null
     * }|null
     */
    public static function openReferral(Document $document): ?array
    {
        if (! $document->relationLoaded('referrals')) {
            return null;
        }

        $open = $document->referrals
            ->filter(fn (CommitteeReferral $item): bool => in_array($item->status, ['pending', 'in-review'], true))
            ->sortBy(fn (CommitteeReferral $item): int => $item->is_primary ? 0 : 1)
            ->values();

        $referral = $open->first();

        if (! $referral instanceof CommitteeReferral) {
            return null;
        }

        return [
            'id' => $referral->getKey(),
            'committee_id' => $referral->committee_id,
            'committee' => $referral->committee?->name,
            'committee_ids' => $open
                ->map(fn (CommitteeReferral $item): string => (string) $item->committee_id)
                ->unique()
                ->values()
                ->all(),
            'committees' => $open
                ->map(fn (CommitteeReferral $item): ?string => $item->committee?->name)
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'status' => $referral->status,
            'meeting_on' => $referral->meeting_on?->toDateString(),
            'remarks' => $referral->instructions,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function subjectReports(Document $document, User $user): array
    {
        if (! $document->relationLoaded('subjectReports')) {
            return [];
        }

        return $document->subjectReports
            ->map(fn (CommitteeReport $report): array => self::report($report, $user))
            ->values()
            ->all();
    }

    /**
     * @return array{committee: string|null, outcome_notes: string|null, completed_at: string|null}|null
     */
    public static function returnedReferral(Document $document): ?array
    {
        if (! $document->relationLoaded('referrals')) {
            return null;
        }

        $referral = $document->referrals
            ->where('status', 'returned')
            ->sortByDesc(fn (CommitteeReferral $item): int => $item->completed_at?->getTimestamp() ?? 0)
            ->first();

        if (! $referral instanceof CommitteeReferral) {
            return null;
        }

        return [
            'committee' => $referral->committee?->name,
            'outcome_notes' => $referral->outcome_notes,
            'completed_at' => $referral->completed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function version(DocumentVersion $version): array
    {
        $version->loadMissing('uploader');

        return [
            'id' => $version->getKey(),
            'version_number' => $version->version_number,
            'is_current' => $version->is_current,
            'original_filename' => $version->original_filename,
            'mime_type' => $version->mime_type,
            'file_size' => $version->file_size,
            'checksum_sha256' => $version->checksum_sha256,
            'change_summary' => $version->change_summary,
            'uploaded_by' => $version->uploader?->display_name,
            'created_at' => $version->created_at?->toIso8601String(),
            'scan_status' => $version->scan_status,
            'processing_status' => $version->processing_status?->value,
            'processing_status_label' => $version->processing_status?->label(),
            'processing_error' => $version->processing_error,
            'processed_at' => $version->processed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function grant(DocumentGrant $grant): array
    {
        return [
            'id' => $grant->getKey(),
            'ability' => $grant->ability,
            'user' => $grant->user?->display_name,
            'role' => $grant->role?->name,
            'committee' => $grant->committee?->name,
            'granted_at' => $grant->granted_at?->toIso8601String(),
            'expires_at' => $grant->expires_at?->toIso8601String(),
            'reason' => $grant->reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function committee(Committee $committee): array
    {
        return [
            'id' => $committee->getKey(),
            'slug' => $committee->slug,
            'name' => $committee->name,
            'code' => $committee->code,
            'type' => $committee->type,
            'mandate' => $committee->mandate,
            'description' => $committee->description,
            'is_active' => $committee->is_active,
            'established_on' => $committee->established_on?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function referral(CommitteeReferral $referral): array
    {
        return [
            'id' => $referral->getKey(),
            'status' => $referral->status,
            'instructions' => $referral->instructions,
            'outcome_notes' => $referral->outcome_notes,
            'is_primary' => $referral->is_primary,
            'referred_at' => $referral->referred_at?->toIso8601String(),
            'due_at' => $referral->due_at?->toIso8601String(),
            'meeting_on' => $referral->meeting_on?->toDateString(),
            'completed_at' => $referral->completed_at?->toIso8601String(),
            'document' => $referral->document ? self::summary($referral->document) : null,
            'referrer' => $referral->referrer?->display_name,
            'transitions' => array_map(
                static fn (string $status): array => ['to' => $status],
                $referral->successors(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function report(CommitteeReport $report, ?User $viewer = null): array
    {
        $can = [
            'submit_for_review' => false,
            'return_to_draft' => false,
            'submit' => false,
            'adopt' => false,
        ];

        if ($viewer !== null) {
            $can = [
                'submit_for_review' => $viewer->can('submitForReview', $report),
                'return_to_draft' => $viewer->can('returnToDraft', $report),
                'submit' => $viewer->can('submit', $report),
                'adopt' => $viewer->can('adopt', $report),
            ];
        }

        return [
            ...self::floorReport($report),
            'can' => $can,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function floorReport(CommitteeReport $report): array
    {
        $report->loadMissing('submitter');

        return [
            'id' => $report->getKey(),
            'report_number' => $report->report_number,
            'recommendation' => $report->recommendation,
            'status' => $report->status,
            'findings' => $report->findings,
            'recommendation_notes' => $report->recommendation_notes,
            'submitted_at' => $report->submitted_at?->toIso8601String(),
            'submitter' => $report->submitter?->display_name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function ordinance(Ordinance $ordinance): array
    {
        return [
            'id' => $ordinance->getKey(),
            'document_id' => $ordinance->document_id,
            'ordinance_number' => $ordinance->ordinance_number,
            'series_year' => $ordinance->series_year,
            'title' => $ordinance->title,
            'purpose' => $ordinance->purpose,
            'status' => $ordinance->status,
            'status_label' => self::statusLabel($ordinance->status),
            'enacted_on' => $ordinance->enacted_on?->toDateString(),
            'effectivity_date' => $ordinance->effectivity_date?->toDateString(),
            'imported_at' => $ordinance->imported_at?->toIso8601String(),
            'updated_at' => $ordinance->updated_at?->toIso8601String(),
            'document' => $ordinance->document ? self::summary($ordinance->document) : null,
            'signed_copy' => self::signedCopy($ordinance),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolution(Resolution $resolution): array
    {
        return [
            'id' => $resolution->getKey(),
            'document_id' => $resolution->document_id,
            'resolution_number' => $resolution->resolution_number,
            'series_year' => $resolution->series_year,
            'title' => $resolution->title,
            'purpose' => $resolution->purpose,
            'category' => $resolution->category,
            'status' => $resolution->status,
            'status_label' => self::statusLabel($resolution->status),
            'adopted_on' => $resolution->adopted_on?->toDateString(),
            'effectivity_date' => $resolution->effectivity_date?->toDateString(),
            'transmitted_on' => $resolution->transmitted_on?->toDateString(),
            'transmitted_to' => $resolution->transmitted_to,
            'imported_at' => $resolution->imported_at?->toIso8601String(),
            'updated_at' => $resolution->updated_at?->toIso8601String(),
            'document' => $resolution->document ? self::summary($resolution->document) : null,
            'signed_copy' => self::signedCopy($resolution),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function signedCopy(Ordinance|Resolution $record): ?array
    {
        if (! $record->signedCopyIsAttached()) {
            return null;
        }

        $uploader = $record->relationLoaded('signedCopyUploader')
            ? $record->signedCopyUploader
            : null;

        return [
            'filename' => $record->signed_copy_filename,
            'size' => $record->signed_copy_size,
            'mime' => $record->signed_copy_mime,
            'uploaded_at' => $record->signed_copy_uploaded_at?->toIso8601String(),
            'uploaded_by' => $uploader?->display_name,
            'available' => $record->hasSignedCopy(),
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    private static function userHasAnyPermission(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ordinance and resolution status is a plain column rather than a state
     * machine, so it carries no label of its own. The register still may not
     * print a raw slug at a reader, and humanising it in the component would
     * put copy in the wrong layer.
     */
    private static function statusLabel(?string $status): ?string
    {
        return $status === null || $status === '' ? null : Str::headline($status);
    }
}
