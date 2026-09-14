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
import { StatusChip, toneForState } from '@/components/ui/status';
import { SummaryCard, SummaryGrid } from '@/components/ui/summary-card';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';
import { FileCheck2, FilePenLine, ListChecks, Plus, SlidersHorizontal, Stamp } from 'lucide-react';
import { FormEvent, useState } from 'react';

/**
 * The minutes register. Secretariat comes here to see which sittings still
 * need a draft, which drafts are in review, and which records are already
 * official. Figures, filters, and rows describe the same filtered set.
 */

type MinutesSummary = {
    id: string;
    status: string;
    status_label: string;
    revision: number;
    session: { id: string; session_number: string; title: string } | null;
    updated_at: string | null;
};

type Phase = 'drafting' | 'review' | 'final';

type Props = {
    minutes: Paginated<MinutesSummary>;
    summary: {
        matching: number;
        drafting: number;
        review: number;
        final: number;
    };
    filters: {
        search: string | null;
        phase: string | null;
    };
    can: { create: boolean };
};

const ANY = '__any';

type VisitInput = {
    search?: string;
    phase?: string;
};

export default function MinutesIndex({ minutes, summary, filters, can }: Props) {
    const { t } = useTranslations();
    const [search, setSearch] = useState(filters.search ?? '');
    const [phase, setPhase] = useState(filters.phase ?? ANY);
    const hasFilters = Boolean(filters.search || filters.phase);
    const activePhase = filters.phase ?? '';

    function visit(next: VisitInput = {}) {
        const nextSearch = next.search ?? search;
        const nextPhase = next.phase ?? phase;

        router.get(
            '/minutes',
            {
                search: nextSearch || undefined,
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
        setPhase(ANY);
        visit({ search: '', phase: ANY });
    }

    function applyPhase(next: Phase | typeof ANY) {
        setPhase(next);
        visit({ phase: next, search });
    }

    return (
        <AppLayout title={t('minutes.title')}>
            <div className="flex flex-col gap-5">
                <IndexHeader
                    eyebrow={t('index.eyebrow.chamber')}
                    title={t('minutes.title')}
                    description={t('minutes.index_subtitle')}
                    actions={
                        can.create ? (
                            <Button variant="plate" asChild>
                                <Link href="/minutes/create">
                                    <Plus aria-hidden="true" strokeWidth={1.75} />
                                    {t('minutes.create')}
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                <SummaryGrid>
                    <SummaryCard
                        label={t('minutes.stat_matching')}
                        value={summary.matching}
                        icon={ListChecks}
                        pressed={!activePhase}
                        onClick={() => applyPhase(ANY)}
                    />
                    <SummaryCard
                        label={t('minutes.stat_drafting')}
                        value={summary.drafting}
                        icon={FilePenLine}
                        pressed={activePhase === 'drafting'}
                        onClick={() => applyPhase('drafting')}
                    />
                    <SummaryCard
                        label={t('minutes.stat_review')}
                        value={summary.review}
                        icon={Stamp}
                        pressed={activePhase === 'review'}
                        onClick={() => applyPhase('review')}
                    />
                    <SummaryCard
                        label={t('minutes.stat_final')}
                        value={summary.final}
                        icon={FileCheck2}
                        pressed={activePhase === 'final'}
                        onClick={() => applyPhase('final')}
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
                                    id="minutes-search"
                                    value={search}
                                    onChange={setSearch}
                                    placeholder={t('minutes.search_placeholder')}
                                    label={t('minutes.search')}
                                />
                            </FilterCell>
                            <FilterCell className="min-w-44">
                                <Label htmlFor="minutes-phase">{t('minutes.phase')}</Label>
                                <Select
                                    value={phase}
                                    onValueChange={(value) => {
                                        setPhase(value);
                                        visit({ phase: value, search });
                                    }}
                                >
                                    <SelectTrigger id="minutes-phase">
                                        <SelectValue placeholder={t('minutes.phase_all')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ANY}>{t('minutes.phase_all')}</SelectItem>
                                        <SelectItem value="drafting">{t('minutes.phase_drafting')}</SelectItem>
                                        <SelectItem value="review">{t('minutes.phase_review')}</SelectItem>
                                        <SelectItem value="final">{t('minutes.phase_final')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FilterCell>
                        </FilterBar>
                    }
                    footer={
                        <RegisterFooter
                            inset
                            from={minutes.from}
                            to={minutes.to}
                            total={minutes.total}
                            links={minutes.links}
                            label={t('minutes.title')}
                        />
                    }
                >
                    <Register flush caption={t('minutes.title')} minWidth="56rem">
                        <RegisterHead>
                            <RegisterHeadCell>{t('sessions.number')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('minutes.session')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('minutes.status')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">{t('minutes.revision_column')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('minutes.updated_at')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">
                                <span className="sr-only">{t('register.row_actions')}</span>
                            </RegisterHeadCell>
                        </RegisterHead>
                        <RegisterBody>
                            {minutes.data.length === 0 ? (
                                <RegisterEmpty colSpan={6}>
                                    <EmptyState
                                        bare
                                        icon={ListChecks}
                                        title={hasFilters ? t('minutes.empty_filtered') : t('minutes.empty')}
                                        description={
                                            hasFilters ? t('minutes.empty_filtered_hint') : t('minutes.empty_hint')
                                        }
                                        action={
                                            hasFilters ? (
                                                <Button variant="secondary" asChild>
                                                    <Link href="/minutes">{t('minutes.clear_filters')}</Link>
                                                </Button>
                                            ) : can.create ? (
                                                <Button variant="plate" asChild>
                                                    <Link href="/minutes/create">{t('minutes.create')}</Link>
                                                </Button>
                                            ) : null
                                        }
                                    />
                                </RegisterEmpty>
                            ) : (
                                minutes.data.map((record) => (
                                    <RegisterRow key={record.id}>
                                        <RegisterCell numeric nowrap>
                                            {record.session?.session_number ?? '—'}
                                        </RegisterCell>
                                        <RegisterCellPrimary href={`/minutes/${record.id}`}>
                                            {record.session?.title ?? t('minutes.unknown_session')}
                                        </RegisterCellPrimary>
                                        <RegisterCell nowrap>
                                            <StatusChip tone={toneForState(record.status)} size="sm">
                                                {record.status_label}
                                            </StatusChip>
                                        </RegisterCell>
                                        <RegisterCell numeric nowrap align="right">
                                            r{record.revision}
                                        </RegisterCell>
                                        <RegisterCellDate value={record.updated_at} withTime />
                                        <RegisterCellActions>
                                            <RegisterOpenLink href={`/minutes/${record.id}`}>
                                                {t('register.open')}
                                            </RegisterOpenLink>
                                        </RegisterCellActions>
                                    </RegisterRow>
                                ))
                            )}
                        </RegisterBody>
                    </Register>
                </RegisterFrame>
            </div>
        </AppLayout>
    );
}
