import { AiContent } from '@/components/ai/AiContent';
import { DraftReportDialog } from '@/components/documents/DraftReportDialog';
import { GrantAccessDialog } from '@/components/documents/GrantAccessDialog';
import { ReferToCommitteeDialog } from '@/components/documents/ReferToCommitteeDialog';
import { ReuploadDocumentDialog } from '@/components/documents/ReuploadDocumentDialog';
import { ReturnDocumentDialog } from '@/components/documents/ReturnDocumentDialog';
import { ViewReportDialog } from '@/components/documents/ViewReportDialog';
import { LegislativeHistorySheet, type HistoryEvent } from '@/components/legislation/LegislativeHistoryPreview';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { FileDrop } from '@/components/ui/file-drop';
import { notifyError, notifySuccess } from '@/components/ui/flash';
import { Input } from '@/components/ui/input';
import { Notice } from '@/components/ui/notice';
import { Panel, PanelBody, PanelHead, PanelTitle } from '@/components/ui/panel';
import { StatusChip, toneForState } from '@/components/ui/status';
import AppLayout from '@/layouts/AppLayout';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Link, router, useForm } from '@inertiajs/react';
import {
    ChevronRight,
    Cloud,
    Download,
    FileSearch,
    FileText,
    GitCompare,
    History,
    Info,
    Link2,
    Pencil,
    Scale,
    Search,
    Sparkles,
} from 'lucide-react';
import { FormEvent, type ReactNode, useState } from 'react';

type Version = {
    id: string;
    version_number: number;
    is_current: boolean;
    original_filename: string;
    mime_type: string;
    file_size: number;
    change_summary: string | null;
    uploaded_by: string | null;
    created_at: string | null;
    processing_status: string | null;
    processing_status_label: string | null;
    processing_error: string | null;
    processed_at: string | null;
};

type Grant = {
    id: string;
    ability: string;
    user: string | null;
    role: string | null;
    committee: string | null;
    reason: string | null;
};

type Transition = {
    to: string;
    label: string;
};

type OpenReferral = {
    id: string;
    committee_id: string;
    committee: string | null;
    committee_ids?: string[];
    committees?: string[];
    status: string;
    meeting_on?: string | null;
    remarks?: string | null;
};

type DocumentReport = {
    id: string;
    report_number: string | null;
    recommendation: string;
    status: string;
    findings?: string | null;
    recommendation_notes?: string | null;
    submitted_at: string | null;
    submitter?: string | null;
    can?: {
        submit_for_review?: boolean;
        return_to_draft?: boolean;
        submit?: boolean;
        adopt?: boolean;
    };
};

type DocumentDetail = {
    id: string;
    slug: string;
    title: string;
    reference_number: string | null;
    tracking_number: string | null;
    document_type_label: string;
    status: string;
    status_label: string;
    confidentiality: string;
    abstract: string | null;
    tags: string[];
    author: string | null;
    committee: string | null;
    committee_id: string | null;
    submitted_at: string | null;
    registered_at: string | null;
    published_at: string | null;
    sealed_at?: string | null;
    is_measure?: boolean;
    enacting_clause?: string | null;
    explanatory_note?: string | null;
    current_reading?: number | null;
    return_reason?: string | null;
    returned_at?: string | null;
    on_session?: boolean;
    versions: Version[];
    grants: Grant[];
    transitions: Transition[];
    returned_referral?: {
        committee: string | null;
        outcome_notes: string | null;
        completed_at: string | null;
    } | null;
    open_referral?: OpenReferral | null;
    reports?: DocumentReport[];
};

type AiSummary = {
    executive_summary: string;
    purpose: string;
    key_provisions: string[];
    important_dates: string[];
    financial_info: string | null;
    affected_offices: string[];
    related_docs_hints: string[];
    potential_issues: string[];
    model?: string;
    generated_at?: string | null;
};

type RelatedSuggestion = {
    document_id: string;
    slug: string;
    title: string;
    reference_number: string | null;
    document_type_label: string;
    similarity: number;
    relevance_band: 'high' | 'medium' | 'low';
    excerpt: string | null;
    is_ai_suggestion: boolean;
};

type CommitteeOption = {
    id: string;
    name: string;
};

type GrantableUser = { id: string; display_name: string };
type GrantableRole = { id: string; name: string };

type Props = {
    document: DocumentDetail;
    committees?: CommitteeOption[];
    grantable_users?: GrantableUser[];
    grantable_roles?: GrantableRole[];
    publication?: { public_slug: string; title: string; status: string } | null;
    history?: HistoryEvent[];
    nextReportNumber?: string | null;
    can: {
        update: boolean;
        delete: boolean;
        download: boolean;
        uploadVersion: boolean;
        grantAccess: boolean;
        archive: boolean;
        transition: boolean;
        summarize: boolean;
        related: boolean;
        compare: boolean;
        consistency: boolean;
        createPublication?: boolean;
        createReport?: boolean;
        submitReport?: boolean;
        editReferral?: boolean;
        seal?: boolean;
    };
    aiSummary: AiSummary | null;
};

const navyButtonClass =
    'border-[var(--color-floor-plate)] bg-[var(--color-floor-plate)] text-[var(--color-floor-ink)] shadow-none hover:border-floor-plate-hover hover:bg-floor-plate-hover';

const cardClass = 'overflow-hidden rounded-[8px] shadow-[0_1px_2px_rgb(15_27_61/0.06)]';

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function firstFormError(errors: Record<string, string>): string | null {
    const first = Object.values(errors)[0];

    return typeof first === 'string' && first.trim() !== '' ? first : null;
}

function toastFormErrors(errors: Record<string, string>): void {
    const message = firstFormError(errors);

    if (message) {
        notifyError(message);
    }
}

async function messageFromFailedResponse(response: Response, fallback: string): Promise<string> {
    try {
        const payload: unknown = await response.json();

        if (payload === null || typeof payload !== 'object') {
            return fallback;
        }

        if ('message' in payload && typeof payload.message === 'string' && payload.message.trim() !== '') {
            return payload.message.trim();
        }

        if ('errors' in payload && payload.errors !== null && typeof payload.errors === 'object') {
            for (const value of Object.values(payload.errors as Record<string, unknown>)) {
                if (typeof value === 'string' && value.trim() !== '') {
                    return value.trim();
                }

                if (Array.isArray(value) && typeof value[0] === 'string' && value[0].trim() !== '') {
                    return value[0].trim();
                }
            }
        }
    } catch {
        // Non-JSON error bodies fall through to the fallback copy.
    }

    return fallback;
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        const kb = bytes / 1024;

        return `${kb < 10 ? kb.toFixed(1) : Math.round(kb)} KB`;
    }

    const mb = bytes / (1024 * 1024);

    return `${mb < 10 ? mb.toFixed(1) : Math.round(mb)} MB`;
}

function FilingRow({
    label,
    children,
    accent = false,
}: {
    label: string;
    children: ReactNode;
    accent?: boolean;
}) {
    return (
        <div className="flex items-start justify-between gap-4 border-b border-line py-2.5 last:border-b-0">
            <dt className="shrink-0 text-xs text-ink-subtle">{label}</dt>
            <dd
                className={cn(
                    'min-w-0 text-right text-xs font-medium text-ink',
                    accent && 'font-semibold tracking-[0.04em] text-accent uppercase',
                )}
            >
                {children}
            </dd>
        </div>
    );
}

function ToolButton({
    href,
    icon: Icon,
    label,
}: {
    href: string;
    icon: typeof GitCompare;
    label: string;
}) {
    return (
        <Button variant="secondary" className="h-auto w-full justify-start gap-3 px-3 py-2.5" asChild>
            <Link href={href}>
                <Icon aria-hidden="true" strokeWidth={1.75} className="size-4 text-ink-muted" />
                <span className="text-sm font-medium text-ink">{label}</span>
            </Link>
        </Button>
    );
}

function SummaryBody({ aiSummary, t }: { aiSummary: AiSummary | null; t: (key: string) => string }) {
    if (!aiSummary) {
        return (
            <EmptyState
                bare
                icon={Sparkles}
                title={t('documents.ai_summary_empty_title')}
                description={t('documents.ai_summary_placeholder')}
                className="py-10"
            />
        );
    }

    return (
        <div className="space-y-4">
            <div>
                <h4 className="label-eyebrow">{t('documents.ai_summary_executive')}</h4>
                <p className="mt-1 leading-relaxed">{aiSummary.executive_summary}</p>
            </div>
            {aiSummary.purpose ? (
                <div>
                    <h4 className="label-eyebrow">{t('documents.ai_summary_purpose')}</h4>
                    <p className="mt-1 leading-relaxed">{aiSummary.purpose}</p>
                </div>
            ) : null}
            {aiSummary.key_provisions.length > 0 ? (
                <div>
                    <h4 className="label-eyebrow">{t('documents.ai_summary_provisions')}</h4>
                    <ul className="mt-1 list-disc space-y-1 pl-5 leading-relaxed">
                        {aiSummary.key_provisions.map((item) => (
                            <li key={item}>{item}</li>
                        ))}
                    </ul>
                </div>
            ) : null}
            {aiSummary.financial_info ? (
                <div>
                    <h4 className="label-eyebrow">{t('documents.ai_summary_financial')}</h4>
                    <p className="mt-1 leading-relaxed">{aiSummary.financial_info}</p>
                </div>
            ) : null}
        </div>
    );
}

export default function DocumentsShow({
    document,
    committees = [],
    grantable_users = [],
    grantable_roles = [],
    publication = null,
    history = [],
    nextReportNumber = null,
    can,
    aiSummary: initialSummary,
}: Props) {
    const { t } = useTranslations();
    const { formatDate, formatDateTime } = useFormatters();
    const [referOpen, setReferOpen] = useState(false);
    const [grantOpen, setGrantOpen] = useState(false);
    const [returnOpen, setReturnOpen] = useState(false);
    const [reuploadOpen, setReuploadOpen] = useState(false);
    const [reportOpen, setReportOpen] = useState(false);
    const [viewingReport, setViewingReport] = useState<DocumentReport | null>(null);
    const [aiSummary, setAiSummary] = useState<AiSummary | null>(initialSummary);
    const [summaryLoading, setSummaryLoading] = useState(false);
    const [summaryError, setSummaryError] = useState<string | null>(null);
    const [relatedDocs, setRelatedDocs] = useState<RelatedSuggestion[]>([]);
    const [relatedLoading, setRelatedLoading] = useState(false);
    const [relatedError, setRelatedError] = useState<string | null>(null);
    const [relatedLoaded, setRelatedLoaded] = useState(false);

    const versionForm = useForm({
        change_summary: '',
        file: null as File | null,
    });

    function uploadVersion(event: FormEvent) {
        event.preventDefault();
        versionForm.post(`/documents/${document.slug}/versions`, {
            forceFormData: true,
            preserveScroll: true,
            onError: toastFormErrors,
        });
    }

    function advanceWorkflow(to: string) {
        if (to === 'committee-referral') {
            setReferOpen(true);

            return;
        }

        if (to === 'returned-for-revision') {
            setReturnOpen(true);

            return;
        }

        if (to === 'rejected' || to === 'archive') {
            const key =
                to === 'archive' && document.status === 'committee-review'
                    ? 'documents.shelve_confirm'
                    : to === 'rejected'
                      ? 'documents.reject_confirm'
                      : 'documents.archive_confirm';

            if (!window.confirm(t(key))) {
                return;
            }
        }

        router.post(`/documents/${document.slug}/transition`, { to }, {
            preserveScroll: true,
            onError: toastFormErrors,
        });
    }

    function workflowButtonVariant(item: Transition): 'primary' | 'secondary' | 'danger' {
        if (item.to === 'rejected' || item.to === 'returned-for-revision') {
            return 'danger';
        }

        if (document.transitions.length === 1 || item.to === 'approved' || item.to === 'submitted') {
            return 'primary';
        }

        return 'secondary';
    }

    async function loadRelatedDocuments() {
        setRelatedLoading(true);
        setRelatedError(null);

        try {
            const response = await fetch(`/documents/${document.slug}/related`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(await messageFromFailedResponse(response, t('documents.related_error')));
            }

            const payload = await response.json();
            setRelatedDocs(payload.suggestions ?? []);
            setRelatedLoaded(true);
        } catch (error) {
            const message = error instanceof Error ? error.message : t('documents.related_error');
            setRelatedError(message);
            notifyError(message);
        } finally {
            setRelatedLoading(false);
        }
    }

    async function generateSummary(refresh = false) {
        setSummaryLoading(true);
        setSummaryError(null);

        try {
            const response = await fetch(`/documents/${document.slug}/summary${refresh ? '?refresh=1' : ''}`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });

            if (!response.ok) {
                throw new Error(await messageFromFailedResponse(response, t('documents.ai_summary_error')));
            }

            const payload = await response.json();
            setAiSummary(payload.summary);
        } catch (error) {
            const message = error instanceof Error ? error.message : t('documents.ai_summary_error');
            setSummaryError(message);
            notifyError(message);
        } finally {
            setSummaryLoading(false);
        }
    }

    async function copyLink() {
        try {
            await navigator.clipboard.writeText(window.location.href);
            notifySuccess(t('documents.link_copied'));
        } catch {
            notifyError(t('documents.link_copy_failed'));
        }
    }

    const currentVersion = document.versions.find((version) => version.is_current) ?? document.versions[0];
    const currentFailed = currentVersion?.processing_status === 'failed';
    const hasWorkflow = can.transition && document.transitions.length > 0;
    const openReferral = document.open_referral ?? null;
    const reports = document.reports ?? [];
    const canDraftReport = Boolean(can.createReport && openReferral);
    const canFileReport = Boolean(can.submitReport && openReferral);
    const canEditReferral = Boolean(can.editReferral && openReferral);
    const canArchiveNow =
        Boolean(can.archive) &&
        (document.transitions.some((item) => item.to === 'archive') ||
            document.status === 'transmittal' ||
            document.status === 'rejected');
    const recordId = document.reference_number ?? document.tracking_number;
    const confidentialityLabel = t(`documents.confidentiality_${document.confidentiality}`);
    const isSecretariatHold = document.status === 'submitted' || document.status === 'secretariat-review';
    const waitingForAgenda =
        document.status === 'agenda-inclusion' && document.transitions.length === 0 && !document.on_session;
    const showActionBanner = hasWorkflow || canDraftReport || canEditReferral || isSecretariatHold || waitingForAgenda;

    function submitReportForReview(reportId: string) {
        router.post(`/reports/${reportId}/submit-for-review`, {}, { preserveScroll: true, onError: toastFormErrors });
    }

    function returnReportToDraft(reportId: string) {
        if (!window.confirm(t('committees.return_report_to_draft_confirm'))) {
            return;
        }

        router.post(`/reports/${reportId}/return-to-draft`, {}, { preserveScroll: true, onError: toastFormErrors });
    }

    function submitReport(reportId: string) {
        if (!window.confirm(t('committees.submit_report_confirm'))) {
            return;
        }

        router.post(`/reports/${reportId}/submit`, {}, { preserveScroll: true, onError: toastFormErrors });
    }

    function slugKey(value: string): string {
        return value.replaceAll('-', '_');
    }

    function sealDocument() {
        if (!window.confirm(t('documents.seal_confirm'))) {
            return;
        }

        router.post(`/documents/${document.slug}/seal`, {}, { preserveScroll: true, onError: toastFormErrors });
    }

    function archiveDocument() {
        if (!window.confirm(t('documents.archive_confirm'))) {
            return;
        }

        router.post(`/documents/${document.slug}/archive`, {}, { preserveScroll: true, onError: toastFormErrors });
    }

    function deleteDocument() {
        if (!window.confirm(t('documents.delete_confirm'))) {
            return;
        }

        router.delete(`/documents/${document.slug}`, { onError: toastFormErrors });
    }

    function startPublication() {
        router.post(`/documents/${document.slug}/publication`, {}, { preserveScroll: true, onError: toastFormErrors });
    }

    const tabClass = (active: boolean) =>
        cn(
            'inline-flex h-7 items-center rounded-[6px] px-3 text-sm transition-all',
            active
                ? 'bg-surface font-medium text-ink shadow-[0_1px_2px_rgb(15_27_61/0.08),0_0_0_1px_rgb(15_27_61/0.04)]'
                : 'text-ink-muted hover:text-ink',
        );

    return (
        <AppLayout title={document.title}>
            <div className="mx-auto flex max-w-6xl flex-col gap-5">
                <header className="flex flex-col gap-4">
                    <p className="text-eyebrow text-ink-faint">
                        <Link href="/documents" className="hover:text-ink">
                            {t('nav.documents')}
                        </Link>
                        <span aria-hidden="true"> / </span>
                        <span>{t('documents.breadcrumb_by_number')}</span>
                    </p>

                    <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
                        <div className="min-w-0 max-w-3xl">
                            <div className="flex flex-wrap items-center gap-2.5">
                                <h1 className="text-2xl font-bold tracking-tight text-floor-plate dark:text-accent">
                                    {recordId ?? document.title}
                                </h1>
                                <span className="inline-flex items-center rounded-xs border border-line bg-canvas-sunk px-2 py-0.5 text-2xs font-semibold tracking-[0.06em] text-ink-muted uppercase">
                                    {confidentialityLabel}
                                </span>
                                <span className="inline-flex items-center rounded-xs bg-accent px-2 py-0.5 text-2xs font-semibold tracking-[0.06em] text-accent-on uppercase">
                                    {document.status_label}
                                </span>
                                {document.sealed_at ? (
                                    <StatusChip tone="final">{t('documents.sealed_badge')}</StatusChip>
                                ) : null}
                            </div>
                            {recordId ? (
                                <p className="mt-2 text-lg font-medium text-ink">{document.title}</p>
                            ) : null}
                            <p className="mt-1.5 text-sm text-ink-muted">
                                {document.document_type_label}
                                {document.author
                                    ? ` · ${t('documents.author')}: ${document.author}`
                                    : null}
                                {document.committee
                                    ? ` · ${t('documents.committee')}: ${document.committee}`
                                    : null}
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="secondary" asChild>
                                <Link href="/documents">{t('documents.back_to_registry')}</Link>
                            </Button>
                            {canDraftReport ? (
                                <Button
                                    type="button"
                                    className={navyButtonClass}
                                    onClick={() => setReportOpen(true)}
                                >
                                    <FileText aria-hidden="true" strokeWidth={1.75} />
                                    {t(canFileReport ? 'committees.submit_report' : 'documents.draft_report')}
                                </Button>
                            ) : null}
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-b border-line pb-4">
                        <nav
                            aria-label={t('documents.record_tabs')}
                            className="inline-flex items-center gap-0.5 rounded-[8px] border border-line bg-surface-alt p-0.5"
                        >
                            <span className={tabClass(true)}>{t('documents.view_source')}</span>
                            {publication ? (
                                <Link href={`/publications/${publication.public_slug}`} className={tabClass(false)}>
                                    {t('documents.view_publication')}
                                </Link>
                            ) : (
                                <span className={cn(tabClass(false), 'cursor-not-allowed opacity-45')}>
                                    {t('documents.view_publication')}
                                </span>
                            )}
                        </nav>

                        <div className="inline-flex flex-wrap items-center gap-0.5 rounded-[8px] border border-line bg-surface p-0.5 shadow-[0_1px_2px_rgb(15_27_61/0.05)]">
                            {can.download && currentVersion ? (
                                <Button variant="ghost" size="sm" className="h-7 rounded-[6px] font-medium" asChild>
                                    <a href={`/documents/${document.slug}/versions/${currentVersion.id}/download`}>
                                        <Download aria-hidden="true" strokeWidth={1.75} />
                                        {t('documents.download')}
                                    </a>
                                </Button>
                            ) : null}
                            {can.update ? (
                                <Button variant="ghost" size="sm" className="h-7 rounded-[6px] font-medium" asChild>
                                    <Link href={`/documents/${document.slug}/edit`}>
                                        <Pencil aria-hidden="true" strokeWidth={1.75} />
                                        {t('documents.edit')}
                                    </Link>
                                </Button>
                            ) : null}
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-7 rounded-[6px] font-medium"
                                onClick={() => void copyLink()}
                            >
                                <Link2 aria-hidden="true" strokeWidth={1.75} />
                                {t('documents.copy_link')}
                            </Button>
                        </div>
                    </div>
                </header>

                <ReferToCommitteeDialog
                    documentSlug={document.slug}
                    committeeId={document.committee_id}
                    committeeIds={openReferral?.committee_ids}
                    meetingOn={openReferral?.meeting_on}
                    remarks={openReferral?.remarks}
                    committees={committees}
                    open={referOpen}
                    onOpenChange={setReferOpen}
                    mode={canEditReferral ? 'edit' : 'create'}
                />

                <ReturnDocumentDialog
                    documentSlug={document.slug}
                    open={returnOpen}
                    onOpenChange={setReturnOpen}
                />

                <ReuploadDocumentDialog
                    documentSlug={document.slug}
                    open={reuploadOpen}
                    onOpenChange={setReuploadOpen}
                />

                {openReferral ? (
                    <DraftReportDialog
                        referral={openReferral}
                        nextReportNumber={nextReportNumber ?? ''}
                        open={reportOpen}
                        onOpenChange={setReportOpen}
                        canFileNow={canFileReport}
                    />
                ) : null}

                <ViewReportDialog
                    report={viewingReport}
                    open={viewingReport !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setViewingReport(null);
                        }
                    }}
                />

                {can.grantAccess ? (
                    <GrantAccessDialog
                        documentSlug={document.slug}
                        users={grantable_users}
                        roles={grantable_roles}
                        committees={committees}
                        open={grantOpen}
                        onOpenChange={setGrantOpen}
                    />
                ) : null}

                {showActionBanner ? (
                    <div className="flex flex-wrap items-center justify-between gap-4 rounded-[10px] border border-line bg-surface px-4 py-3.5 shadow-[0_1px_2px_rgb(15_27_61/0.06)]">
                        <div className="flex min-w-0 flex-1 items-start gap-3.5">
                            <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-[8px] bg-info-soft text-info ring-1 ring-info-line ring-inset">
                                <Info aria-hidden="true" className="size-[18px]" strokeWidth={1.75} />
                            </span>
                            <div className="min-w-0">
                                <p className="text-2xs font-semibold tracking-[0.08em] text-info uppercase">
                                    {t('documents.next_step')}
                                </p>
                                <p className="mt-0.5 max-w-2xl text-sm leading-relaxed text-ink-muted">
                                    {isSecretariatHold
                                        ? t('documents.secretariat_hold_notice', { status: document.status_label })
                                        : waitingForAgenda
                                          ? t('documents.ready_for_agenda_hint')
                                          : canEditReferral && !hasWorkflow
                                            ? t('documents.refer_edit_hint')
                                            : t('documents.advance_workflow_hint')}
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {waitingForAgenda ? (
                                <Button variant="primary" size="sm" className={navyButtonClass} asChild>
                                    <Link href="/sessions">{t('documents.ready_for_agenda_action')}</Link>
                                </Button>
                            ) : null}
                            {document.transitions.map((item) => {
                                const variant = workflowButtonVariant(item);

                                return (
                                    <Button
                                        key={item.to}
                                        variant={variant === 'primary' ? 'primary' : variant}
                                        size="sm"
                                        className={variant === 'primary' ? navyButtonClass : undefined}
                                        onClick={() => advanceWorkflow(item.to)}
                                    >
                                        {item.label}
                                    </Button>
                                );
                            })}
                            {canEditReferral ? (
                                <Button
                                    type="button"
                                    variant={hasWorkflow ? 'secondary' : 'primary'}
                                    size="sm"
                                    className={hasWorkflow ? undefined : navyButtonClass}
                                    onClick={() => setReferOpen(true)}
                                >
                                    <Pencil aria-hidden="true" strokeWidth={1.75} />
                                    {t('documents.refer_edit')}
                                </Button>
                            ) : null}
                        </div>
                    </div>
                ) : null}

                {document.status === 'returned-for-revision' ? (
                    <Notice tone="caution">
                        <span className="block">{t('documents.revision_returned_notice')}</span>
                        {document.return_reason ? (
                            <span className="mt-1 block text-sm">
                                {t('documents.returned_reason')}: {document.return_reason}
                            </span>
                        ) : null}
                    </Notice>
                ) : null}

                {document.returned_referral ? (
                    <Notice tone="caution">
                        <span className="block">
                            {t('documents.returned_notice', {
                                committee: document.returned_referral.committee ?? t('documents.no_committee'),
                            })}
                        </span>
                        {document.returned_referral.outcome_notes ? (
                            <span className="mt-1 block text-sm">
                                {t('documents.returned_reason')}: {document.returned_referral.outcome_notes}
                            </span>
                        ) : null}
                    </Notice>
                ) : null}

                {currentFailed ? (
                    <Notice tone="danger">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p>
                                    {t('documents.processing_status')}: {currentVersion.processing_status_label}
                                    {currentVersion.processing_error ? ` (${currentVersion.processing_error})` : null}
                                </p>
                                {can.uploadVersion ? (
                                    <p className="mt-1 text-ink">{t('documents.processing_failed_reupload_hint')}</p>
                                ) : null}
                            </div>
                            {can.uploadVersion ? (
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        className={navyButtonClass}
                                        onClick={() =>
                                            router.post(
                                                `/documents/${document.slug}/versions/${currentVersion.id}/retry-processing`,
                                                {},
                                                { onError: toastFormErrors },
                                            )
                                        }
                                    >
                                        {t('documents.retry_processing')}
                                    </Button>
                                    <Button type="button" size="sm" variant="secondary" onClick={() => setReuploadOpen(true)}>
                                        {t('documents.reupload_file')}
                                    </Button>
                                </div>
                            ) : null}
                        </div>
                    </Notice>
                ) : null}

                <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20.5rem]">
                    <div className="flex min-w-0 flex-col gap-5">
                        {document.abstract ? (
                            <Panel className={cardClass}>
                                <PanelHead>
                                    <PanelTitle>{t('documents.abstract')}</PanelTitle>
                                </PanelHead>
                                <PanelBody>
                                    <p className="text-sm leading-relaxed text-ink">{document.abstract}</p>
                                </PanelBody>
                            </Panel>
                        ) : null}

                        {canDraftReport || reports.length > 0 ? (
                            <Panel className={cardClass}>
                                <PanelHead>
                                    <div>
                                        <PanelTitle>{t('documents.committee_reports')}</PanelTitle>
                                        <p className="mt-1 text-sm text-ink-muted">
                                            {openReferral
                                                ? t('documents.committee_reports_hint', {
                                                      committee: openReferral.committee ?? t('documents.no_committee'),
                                                  })
                                                : t('documents.committee_reports_closed_hint')}
                                        </p>
                                    </div>
                                    {canDraftReport ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            className={navyButtonClass}
                                            onClick={() => setReportOpen(true)}
                                        >
                                            <FileText aria-hidden="true" strokeWidth={1.75} />
                                            {t(canFileReport ? 'committees.submit_report' : 'documents.draft_report')}
                                        </Button>
                                    ) : null}
                                </PanelHead>
                                <PanelBody>
                                    {reports.length === 0 ? (
                                        <EmptyState
                                            bare
                                            icon={FileSearch}
                                            title={t('documents.committee_reports_empty')}
                                            description={t('documents.committee_reports_empty_hint')}
                                            className="py-10"
                                        />
                                    ) : (
                                        <ul className="space-y-3">
                                            {reports.map((report) => (
                                                <li
                                                    key={report.id}
                                                    className="rounded-[8px] border border-line bg-surface-alt px-4 py-3.5"
                                                >
                                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                                        <div className="min-w-0">
                                                            <p className="font-medium text-ink">
                                                                {report.report_number ?? t('committees.draft_report')}
                                                            </p>
                                                            <p className="mt-0.5 text-xs text-ink-muted">
                                                                {t(`committees.report_status_${slugKey(report.status)}`)}
                                                                {report.submitted_at
                                                                    ? ` — ${t('documents.updated_on', { date: formatDate(report.submitted_at) })}`
                                                                    : null}
                                                            </p>
                                                        </div>
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <StatusChip tone={toneForState(report.status)} size="sm">
                                                                {t(`committees.report_status_${slugKey(report.status)}`)}
                                                            </StatusChip>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="secondary"
                                                                onClick={() => setViewingReport(report)}
                                                            >
                                                                {t('documents.view_report')}
                                                            </Button>
                                                            {report.can?.submit_for_review ? (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    className={navyButtonClass}
                                                                    onClick={() => submitReportForReview(report.id)}
                                                                >
                                                                    {t('committees.submit_for_review')}
                                                                </Button>
                                                            ) : null}
                                                            {report.can?.return_to_draft ? (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="ghost"
                                                                    onClick={() => returnReportToDraft(report.id)}
                                                                >
                                                                    {t('committees.return_report_to_draft')}
                                                                </Button>
                                                            ) : null}
                                                            {report.can?.submit ? (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    className={navyButtonClass}
                                                                    onClick={() => submitReport(report.id)}
                                                                >
                                                                    {t('committees.submit_report')}
                                                                </Button>
                                                            ) : null}
                                                        </div>
                                                    </div>
                                                    <p className="mt-2 text-sm text-ink-muted">
                                                        {t('committees.recommendation')}:{' '}
                                                        {t(`committees.recommendation_${slugKey(report.recommendation)}`)}
                                                    </p>
                                                    {report.status === 'draft' || report.status === 'chair-review' ? (
                                                        <p className="mt-1 text-xs text-ink-faint">
                                                            {t('documents.report_not_submitted')}
                                                        </p>
                                                    ) : null}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </PanelBody>
                            </Panel>
                        ) : null}

                        <Panel className={cardClass}>
                            <PanelHead>
                                <div className="flex items-center gap-2">
                                    <Sparkles aria-hidden="true" className="size-4 text-accent" strokeWidth={1.75} />
                                    <PanelTitle>{t('documents.ai_summary')}</PanelTitle>
                                </div>
                                {can.summarize ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        className={aiSummary ? undefined : navyButtonClass}
                                        variant={aiSummary ? 'secondary' : 'primary'}
                                        disabled={summaryLoading}
                                        onClick={() => generateSummary(Boolean(aiSummary))}
                                    >
                                        {summaryLoading
                                            ? t('documents.ai_summary_generating')
                                            : aiSummary
                                              ? t('documents.ai_summary_refresh')
                                              : t('documents.ai_summary_generate')}
                                    </Button>
                                ) : null}
                            </PanelHead>
                            <PanelBody className="space-y-3">
                                <Notice tone="caution" compact>
                                    {t('ai.verify')}
                                </Notice>
                                {aiSummary ? (
                                    <AiContent showBadge={false}>
                                        <SummaryBody aiSummary={aiSummary} t={t} />
                                    </AiContent>
                                ) : (
                                    <div className="rounded-[8px] border border-dashed border-line px-4">
                                        <SummaryBody aiSummary={aiSummary} t={t} />
                                    </div>
                                )}
                                {summaryError ? <p className="text-sm text-critical">{summaryError}</p> : null}
                            </PanelBody>
                        </Panel>

                        {can.related ? (
                            <Panel className={cardClass}>
                                <PanelHead>
                                    <div>
                                        <PanelTitle>{t('documents.related_title')}</PanelTitle>
                                        <p className="mt-1 text-sm text-ink-muted">{t('documents.related_subtitle')}</p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        disabled={relatedLoading}
                                        onClick={() => void loadRelatedDocuments()}
                                    >
                                        {relatedLoading
                                            ? t('documents.related_loading')
                                            : relatedLoaded
                                              ? t('documents.ai_summary_refresh')
                                              : t('documents.related_add')}
                                    </Button>
                                </PanelHead>
                                <PanelBody>
                                    {relatedError ? (
                                        <Notice tone="danger" className="mb-3">
                                            {relatedError}
                                        </Notice>
                                    ) : null}
                                    {relatedLoading ? (
                                        <AiContent>
                                            <p className="text-sm text-machine-ink">{t('documents.related_loading')}</p>
                                        </AiContent>
                                    ) : !relatedLoaded ? (
                                        <div className="rounded-[8px] border border-dashed border-line">
                                            <EmptyState
                                                bare
                                                icon={Search}
                                                title={t('documents.related_empty_title')}
                                                description={t('documents.related_prompt')}
                                                className="py-10"
                                            />
                                        </div>
                                    ) : relatedDocs.length === 0 ? (
                                        <div className="rounded-[8px] border border-dashed border-line">
                                            <EmptyState
                                                bare
                                                icon={FileSearch}
                                                title={t('documents.related_empty')}
                                                description={t('documents.related_subtitle')}
                                                className="py-10"
                                            />
                                        </div>
                                    ) : (
                                        <ul className="space-y-2">
                                            {relatedDocs.map((suggestion) => (
                                                <li key={suggestion.document_id}>
                                                    <Link
                                                        href={`/documents/${suggestion.slug}`}
                                                        className="flex items-center gap-3 rounded-[8px] border border-line bg-surface px-3 py-3 transition-colors hover:bg-surface-alt"
                                                    >
                                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-sm bg-accent-soft text-accent">
                                                            <FileText aria-hidden="true" className="size-4" strokeWidth={1.75} />
                                                        </span>
                                                        <span className="min-w-0 flex-1">
                                                            <span className="block font-mono text-2xs text-ink-faint">
                                                                {suggestion.reference_number ?? t('documents.no_reference')}
                                                            </span>
                                                            <span className="mt-0.5 block truncate text-sm font-medium text-ink">
                                                                {suggestion.title}
                                                            </span>
                                                            <span className="mt-0.5 block text-2xs tracking-[0.04em] text-ink-faint uppercase">
                                                                {suggestion.document_type_label}
                                                            </span>
                                                        </span>
                                                        <ChevronRight
                                                            aria-hidden="true"
                                                            className="size-4 shrink-0 text-ink-faint"
                                                            strokeWidth={1.75}
                                                        />
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </PanelBody>
                            </Panel>
                        ) : null}

                        <Panel className={cardClass}>
                            <PanelHead>
                                <div>
                                    <PanelTitle>{t('documents.version_history')}</PanelTitle>
                                    <p className="mt-1 text-xs text-ink-subtle">
                                        {t('documents.versions_count', { count: document.versions.length })}
                                    </p>
                                </div>
                            </PanelHead>
                            <PanelBody className="p-0">
                                <ul className="divide-y divide-line">
                                    {document.versions.map((version) => (
                                        <li key={version.id} className="flex flex-wrap items-start justify-between gap-3 px-5 py-3.5">
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="text-sm font-medium text-ink">
                                                        {t('documents.version_label', { number: version.version_number })}
                                                    </p>
                                                    {version.is_current ? (
                                                        <Badge variant="outline" className="border-accent text-accent">
                                                            {t('documents.current_version')}
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                                <p className="mt-1 truncate text-sm text-ink">
                                                    {version.original_filename}
                                                    <span className="ml-1.5 font-mono text-xs text-ink-faint">
                                                        {formatFileSize(version.file_size)}
                                                    </span>
                                                </p>
                                                <p className="mt-0.5 text-xs text-ink-subtle">
                                                    {version.uploaded_by ?? t('documents.unknown_uploader')}
                                                    {version.created_at ? ` · ${formatDateTime(version.created_at)}` : null}
                                                </p>
                                                {version.processing_status && version.processing_status !== 'completed' ? (
                                                    <p
                                                        className={cn(
                                                            'mt-1 text-xs',
                                                            version.processing_status === 'failed'
                                                                ? 'text-critical'
                                                                : 'text-ink-muted',
                                                        )}
                                                    >
                                                        {t('documents.processing_status')}:{' '}
                                                        {version.processing_status_label ?? version.processing_status}
                                                        {version.processing_status === 'failed' && version.processing_error
                                                            ? ` (${version.processing_error})`
                                                            : null}
                                                    </p>
                                                ) : null}
                                                {version.change_summary ? (
                                                    <p className="mt-1 text-sm text-ink-muted">{version.change_summary}</p>
                                                ) : null}
                                            </div>
                                            {can.download ||
                                            (can.uploadVersion && version.is_current && version.processing_status === 'failed') ? (
                                                <div className="flex flex-wrap gap-2">
                                                    {can.uploadVersion &&
                                                    version.is_current &&
                                                    version.processing_status === 'failed' ? (
                                                        <>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                className={navyButtonClass}
                                                                onClick={() =>
                                                                    router.post(
                                                                        `/documents/${document.slug}/versions/${version.id}/retry-processing`,
                                                                        {},
                                                                        { onError: toastFormErrors },
                                                                    )
                                                                }
                                                            >
                                                                {t('documents.retry_processing')}
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="secondary"
                                                                onClick={() => setReuploadOpen(true)}
                                                            >
                                                                {t('documents.reupload_file')}
                                                            </Button>
                                                        </>
                                                    ) : null}
                                                    {can.download && version.mime_type === 'application/pdf' ? (
                                                        <Button variant="ghost" size="sm" asChild>
                                                            <Link
                                                                href={`/documents/${document.slug}/versions/${version.id}/view`}
                                                            >
                                                                {t('documents.view')}
                                                            </Link>
                                                        </Button>
                                                    ) : null}
                                                    {can.download ? (
                                                        <Button variant="secondary" size="sm" asChild>
                                                            <a
                                                                href={`/documents/${document.slug}/versions/${version.id}/download`}
                                                            >
                                                                {t('documents.download')}
                                                            </a>
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                            </PanelBody>
                        </Panel>

                        {can.uploadVersion && !currentFailed ? (
                            <Panel className={cardClass}>
                                <PanelHead>
                                    <div>
                                        <PanelTitle>{t('documents.upload_version')}</PanelTitle>
                                        <p className="mt-1 text-sm text-ink-muted">{t('documents.upload_version_hint')}</p>
                                    </div>
                                </PanelHead>
                                <PanelBody>
                                    <form onSubmit={uploadVersion} className="space-y-3">
                                        <FileDrop
                                            id="version-file"
                                            file={versionForm.data.file}
                                            onFileChange={(file) => versionForm.setData('file', file)}
                                            dropLabel={t('documents.file_drop')}
                                            browseLabel={t('documents.file_browse')}
                                            replaceLabel={t('documents.file_replace')}
                                            removeLabel={t('documents.file_remove')}
                                            emptyHint={t('documents.upload_formats')}
                                            icon={Cloud}
                                            className="border-dashed"
                                        />
                                        <label className="block">
                                            <span className="mb-1.5 block text-xs font-medium text-ink">
                                                {t('documents.change_summary_optional')}
                                            </span>
                                            <Input
                                                placeholder={t('documents.change_summary')}
                                                value={versionForm.data.change_summary}
                                                onChange={(e) => versionForm.setData('change_summary', e.target.value)}
                                            />
                                        </label>
                                        <Button
                                            type="submit"
                                            className={navyButtonClass}
                                            disabled={versionForm.processing}
                                        >
                                            {t('documents.upload_version')}
                                        </Button>
                                    </form>
                                </PanelBody>
                            </Panel>
                        ) : !can.uploadVersion && document.status !== 'returned-for-revision' ? (
                            <Panel className={cardClass}>
                                <PanelBody>
                                    <p className="text-xs text-ink-subtle">{t('documents.upload_locked_hint')}</p>
                                </PanelBody>
                            </Panel>
                        ) : null}
                    </div>

                    <aside className="flex flex-col gap-5 lg:sticky lg:top-4">
                        <Panel className={cn(cardClass, 'pt-0')}>
                            <div className="bg-floor-plate px-5 py-3">
                                <p className="text-2xs font-semibold tracking-[0.14em] text-floor-ink uppercase">
                                    {t('documents.filing_kicker')} / {t('documents.filing_metadata')}
                                </p>
                            </div>
                            <PanelBody className="px-5 py-1">
                                <dl>
                                    <FilingRow label={t('documents.type')} accent>
                                        {document.document_type_label}
                                    </FilingRow>
                                    <FilingRow label={t('documents.status')} accent>
                                        {document.status_label}
                                    </FilingRow>
                                    <FilingRow label={t('documents.classification')}>
                                        {confidentialityLabel}
                                    </FilingRow>
                                    <FilingRow label={t('documents.reference')}>
                                        {document.reference_number ?? t('documents.no_reference')}
                                    </FilingRow>
                                    <FilingRow label={t('documents.author')}>
                                        {document.author ?? t('documents.no_author')}
                                    </FilingRow>
                                    {document.committee || (openReferral?.committees?.length ?? 0) > 0 ? (
                                        <FilingRow label={t('documents.committee')}>
                                            {(openReferral?.committees?.length ?? 0) > 0
                                                ? openReferral?.committees?.join(', ')
                                                : document.committee}
                                        </FilingRow>
                                    ) : null}
                                    {openReferral?.meeting_on ? (
                                        <FilingRow label={t('documents.refer_meeting_on')}>
                                            {formatDate(openReferral.meeting_on)}
                                        </FilingRow>
                                    ) : null}
                                    {openReferral?.remarks ? (
                                        <FilingRow label={t('documents.refer_remarks')}>
                                            {openReferral.remarks}
                                        </FilingRow>
                                    ) : null}
                                    <FilingRow label={t('documents.submitted_at')}>
                                        {formatDate(document.submitted_at)}
                                    </FilingRow>
                                    {document.published_at ? (
                                        <FilingRow label={t('documents.published_at')}>
                                            {formatDate(document.published_at)}
                                        </FilingRow>
                                    ) : null}
                                    {currentVersion ? (
                                        <FilingRow label={t('documents.processing_status')}>
                                            {currentVersion.processing_status_label ?? t('documents.processing_complete')}
                                        </FilingRow>
                                    ) : null}
                                    {currentVersion ? (
                                        <FilingRow label={t('documents.current_file')}>
                                            <span className="block truncate" title={currentVersion.original_filename}>
                                                {currentVersion.original_filename}
                                            </span>
                                        </FilingRow>
                                    ) : null}
                                    {document.tags.length > 0 ? (
                                        <FilingRow label={t('documents.tags')}>
                                            <span className="flex flex-wrap justify-end gap-1">
                                                {document.tags.map((tag) => (
                                                    <Badge key={tag} variant="secondary">
                                                        {tag}
                                                    </Badge>
                                                ))}
                                            </span>
                                        </FilingRow>
                                    ) : null}
                                </dl>
                            </PanelBody>
                        </Panel>

                        <Panel className={cardClass}>
                            <PanelBody className="flex flex-col gap-2">
                                {can.compare ? (
                                    <ToolButton
                                        href={`/ai/compare?a=${encodeURIComponent(document.slug)}`}
                                        icon={GitCompare}
                                        label={t('documents.compare_documents')}
                                    />
                                ) : null}
                                <LegislativeHistorySheet events={history}>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        className="h-auto w-full justify-start gap-3 px-3 py-2.5"
                                    >
                                        <History aria-hidden="true" strokeWidth={1.75} className="size-4 text-ink-muted" />
                                        <span className="text-sm font-medium text-ink">{t('legislation.history_title')}</span>
                                    </Button>
                                </LegislativeHistorySheet>
                                {can.consistency ? (
                                    <ToolButton
                                        href={`/documents/${document.slug}/consistency`}
                                        icon={Scale}
                                        label={t('documents.ai_review')}
                                    />
                                ) : null}
                                {can.createPublication && !publication ? (
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        className="h-auto w-full justify-start gap-3 px-3 py-2.5"
                                        onClick={startPublication}
                                    >
                                        {t('documents.start_publication')}
                                    </Button>
                                ) : null}
                                {can.seal ? (
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        className="h-auto w-full justify-start gap-3 px-3 py-2.5"
                                        onClick={sealDocument}
                                    >
                                        {t('documents.seal_action')}
                                    </Button>
                                ) : null}
                                {canArchiveNow ? (
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        className="h-auto w-full justify-start gap-3 px-3 py-2.5"
                                        onClick={archiveDocument}
                                    >
                                        {t('documents.archive_action')}
                                    </Button>
                                ) : null}
                                {can.delete ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="h-auto w-full justify-start gap-3 px-3 py-2.5 text-critical hover:text-critical"
                                        onClick={deleteDocument}
                                    >
                                        {t('documents.delete')}
                                    </Button>
                                ) : null}
                            </PanelBody>
                        </Panel>

                        {can.grantAccess || document.grants.length > 0 ? (
                            <Panel className={cardClass}>
                                <PanelHead>
                                    <PanelTitle>{t('documents.access_grants')}</PanelTitle>
                                    {can.grantAccess ? (
                                        <Button type="button" size="sm" variant="secondary" onClick={() => setGrantOpen(true)}>
                                            {t('documents.grant_access')}
                                        </Button>
                                    ) : null}
                                </PanelHead>
                                <PanelBody className="space-y-2">
                                    {document.grants.length === 0 ? (
                                        <div className="rounded-[8px] border border-dashed border-line px-4 py-6 text-center">
                                            <p className="text-sm text-ink-muted">{t('documents.grants_empty')}</p>
                                            <p className="mt-1 text-xs text-ink-faint">{t('documents.grants_empty_hint')}</p>
                                        </div>
                                    ) : (
                                        document.grants.map((grant) => (
                                            <div
                                                key={grant.id}
                                                className="flex flex-wrap items-start justify-between gap-2 rounded-[8px] border border-line px-3 py-2"
                                            >
                                                <div>
                                                    <p className="text-sm font-medium text-ink">
                                                        {grant.user ??
                                                            grant.role ??
                                                            grant.committee ??
                                                            t('documents.unknown_grantee')}
                                                    </p>
                                                    <p className="mt-0.5 font-mono text-xs text-ink-subtle">
                                                        {grant.ability}
                                                    </p>
                                                    {grant.reason ? (
                                                        <p className="mt-1 text-xs text-ink-muted">{grant.reason}</p>
                                                    ) : null}
                                                </div>
                                                {can.grantAccess ? (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => {
                                                            if (!window.confirm(t('documents.grant_revoke_confirm'))) {
                                                                return;
                                                            }

                                                            router.delete(
                                                                `/documents/${document.slug}/grants/${grant.id}`,
                                                                { preserveScroll: true, onError: toastFormErrors },
                                                            );
                                                        }}
                                                    >
                                                        {t('documents.grant_revoke')}
                                                    </Button>
                                                ) : null}
                                            </div>
                                        ))
                                    )}
                                </PanelBody>
                            </Panel>
                        ) : null}
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}
