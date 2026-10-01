import { AppointMemberDialog } from '@/components/committees/AppointMemberDialog';
import { ReferMeasureDialog } from '@/components/committees/ReferMeasureDialog';
import { OutcomeReferralDialog } from '@/components/committees/ReturnReferralDialog';
import { ViewReportDialog } from '@/components/documents/ViewReportDialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Notice } from '@/components/ui/notice';
import { Panel, PanelBody, PanelHead, PanelTitle } from '@/components/ui/panel';
import { StatusChip, toneForState } from '@/components/ui/status';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Timeline, TimelineItem } from '@/components/ui/timeline';
import { UserAvatar } from '@/components/users/UserAvatar';
import AppLayout from '@/layouts/AppLayout';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Link, router } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { CalendarDays, Check, Clock, FileText, History, Landmark, Plus, ScrollText, Users } from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';

/**
 * One committee as a working desk. The plate states the standing facts — kind,
 * name, mandate, and the three counts the secretariat is asked for — and the
 * panels below it carry the work: what the committee may deliberate on, its
 * citation, the measures and reports on its calendar, and who sits on it.
 * Appointing, referring, and drafting stay in dialogs so the record does not
 * leave the page.
 */

type Member = {
    id: string;
    user: string | null;
    avatar_url: string | null;
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
    meeting_on?: string | null;
    completed_at?: string | null;
    document: {
        id: string;
        title: string;
        slug: string;
        reference_number?: string | null;
        document_type_label?: string | null;
    } | null;
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
        /** Not recorded against a committee yet; the panel waits for it. */
        subject_areas?: string[] | null;
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
        submitReport?: boolean;
    };
    appointableUsers: AppointableUser[];
    referableDocuments: ReferableDocument[];
};

type DialogKind = 'member' | 'referral' | null;

type ActivityEvent = {
    id: string;
    label: string;
    detail: string | null;
    at: string | null;
};

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
    const [outcomeReferral, setOutcomeReferral] = useState<{ id: string; status: 'returned' | 'closed' } | null>(null);
    const [viewingReport, setViewingReport] = useState<Report | null>(null);
    const typeKey = committee.type === 'ad-hoc' ? 'adhoc' : (committee.type ?? 'standing');
    const mandate = committee.mandate || committee.description;
    const subjectAreas = committee.subject_areas ?? [];

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

    const activeMembers = members.filter((member) => member.is_active);
    const chair = activeMembers.find((member) => member.position === 'chair');
    const openReferralCount = committee.referrals.filter((referral) => OPEN_REFERRAL.has(referral.status)).length;
    const nextMeeting = useMemo(() => nextMeetingOnCalendar(committee.referrals), [committee.referrals]);
    const activity = useMemo(
        () => committeeActivity(committee.referrals, committee.reports, members, t),
        [committee.referrals, committee.reports, members, t],
    );

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

    return (
        <AppLayout title={committee.name}>
            <div className="mx-auto flex max-w-6xl flex-col gap-4">
                <nav aria-label={t('nav.group_record')} className="text-xs text-ink-muted">
                    <Link href="/committees" className="hover:text-ink">
                        {t('nav.committees')}
                    </Link>
                    <span aria-hidden="true" className="px-1.5 text-ink-faint">
                        ›
                    </span>
                    <span className="font-medium text-ink">{committee.code ?? committee.name}</span>
                </nav>

                <section className="bg-record-hero overflow-hidden rounded-[var(--radius-xl)] text-floor-ink">
                    <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-5 px-6 py-6 sm:px-7 sm:py-7">
                        <div className="max-w-2xl min-w-0">
                            <p className="flex items-center gap-2.5">
                                <span className="flex size-7 shrink-0 items-center justify-center rounded-[var(--radius-sm)] border border-floor-line bg-floor-sunk">
                                    <Landmark aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                                </span>
                                <span className="text-2xs font-semibold tracking-[0.16em] text-floor-ink-faint uppercase">
                                    {t('committees.kind_eyebrow', { type: t(`committees.type_${typeKey}`) })}
                                </span>
                            </p>

                            <h1 className="mt-3 text-2xl font-semibold tracking-[-0.02em] text-floor-ink sm:text-3xl">
                                {committee.name}
                            </h1>

                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                <HeroTag>
                                    <span
                                        aria-hidden="true"
                                        className={cn(
                                            'size-1.5 shrink-0 rounded-full',
                                            committee.is_active ? 'bg-success' : 'bg-floor-ink-faint',
                                        )}
                                    />
                                    {committee.is_active ? t('committees.active') : t('committees.inactive')}
                                </HeroTag>
                                <HeroTag>
                                    <span className="text-floor-ink-faint">{t('committees.code')}</span>
                                    <span className="font-mono">{committee.code ?? t('committees.no_code')}</span>
                                </HeroTag>
                                <span className="text-xs text-floor-ink-faint">
                                    {t('committees.established_on')}{' '}
                                    <span className="font-mono text-floor-ink-muted">{formatDate(committee.established_on)}</span>
                                </span>
                            </div>

                            {mandate ? <p className="mt-4 text-sm leading-relaxed text-floor-ink-muted">{mandate}</p> : null}
                        </div>

                        {nextMeeting ? (
                            <div className="rounded-[var(--radius-md)] border border-floor-line bg-floor-sunk px-4 py-3">
                                <p className="flex items-center gap-1.5 text-2xs font-semibold tracking-[0.14em] text-floor-ink-faint uppercase">
                                    <CalendarDays aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                                    {t('committees.next_meeting')}
                                </p>
                                <p className="mt-1.5 font-mono text-sm font-semibold text-floor-ink">
                                    {formatDate(nextMeeting.on)}
                                </p>
                                <p className="mt-1 flex items-center gap-1.5 text-2xs text-floor-ink-muted">
                                    <FileText aria-hidden="true" strokeWidth={1.75} className="size-3" />
                                    {t('committees.next_meeting_measures', { count: nextMeeting.count })}
                                </p>
                            </div>
                        ) : null}
                    </div>

                    <div className="grid grid-cols-3 divide-x divide-floor-line border-t border-floor-line">
                        <HeroFigure icon={Users} label={t('committees.members')} value={activeMembers.length} />
                        <HeroFigure icon={FileText} label={t('committees.open_referrals')} value={openReferralCount} />
                        <HeroFigure icon={ScrollText} label={t('committees.reports')} value={committee.reports.length} />
                    </div>
                </section>

                {!committee.is_active ? <Notice tone="caution">{t('committees.inactive_notice')}</Notice> : null}

                <div className={cn('grid items-start gap-4', subjectAreas.length > 0 && 'lg:grid-cols-[minmax(0,1fr)_21.5rem]')}>
                    {subjectAreas.length > 0 ? (
                        <Panel>
                            <PanelBody className="px-6 py-5">
                                <PanelTitle className="text-base">{t('committees.jurisdiction')}</PanelTitle>
                                <p className="mt-1 text-sm text-ink-muted">{t('committees.jurisdiction_caption')}</p>
                                <ul className="mt-4 grid gap-x-8 gap-y-2.5 sm:grid-cols-2">
                                    {subjectAreas.map((area) => (
                                        <li key={area} className="flex items-start gap-2.5 text-sm text-ink">
                                            <span
                                                aria-hidden="true"
                                                className="mt-px flex size-4.5 shrink-0 items-center justify-center rounded-full border border-accent-line bg-accent-soft text-accent"
                                            >
                                                <Check strokeWidth={2.25} className="size-2.5" />
                                            </span>
                                            {area}
                                        </li>
                                    ))}
                                </ul>
                            </PanelBody>
                        </Panel>
                    ) : null}

                    <Panel>
                        <PanelBody className="px-6 py-5">
                            <PanelTitle className="text-base">{t('committees.at_a_glance')}</PanelTitle>
                            <dl
                                className={cn('mt-3 grid gap-x-10', subjectAreas.length === 0 && 'sm:grid-cols-2 lg:grid-cols-3')}
                            >
                                <GlanceRow label={t('committees.code')} numeric>
                                    {committee.code ?? t('committees.no_code')}
                                </GlanceRow>
                                <GlanceRow label={t('committees.type')}>{t(`committees.type_${typeKey}`)}</GlanceRow>
                                <GlanceRow label={t('committees.status')}>
                                    {committee.is_active ? t('committees.active') : t('committees.inactive')}
                                </GlanceRow>
                                <GlanceRow label={t('committees.established_on')} numeric>
                                    {formatDate(committee.established_on)}
                                </GlanceRow>
                                <GlanceRow label={t('committees.chair')}>{chair?.user ?? t('committees.no_chair')}</GlanceRow>
                            </dl>
                        </PanelBody>
                    </Panel>
                </div>

                <Panel>
                    <Tabs defaultValue="referrals" className="gap-0">
                        <PanelHead className="items-start">
                            <div className="min-w-0">
                                <PanelTitle className="text-base">{t('committees.committee_work')}</PanelTitle>
                                <p className="mt-1 text-sm text-ink-muted">{t('committees.committee_work_caption')}</p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <TabsList>
                                    <TabsTrigger value="referrals">
                                        {t('committees.referrals')}
                                        <TabCount>{committee.referrals.length}</TabCount>
                                    </TabsTrigger>
                                    <TabsTrigger value="reports">
                                        {t('committees.reports')}
                                        <TabCount>{committee.reports.length}</TabCount>
                                    </TabsTrigger>
                                    <TabsTrigger value="activity">{t('committees.activity')}</TabsTrigger>
                                </TabsList>
                                {can.createReferral ? (
                                    <Button type="button" size="sm" variant="primary" onClick={() => setDialog('referral')}>
                                        <Plus aria-hidden="true" strokeWidth={2} />
                                        {t('committees.refer')}
                                    </Button>
                                ) : null}
                            </div>
                        </PanelHead>

                        <TabsContent value="referrals">
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
                                            onOutcome={(status) => setOutcomeReferral({ id: referral.id, status })}
                                        />
                                    ))}
                                </ul>
                            )}
                        </TabsContent>

                        <TabsContent value="reports">
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
                                            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                                                <div className="flex min-w-0 flex-wrap items-center gap-2.5">
                                                    <span className="font-mono text-2xs text-ink-faint">
                                                        {report.report_number ?? t('committees.draft_report')}
                                                    </span>
                                                    <Badge variant="outline">
                                                        {t(`committees.recommendation_${slugKey(report.recommendation)}`)}
                                                    </Badge>
                                                    {report.submitter ? (
                                                        <span className="truncate text-sm text-ink">{report.submitter}</span>
                                                    ) : null}
                                                </div>
                                                <div className="flex shrink-0 flex-wrap items-center gap-2">
                                                    <StatusChip tone={toneForState(report.status)} size="sm">
                                                        {t(`committees.report_status_${slugKey(report.status)}`)}
                                                    </StatusChip>
                                                    <RowDate>{formatDate(report.submitted_at)}</RowDate>
                                                </div>
                                            </div>
                                            <div className="flex flex-wrap items-center gap-2">
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
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </TabsContent>

                        <TabsContent value="activity">
                            {activity.length === 0 ? (
                                <EmptyState
                                    bare
                                    icon={History}
                                    title={t('committees.activity_empty')}
                                    description={t('committees.activity_empty_hint')}
                                    className="py-14"
                                />
                            ) : (
                                <Timeline className="px-5 py-4">
                                    {activity.map((event, index) => (
                                        <TimelineItem
                                            key={event.id}
                                            label={event.label}
                                            when={formatDate(event.at)}
                                            current={index === 0}
                                        >
                                            {event.detail}
                                        </TimelineItem>
                                    ))}
                                </Timeline>
                            )}
                        </TabsContent>
                    </Tabs>
                </Panel>

                <Panel>
                    <PanelHead className="items-start">
                        <div className="min-w-0">
                            <PanelTitle className="text-base">{t('committees.roster')}</PanelTitle>
                            <p className="mt-1 text-sm text-ink-muted">{t('committees.roster_caption')}</p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">{t('committees.member_count', { count: activeMembers.length })}</Badge>
                            {can.manageMembers ? (
                                <Button type="button" size="sm" onClick={() => setDialog('member')}>
                                    <Plus aria-hidden="true" strokeWidth={2} />
                                    {t('committees.appoint')}
                                </Button>
                            ) : null}
                        </div>
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
                        <PanelBody className="grid gap-3 px-5 py-4 sm:grid-cols-2">
                            {members.map((member) => (
                                <div
                                    key={member.id}
                                    className="flex items-center gap-3 rounded-[var(--radius-md)] border border-line px-3.5 py-3"
                                >
                                    <UserAvatar
                                        name={member.user}
                                        src={member.avatar_url}
                                        className="size-9"
                                        fallbackClassName="text-2xs"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-ink">
                                            {member.user ?? t('committees.unknown_member')}
                                        </p>
                                        <p className="mt-0.5 truncate text-xs text-ink-subtle">
                                            {t(`committees.position_${slugKey(member.position)}`)}
                                            {member.appointed_on
                                                ? ` · ${t('committees.member_since', {
                                                      date: formatDate(member.appointed_on),
                                                  })}`
                                                : ''}
                                        </p>
                                    </div>
                                    <StatusChip tone={member.is_active ? 'final' : 'closed'} size="sm">
                                        {member.is_active ? t('committees.active') : t('committees.inactive')}
                                    </StatusChip>
                                </div>
                            ))}
                        </PanelBody>
                    )}
                </Panel>
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

function HeroTag({ children }: { children: ReactNode }) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-[var(--radius-xs)] border border-floor-line bg-floor-sunk px-2 py-0.5 text-2xs font-medium text-floor-ink">
            {children}
        </span>
    );
}

function HeroFigure({ icon: Icon, label, value }: { icon: LucideIcon; label: string; value: number }) {
    return (
        <div className="px-6 py-4 sm:px-7">
            <p className="flex items-center gap-1.5 text-2xs font-semibold tracking-[0.14em] text-floor-ink-faint uppercase">
                <Icon aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                {label}
            </p>
            <p
                className={cn(
                    'mt-1.5 font-mono text-figure-sm tracking-[-0.02em]',
                    value === 0 ? 'text-floor-ink-faint' : 'text-floor-ink',
                )}
            >
                {value}
            </p>
        </div>
    );
}

function TabCount({ children }: { children: ReactNode }) {
    return <span className="font-mono text-2xs text-ink-faint">{children}</span>;
}

function GlanceRow({ label, children, numeric = false }: { label: string; children: ReactNode; numeric?: boolean }) {
    return (
        <div className="flex items-baseline justify-between gap-4 border-b border-line py-2.5 last:border-b-0">
            <dt className="shrink-0 text-xs text-ink-subtle">{label}</dt>
            <dd className={cn('min-w-0 truncate text-right text-sm text-ink', numeric && 'font-mono text-xs')}>{children}</dd>
        </div>
    );
}

function RowDate({ children }: { children: ReactNode }) {
    return (
        <span className="inline-flex items-center gap-1.5 font-mono text-2xs text-ink-faint">
            <Clock aria-hidden="true" strokeWidth={1.75} className="size-3" />
            {children}
        </span>
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
            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                <div className="flex min-w-0 flex-wrap items-center gap-2.5">
                    <span className="font-mono text-2xs text-ink-faint">
                        {referral.document?.reference_number ?? t('documents.no_reference')}
                    </span>
                    {referral.document?.document_type_label ? (
                        <Badge variant="outline">{referral.document.document_type_label}</Badge>
                    ) : null}
                    {referral.document ? (
                        <Link
                            href={`/documents/${referral.document.slug}`}
                            className="truncate text-sm font-medium text-ink hover:text-accent"
                        >
                            {referral.document.title}
                        </Link>
                    ) : (
                        <span className="text-sm font-medium text-ink">{t('committees.unknown_document')}</span>
                    )}
                </div>
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {referral.is_primary ? <Badge variant="outline">{t('committees.primary_referral')}</Badge> : null}
                    {overdue ? <Badge variant="destructive">{t('committees.overdue')}</Badge> : null}
                    <StatusChip tone={toneForState(referral.status)} size="sm">
                        {t(`committees.referral_status_${slugKey(referral.status)}`)}
                    </StatusChip>
                    <RowDate>{formatDate(referral.referred_at)}</RowDate>
                </div>
            </div>
            {referral.meeting_on || referral.due_at ? (
                <p className="font-mono text-2xs text-ink-faint">
                    {referral.meeting_on ? `${t('committees.meeting_on')} ${formatDate(referral.meeting_on)}` : ''}
                    {referral.meeting_on && referral.due_at ? ' · ' : ''}
                    {referral.due_at ? `${t('committees.due_at')} ${formatDate(referral.due_at)}` : ''}
                </p>
            ) : null}
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

/**
 * The committee's own calendar: the soonest date a measure is set to be heard,
 * and how many measures are set for that same sitting.
 */
function nextMeetingOnCalendar(referrals: Referral[]): { on: string; count: number } | null {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const scheduled = referrals
        .filter((referral) => OPEN_REFERRAL.has(referral.status))
        .map((referral) => referral.meeting_on)
        .filter((value): value is string => Boolean(value))
        .filter((value) => new Date(value).getTime() >= today.getTime())
        .sort();

    const on = scheduled[0];

    if (!on) {
        return null;
    }

    return { on, count: scheduled.filter((value) => value === on).length };
}

/**
 * Recent activity is read back out of the record already on the page —
 * referrals, their outcomes, filed reports, and appointments — newest first.
 */
function committeeActivity(
    referrals: Referral[],
    reports: Report[],
    members: Member[],
    t: (key: string) => string,
): ActivityEvent[] {
    const events: ActivityEvent[] = [];

    referrals.forEach((referral) => {
        const subject = referral.document?.title ?? t('committees.unknown_document');

        if (referral.referred_at) {
            events.push({
                id: `referred-${referral.id}`,
                label: t('committees.activity_referred'),
                detail: subject,
                at: referral.referred_at,
            });
        }

        if (!OPEN_REFERRAL.has(referral.status)) {
            events.push({
                id: `outcome-${referral.id}`,
                label: t(`committees.activity_${slugKey(referral.status)}`),
                detail: subject,
                at: referral.completed_at ?? referral.referred_at,
            });
        }
    });

    reports.forEach((report) => {
        if (!report.submitted_at) {
            return;
        }

        events.push({
            id: `report-${report.id}`,
            label: t('committees.activity_report_filed'),
            detail: report.report_number ?? t('committees.draft_report'),
            at: report.submitted_at,
        });
    });

    members.forEach((member) => {
        if (!member.appointed_on) {
            return;
        }

        events.push({
            id: `member-${member.id}`,
            label: t('committees.activity_appointed'),
            detail: `${member.user ?? t('committees.unknown_member')} · ${t(`committees.position_${slugKey(member.position)}`)}`,
            at: member.appointed_on,
        });
    });

    return events.sort((a, b) => timestamp(b.at) - timestamp(a.at));
}

function timestamp(value: string | null): number {
    if (!value) {
        return 0;
    }

    const parsed = new Date(value).getTime();

    return Number.isNaN(parsed) ? 0 : parsed;
}

function slugKey(value: string): string {
    return value.replaceAll('-', '_');
}
