import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { FilterBar, FilterCell, SearchField } from '@/components/ui/filter-bar';
import { IndexHeader } from '@/components/ui/index-header';
import { Label } from '@/components/ui/label';
import {
    Register,
    RegisterBody,
    RegisterCell,
    RegisterCellActions,
    RegisterCellDate,
    RegisterCellPrimary,
    RegisterEmpty,
    RegisterFooter,
    RegisterFrame,
    RegisterHead,
    RegisterHeadCell,
    RegisterOpenLink,
    RegisterRow,
    type Paginated,
} from '@/components/ui/register';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { LiveDot, StatusChip, toneForState } from '@/components/ui/status';
import { SummaryCard, SummaryGrid } from '@/components/ui/summary-card';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { floorPath, preferredFloorPath } from '@/lib/sessionFloor';
import type { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { CalendarClock, FilePenLine, Gavel, Plus, Radio, SlidersHorizontal } from 'lucide-react';
import { FormEvent, useState } from 'react';

/**
 * The sitting register. Secretariat arrives here to answer three questions in
 * order: is the chamber in session, what is next on the calendar, and which
 * drafts still need an agenda and a date. The figures, the finding aid, and
 * the rows all describe the same filtered set.
 */

type SessionRow = {
    id: string;
    session_number: string;
    title: string;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    scheduled_start_at: string | null;
    venue: string | null;
    attached_document_count: number;
};

type Phase = 'live' | 'upcoming' | 'draft' | 'closed';

type Props = {
    sessions: Paginated<SessionRow>;
    summary: {
        matching: number;
        live: number;
        upcoming: number;
        drafts: number;
    };
    filters: {
        search: string | null;
        type: string | null;
        phase: string | null;
    };
    sessionTypes: { value: string; label: string }[];
    can: { create: boolean };
};

const ANY = '__any';

type VisitInput = {
    search?: string;
    type?: string;
    phase?: string;
};

export default function SessionsIndex({ sessions, summary, filters, sessionTypes, can }: Props) {
    const { t } = useTranslations();
    const { auth } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const [type, setType] = useState(filters.type ?? ANY);
    const [phase, setPhase] = useState(filters.phase ?? ANY);
    const hasFilters = Boolean(filters.search || filters.type || filters.phase);
    const activePhase = filters.phase ?? '';

    function visit(next: VisitInput = {}) {
        const nextSearch = next.search ?? search;
        const nextType = next.type ?? type;
        const nextPhase = next.phase ?? phase;

        router.get(
            '/sessions',
            {
                search: nextSearch || undefined,
                type: nextType === ANY ? undefined : nextType,
                phase: nextPhase === ANY ? undefined : nextPhase,
            },
            { preserveState: true },
        );
    }

    function applyFilters(event: FormEvent) {
        event.preventDefault();
        visit({ search });
    }

    function resetFilters() {
        setSearch('');
        setType(ANY);
        setPhase(ANY);
        visit({ search: '', type: ANY, phase: ANY });
    }

    function applyPhase(next: Phase | typeof ANY) {
        setPhase(next);
        visit({ phase: next, search });
    }

    return (
        <AppLayout title={t('sessions.title')}>
            <div className="flex flex-col gap-5">
                <IndexHeader
                    eyebrow={t('index.eyebrow.chamber')}
                    title={t('sessions.title')}
                    description={t('sessions.index_subtitle')}
                    actions={
                        can.create ? (
                            <Button variant="plate" asChild>
                                <Link href="/sessions/create">
                                    <Plus aria-hidden="true" strokeWidth={1.75} />
                                    {t('sessions.create')}
                                </Link>
                            </Button>
                        ) : null
                    }
                />

                <SummaryGrid>
                    <SummaryCard
                        label={t('sessions.stat_matching')}
                        value={summary.matching}
                        icon={Gavel}
                        pressed={!activePhase}
                        onClick={() => applyPhase(ANY)}
                    />
                    <SummaryCard
                        label={t('sessions.stat_live')}
                        value={summary.live}
                        icon={Radio}
                        live={summary.live > 0}
                        pressed={activePhase === 'live'}
                        onClick={() => applyPhase('live')}
                    />
                    <SummaryCard
                        label={t('sessions.stat_upcoming')}
                        value={summary.upcoming}
                        icon={CalendarClock}
                        pressed={activePhase === 'upcoming'}
                        onClick={() => applyPhase('upcoming')}
                    />
                    <SummaryCard
                        label={t('sessions.stat_drafts')}
                        value={summary.drafts}
                        icon={FilePenLine}
                        pressed={activePhase === 'draft'}
                        onClick={() => applyPhase('draft')}
                    />
                </SummaryGrid>

                <RegisterFrame
                    filters={
                        <FilterBar
                            inline
                            hideSubmit
                            onSubmit={applyFilters}
                            trailing={
                                <Button type="button" variant="secondary" onClick={resetFilters}>
                                    <SlidersHorizontal aria-hidden="true" strokeWidth={1.75} />
                                    {t('register.reset')}
                                </Button>
                            }
                        >
                            <FilterCell grow>
                                <SearchField
                                    id="session-search"
                                    value={search}
                                    onChange={setSearch}
                                    placeholder={t('sessions.search_placeholder')}
                                    label={t('sessions.search')}
                                />
                            </FilterCell>

                            <FilterCell className="min-w-44">
                                <Label htmlFor="session-type">{t('sessions.type')}</Label>
                                <Select
                                    value={type}
                                    onValueChange={(value) => {
                                        setType(value);
                                        visit({ type: value, search });
                                    }}
                                >
                                    <SelectTrigger id="session-type">
                                        <SelectValue placeholder={t('sessions.all_types')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ANY}>{t('sessions.all_types')}</SelectItem>
                                        {sessionTypes.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FilterCell>

                            <FilterCell className="min-w-44">
                                <Label htmlFor="session-phase">{t('sessions.phase')}</Label>
                                <Select
                                    value={phase}
                                    onValueChange={(value) => {
                                        setPhase(value);
                                        visit({ phase: value, search });
                                    }}
                                >
                                    <SelectTrigger id="session-phase">
                                        <SelectValue placeholder={t('sessions.phase_all')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ANY}>{t('sessions.phase_all')}</SelectItem>
                                        <SelectItem value="live">{t('sessions.phase_live')}</SelectItem>
                                        <SelectItem value="upcoming">{t('sessions.phase_upcoming')}</SelectItem>
                                        <SelectItem value="draft">{t('sessions.phase_draft')}</SelectItem>
                                        <SelectItem value="closed">{t('sessions.phase_closed')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FilterCell>
                        </FilterBar>
                    }
                    footer={
                        <RegisterFooter
                            inset
                            from={sessions.from}
                            to={sessions.to}
                            total={sessions.total}
                            links={sessions.links}
                            label={t('sessions.title')}
                        />
                    }
                >
                    <Register flush caption={t('sessions.title')} minWidth="60rem">
                        <RegisterHead>
                            <RegisterHeadCell>{t('sessions.number')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('sessions.title_label')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('sessions.status')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('sessions.scheduled')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('sessions.venue')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">
                                <span className="sr-only">{t('register.row_actions')}</span>
                            </RegisterHeadCell>
                        </RegisterHead>
                        <RegisterBody>
                            {sessions.data.length === 0 ? (
                                <RegisterEmpty colSpan={6}>
                                    <EmptyState
                                        bare
                                        icon={Gavel}
                                        title={hasFilters ? t('sessions.empty_filtered') : t('sessions.empty')}
                                        description={hasFilters ? t('sessions.empty_filtered_hint') : t('sessions.empty_hint')}
                                        action={
                                            hasFilters ? (
                                                <Button variant="secondary" asChild>
                                                    <Link href="/sessions">{t('sessions.clear_filters')}</Link>
                                                </Button>
                                            ) : can.create ? (
                                                <Button variant="plate" asChild>
                                                    <Link href="/sessions/create">{t('sessions.create')}</Link>
                                                </Button>
                                            ) : null
                                        }
                                    />
                                </RegisterEmpty>
                            ) : (
                                sessions.data.map((session) => {
                                    const live = toneForState(session.status) === 'live';
                                    const inChamber = session.status === 'in-session' || session.status === 'suspended';

                                    return (
                                        <RegisterRow key={session.id} live={live}>
                                            <RegisterCell numeric nowrap>
                                                {session.session_number}
                                            </RegisterCell>
                                            <RegisterCellPrimary href={`/sessions/${session.id}`} secondary={session.type_label}>
                                                <span className="inline-flex items-center gap-2">
                                                    {live ? <LiveDot /> : null}
                                                    {session.title}
                                                </span>
                                            </RegisterCellPrimary>
                                            <RegisterCell nowrap>
                                                <StatusChip tone={toneForState(session.status)} size="sm">
                                                    {session.status_label}
                                                </StatusChip>
                                            </RegisterCell>
                                            <RegisterCellDate
                                                value={session.scheduled_start_at}
                                                withTime
                                                fallback={t('sessions.unscheduled')}
                                            />
                                            <RegisterCell nowrap>
                                                <span className={session.venue ? 'text-ink-muted' : 'text-ink-subtle'}>
                                                    {session.venue ?? t('sessions.none')}
                                                </span>
                                            </RegisterCell>
                                            <RegisterCellActions>
                                                <RegisterOpenLink href={`/sessions/${session.id}/documents`}>
                                                    {t('sessions.attached_documents')}
                                                </RegisterOpenLink>
                                                {inChamber ? (
                                                    <>
                                                        <RegisterOpenLink href={preferredFloorPath(session.id, auth.user)}>
                                                            {t('sessions.open_floor')}
                                                        </RegisterOpenLink>
                                                        <RegisterOpenLink href={floorPath(session.id, 'dashboard')}>
                                                            {t('sessions.floor.dashboard')}
                                                        </RegisterOpenLink>
                                                    </>
                                                ) : (
                                                    <RegisterOpenLink href={`/sessions/${session.id}`}>
                                                        {t('register.open')}
                                                    </RegisterOpenLink>
                                                )}
                                            </RegisterCellActions>
                                        </RegisterRow>
                                    );
                                })
                            )}
                        </RegisterBody>
                    </Register>
                </RegisterFrame>
            </div>
        </AppLayout>
    );
}
