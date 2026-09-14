import { OutcomeReferralDialog } from '@/components/committees/ReturnReferralDialog';
import { AppointMemberDialog } from '@/components/committees/AppointMemberDialog';
import { ReferMeasureDialog } from '@/components/committees/ReferMeasureDialog';
import { ViewReportDialog } from '@/components/documents/ViewReportDialog';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Definition, DefinitionList } from '@/components/ui/definition-list';
import { EmptyState } from '@/components/ui/empty-state';
import { Figure, FigureCell, FigureRow } from '@/components/ui/figure';
import { Notice } from '@/components/ui/notice';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelBody, PanelHead, PanelTitle } from '@/components/ui/panel';
import { Provenance, ProvenanceField } from '@/components/ui/provenance';
import { StatusChip, toneForState } from '@/components/ui/status';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';
import { FileText, Plus, ScrollText, Users } from 'lucide-react';
import { useMemo, useState } from 'react';

/**
 * One committee as a working desk: the standing facts (who it is, who sits on
 * it) stay in view, and the work of the committee — referrals and reports —
 * occupies the main column. Appointing, referring, and drafting stay in
 * dialogs so the record does not leave the page.
 */

type Member = {
    id: string;
    user: string | null;
    position: string;
    is_active: boolean;
    appointed_on: string | null;
};

type Referral = {
    id: string;
    status: string;
    instructions: string | null;
    outcome_notes?: string | null;
    is_primary?: boolean;
    referred_at: string | null;
    due_at: string | null;
    document: { id: string; title: string; slug: string } | null;
    transitions?: { to: string }[];
};

type Report = {
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

type AppointableUser = { id: string; display_name: string };
type ReferableDocument = { id: string; title: string; reference_number: string | null };

type Props = {
    committee: {
        id: string;
        slug: string;
        name: string;
        code: string | null;
        type: string | null;
        mandate: string | null;
        description: string | null;
        is_active: boolean;
        established_on: string | null;
        members: Member[];
        referrals: Referral[];
        reports: Report[];
    };
    can: {
        manageMembers: boolean;
        createReferral: boolean;
        updateReferral: boolean;
        createReport: boolean;
        submitReportForReview?: boolean;
        submitReport: boolean;
        adoptReport?: boolean;
    };
    appointableUsers: AppointableUser[];
    referableDocuments: ReferableDocument[];
};

type DialogKind = 'member' | 'referral' | null;

const POSITION_RANK: Record<string, number> = {
    chair: 0,
    'vice-chair': 1,
    member: 2,
};

const OPEN_REFERRAL = new Set(['pending', 'in-review', 'in_review']);

export default function CommitteesShow({ committee, can, appointableUsers, referableDocuments }: Props) {
    const { t } = useTranslations();
    const { formatDate } = useFormatters();
    const [dialog, setDialog] = useState<DialogKind>(null);
    const [outcomeReferral, setOutcomeReferral] = useState<{ id: string; status: 'returned' | 'closed' } | null>(
        null,
    );
    const [viewingReport, setViewingReport] = useState<Report | null>(null);
    const typeKey = committee.type === 'ad-hoc' ? 'adhoc' : (committee.type ?? 'standing');
    const mandate = committee.mandate || committee.description;

    const members = useMemo(
        () =>
            [...committee.members].sort((a, b) => {
                const rank = (POSITION_RANK[a.position] ?? 9) - (POSITION_RANK[b.position] ?? 9);

                if (rank !== 0) {
                    return rank;
                }

                if (a.is_active !== b.is_active) {
                    return a.is_active ? -1 : 1;
                }

                return (a.user ?? '').localeCompare(b.user ?? '');
            }),
        [committee.members],
    );

    const openReferralCount = committee.referrals.filter((referral) => OPEN_REFERRAL.has(referral.status)).length;

    function submitReportForReview(reportId: string) {
        if (!window.confirm(t('committees.submit_for_review_confirm'))) {
            return;
        }

        router.post(`/reports/${reportId}/submit-for-review`, {}, { preserveScroll: true });
    }

    function returnReportToDraft(reportId: string) {
        if (!window.confirm(t('committees.return_report_to_draft_confirm'))) {
            return;
        }

        router.post(`/reports/${reportId}/return-to-draft`, {}, { preserveScroll: true });
    }

    function submitReport(reportId: string) {
        if (!window.confirm(t('committees.submit_report_confirm'))) {
            return;
        }

        router.post(`/reports/${reportId}/submit`, {}, { preserveScroll: true });
    }

    function adoptReport(reportId: string) {
        if (!window.confirm(t('committees.adopt_report_confirm'))) {
            return;
        }

        router.post(`/reports/${reportId}/adopt`, {}, { preserveScroll: true });
    }

    return (
        <AppLayout title={committee.name}>
            <div className="mx-auto flex max-w-6xl flex-col gap-5">
                <PageHeader
                    title={committee.name}
                    description={t(`committees.type_${typeKey}`)}
                    status={
                        <StatusChip tone={committee.is_active ? 'final' : 'closed'}>
                            {committee.is_active ? t('committees.active') : t('committees.inactive')}
                        </StatusChip>
                    }
                    provenance={
                        <Provenance bare>
                            <ProvenanceField label={t('committees.code')}>
                                {committee.code ?? t('committees.no_code')}
                            </ProvenanceField>
                            <ProvenanceField label={t('committees.established_on')}>
                                {formatDate(committee.established_on)}
                            </ProvenanceField>
                        </Provenance>
                    }
                    actions={
                        <Button variant="ghost" asChild>
                            <Link href="/committees">{t('committees.title')}</Link>
                        </Button>
                    }
                />

                {!committee.is_active ? <Notice tone="caution">{t('committees.inactive_notice')}</Notice> : null}

                <Panel>
                    <PanelBody className="py-2">
                        <FigureRow className="grid-cols-3 sm:grid-cols-3">
                            <FigureCell>
                                <Figure
                                    size="sm"
                                    value={String(members.filter((member) => member.is_active).length)}
                                    label={t('committees.members')}
                                    muted={members.length === 0}
                                />
                            </FigureCell>
                            <FigureCell>
                                <Figure
                                    size="sm"
                                    value={String(openReferralCount)}
                                    label={t('committees.open_referrals')}
                                    muted={openReferralCount === 0}
                                />
                            </FigureCell>
                            <FigureCell>
                                <Figure
                                    size="sm"
                                    value={String(committee.reports.length)}
                                    label={t('committees.reports')}
                                    muted={committee.reports.length === 0}
                                />
                            </FigureCell>
                        </FigureRow>
                    </PanelBody>
                </Panel>

                <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20.5rem]">
                    <div className="min-w-0">
                        <Tabs defaultValue="referrals">
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                                <TabsList>
                                    <TabsTrigger value="referrals">
                                        {t('committees.referrals')}
                                        <span className="font-mono text-2xs text-ink-faint">{committee.referrals.length}</span>
                                    </TabsTrigger>
                                    <TabsTrigger value="reports">
                                        {t('committees.reports')}
                                        <span className="font-mono text-2xs text-ink-faint">{committee.reports.length}</span>
                                    </TabsTrigger>
                                </TabsList>
                                <div className="flex flex-wrap gap-2">
                                    {can.createReferral ? (
                                        <Button type="button" size="sm" variant="primary" onClick={() => setDialog('referral')}>
                                            <Plus aria-hidden="true" strokeWidth={2} />
                                            {t('committees.refer')}
                                        </Button>
                                    ) : null}
                                </div>
                            </div>

                            <TabsContent value="referrals">
                                <Panel>
                                    {committee.referrals.length === 0 ? (
                                        <EmptyState
                                            bare
                                            icon={FileText}
                                            title={t('committees.referrals_empty')}
                                            description={t('committees.referrals_empty_hint')}
                                            action={
                                                can.createReferral ? (
                                                    <Button type="button" size="sm" onClick={() => setDialog('referral')}>
                                                        <Plus aria-hidden="true" strokeWidth={2} />
                                                        {t('committees.refer')}
                                                    </Button>
                                                ) : undefined
                                            }
                                            className="py-14"
                                        />
                                    ) : (
                                        <ul className="divide-y divide-line">
                                            {committee.referrals.map((referral) => (
                                                <ReferralRow
                                                    key={referral.id}
                                                    referral={referral}
                                                    canUpdate={Boolean(can.updateReferral)}
                                                    formatDate={formatDate}
                                                    t={t}
                                                    onOutcome={(status) =>
                                                        setOutcomeReferral({ id: referral.id, status })
                                                    }
                                                />
                                            ))}
                                        </ul>
                                    )}
                                </Panel>
                            </TabsContent>

                            <TabsContent value="reports">
                                <Panel>
                                    {committee.reports.length === 0 ? (
                                        <EmptyState
                                            bare
                                            icon={ScrollText}
                                            title={t('committees.reports_empty')}
                                            description={t('committees.reports_empty_hint')}
                                            className="py-14"
                                        />
                                    ) : (
                                        <ul className="divide-y divide-line">
                                            {committee.reports.map((report) => (
                                                <li key={report.id} className="flex flex-col gap-2 px-5 py-3.5">
                                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                                        <span className="font-medium text-ink">
                                                            {report.report_number ?? t('committees.draft_report')}
                                                        </span>
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
                                                                    variant="secondary"
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
                                                                    variant="primary"
                                                                    onClick={() => submitReport(report.id)}
                                                                >
                                                                    {t('committees.submit_report')}
                                                                </Button>
                                                            ) : null}
                                                            {report.can?.adopt ? (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="primary"
                                                                    onClick={() => adoptReport(report.id)}
                                                                >
                                                                    {t('committees.adopt_report')}
                                                                </Button>
                                                            ) : null}
                                                        </div>
                                                    </div>
                                                    <p className="text-sm text-ink-muted">
                                                        {t('committees.recommendation')}:{' '}
                                                        {t(`committees.recommendation_${slugKey(report.recommendation)}`)}
                                                    </p>
                                                    <p className="font-mono text-xs text-ink-faint">
                                                        {t('committees.submitted_at')} {formatDate(report.submitted_at)}
                                                    </p>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </Panel>
                            </TabsContent>
                        </Tabs>
                    </div>

                    <aside className="flex flex-col gap-5 lg:sticky lg:top-4">
                        <Panel>
                            <PanelHead sunk>
                                <PanelTitle>{t('committees.about')}</PanelTitle>
                            </PanelHead>
                            <PanelBody className="px-5 py-0">
                                <DefinitionList className="border-y-0">
                                    <Definition label={t('committees.code')} numeric>
                                        {committee.code ?? t('committees.no_code')}
                                    </Definition>
                                    <Definition label={t('committees.type')}>{t(`committees.type_${typeKey}`)}</Definition>
                                    <Definition label={t('committees.status')}>
                                        {committee.is_active ? t('committees.active') : t('committees.inactive')}
                                    </Definition>
                                    <Definition label={t('committees.established_on')} numeric>
                                        {formatDate(committee.established_on)}
                                    </Definition>
                                    <Definition label={t('committees.mandate')}>
                                        {mandate ? (
                                            <span className="leading-relaxed">{mandate}</span>
                                        ) : (
                                            t('committees.no_mandate')
                                        )}
                                    </Definition>
                                </DefinitionList>
                            </PanelBody>
                        </Panel>

                        <Panel>
                            <PanelHead>
                                <PanelTitle>{t('committees.roster')}</PanelTitle>
                                {can.manageMembers ? (
                                    <Button type="button" size="sm" onClick={() => setDialog('member')}>
                                        <Plus aria-hidden="true" strokeWidth={2} />
                                        {t('committees.appoint')}
                                    </Button>
                                ) : null}
                            </PanelHead>
                            {members.length === 0 ? (
                                <EmptyState
                                    bare
                                    icon={Users}
                                    title={t('committees.members_empty')}
                                    description={t('committees.members_empty_hint')}
                                    action={
                                        can.manageMembers ? (
                                            <Button type="button" size="sm" onClick={() => setDialog('member')}>
                                                <Plus aria-hidden="true" strokeWidth={2} />
                                                {t('committees.appoint')}
                                            </Button>
                                        ) : undefined
                                    }
                                    className="py-10"
                                />
                            ) : (
                                <ul className="divide-y divide-line">
                                    {members.map((member) => (
                                        <li key={member.id} className="flex items-center gap-3 px-5 py-3">
                                            <Avatar className="size-9">
                                                <AvatarFallback>{initials(member.user)}</AvatarFallback>
                                            </Avatar>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium text-ink">
                                                    {member.user ?? t('committees.unknown_member')}
                                                </p>
                                                <p className="mt-0.5 text-xs text-ink-subtle">
                                                    {t(`committees.position_${slugKey(member.position)}`)}
                                                    {member.appointed_on
                                                        ? ` · ${formatDate(member.appointed_on)}`
                                                        : ''}
                                                </p>
                                            </div>
                                            <StatusChip tone={member.is_active ? 'final' : 'closed'} size="sm">
                                                {member.is_active ? t('committees.active') : t('committees.inactive')}
                                            </StatusChip>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Panel>
                    </aside>
                </div>
            </div>

            {dialog === 'member' ? (
                <AppointMemberDialog
                    committeeSlug={committee.slug}
                    users={appointableUsers}
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                />
            ) : null}

            {dialog === 'referral' ? (
                <ReferMeasureDialog
                    committeeId={committee.id}
                    documents={referableDocuments}
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                />
            ) : null}

            {outcomeReferral ? (
                <OutcomeReferralDialog
                    referralId={outcomeReferral.id}
                    status={outcomeReferral.status}
                    open
                    onOpenChange={(open) => !open && setOutcomeReferral(null)}
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
        </AppLayout>
    );
}

function ReferralRow({
    referral,
    canUpdate,
    formatDate,
    t,
    onOutcome,
}: {
    referral: Referral;
    canUpdate: boolean;
    formatDate: (value: string | null | undefined) => string;
    t: (key: string) => string;
    onOutcome: (status: 'returned' | 'closed') => void;
}) {
    const overdue =
        Boolean(referral.due_at) &&
        OPEN_REFERRAL.has(referral.status) &&
        new Date(referral.due_at as string).getTime() < Date.now();
    const transitions = canUpdate ? (referral.transitions ?? []) : [];

    function advance(to: string) {
        if (to === 'returned' || to === 'closed') {
            onOutcome(to);

            return;
        }

        if (to === 'reported' && !window.confirm(t('committees.mark_reported_confirm'))) {
            return;
        }

        router.put(`/referrals/${referral.id}`, { status: to }, { preserveScroll: true });
    }

    function actionVariant(to: string): 'primary' | 'secondary' | 'danger' {
        if (to === 'closed' || to === 'returned') {
            return to === 'closed' ? 'danger' : 'secondary';
        }

        return 'primary';
    }

    return (
        <li className="flex flex-col gap-2 px-5 py-3.5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    {referral.document ? (
                        <Link
                            href={`/documents/${referral.document.slug}`}
                            className="font-medium text-ink hover:text-accent"
                        >
                            {referral.document.title}
                        </Link>
                    ) : (
                        <span className="font-medium text-ink">{t('committees.unknown_document')}</span>
                    )}
                    <p className="mt-1 font-mono text-xs text-ink-faint">
                        {t('committees.referred_at')} {formatDate(referral.referred_at)}
                        {referral.due_at ? ` · ${t('committees.due_at')} ${formatDate(referral.due_at)}` : ''}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-1.5">
                    {referral.is_primary ? (
                        <Badge variant="outline">{t('committees.primary_referral')}</Badge>
                    ) : null}
                    {overdue ? <Badge variant="destructive">{t('committees.overdue')}</Badge> : null}
                    <StatusChip tone={toneForState(referral.status)} size="sm">
                        {t(`committees.referral_status_${slugKey(referral.status)}`)}
                    </StatusChip>
                </div>
            </div>
            {referral.instructions ? <p className="text-sm leading-relaxed text-ink-muted">{referral.instructions}</p> : null}
            {referral.status === 'returned' && referral.outcome_notes ? (
                <p className="text-sm leading-relaxed text-ink">
                    <span className="text-ink-muted">{t('committees.outcome_notes')}: </span>
                    {referral.outcome_notes}
                </p>
            ) : null}
            {transitions.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                    {transitions.map((item) => (
                        <Button
                            key={item.to}
                            type="button"
                            variant={actionVariant(item.to)}
                            size="sm"
                            onClick={() => advance(item.to)}
                        >
                            {t(`committees.referral_action_${slugKey(item.to)}`)}
                        </Button>
                    ))}
                </div>
            ) : null}
        </li>
    );
}

function slugKey(value: string): string {
    return value.replaceAll('-', '_');
}

function initials(name: string | null): string {
    if (!name) {
        return '?';
    }

    const parts = name.trim().split(/\s+/).filter(Boolean);
    const first = parts[0];

    if (!first) {
        return '?';
    }

    if (parts.length === 1) {
        return first.slice(0, 2).toUpperCase();
    }

    const last = parts[parts.length - 1] ?? '';

    return `${first[0] ?? ''}${last[0] ?? ''}`.toUpperCase();
}
