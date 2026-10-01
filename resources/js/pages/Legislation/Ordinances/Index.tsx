import { LegislativeHistoryDrawer } from '@/components/legislation/LegislativeHistoryPreview';
import { LegislationRegisterNav } from '@/components/legislation/LegislationRegisterNav';
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
import { FileText, ListChecks, PenLine, Plus, Scale, SlidersHorizontal, Upload } from 'lucide-react';
import { FormEvent, useState } from 'react';

/**
 * The ordinance register: a strip of figures over the filtered set, a finding
 * aid, then the rows. Identity left, state middle, dates and year right,
 * actions last — the same shape as the document register.
 */

type OrdinanceRow = {
    id: string;
    ordinance_number: string;
    series_year: number;
    title: string;
    status: string;
    status_label: string | null;
    enacted_on: string | null;
};

type Props = {
    ordinances: Paginated<OrdinanceRow>;
    summary: {
        matching: number;
        draft: number;
        pending: number;
        enacted: number;
    };
    filters: {
        search: string | null;
        status: string | null;
        year: number | null;
    };
    statuses: string[];
    years: number[];
    can: { create: boolean };
};

const ANY = '__any';

type VisitInput = {
    search?: string;
    status?: string;
    year?: string;
};

export default function OrdinancesIndex({ ordinances, summary, filters, statuses, years, can }: Props) {
    const { t } = useTranslations();
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? ANY);
    const [year, setYear] = useState(filters.year ? String(filters.year) : ANY);
    const activeStatus = filters.status ?? '';

    function visit(next: VisitInput = {}) {
        const nextSearch = next.search ?? search;
        const nextStatus = next.status ?? status;
        const nextYear = next.year ?? year;

        router.get(
            '/ordinances',
            {
                search: nextSearch || undefined,
                status: nextStatus === ANY ? undefined : nextStatus,
                year: nextYear === ANY ? undefined : nextYear,
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
        setStatus(ANY);
        setYear(ANY);
        visit({ search: '', status: ANY, year: ANY });
    }

    function applyStatus(next: string) {
        setStatus(next);
        visit({ status: next, search });
    }

    return (
        <AppLayout title={t('legislation.ordinances')}>
            <div className="flex flex-col gap-5">
                <IndexHeader
                    eyebrow={t('index.eyebrow.legislation')}
                    title={t('legislation.ordinances')}
                    description={t('legislation.ordinances_subtitle')}
                    actions={
                        can.create ? (
                            <>
                                <Button variant="secondary" asChild>
                                    <Link href="/ordinances/import">
                                        <Upload aria-hidden="true" strokeWidth={1.75} />
                                        {t('legislation.import_ordinances')}
                                    </Link>
                                </Button>
                                <Button variant="plate" asChild>
                                    <Link href="/ordinances/create">
                                        <Plus aria-hidden="true" strokeWidth={1.75} />
                                        {t('legislation.create_ordinance')}
                                    </Link>
                                </Button>
                            </>
                        ) : undefined
                    }
                />

                <LegislationRegisterNav />

                <SummaryGrid>
                    <SummaryCard
                        label={t('legislation.stat_matching')}
                        value={summary.matching}
                        icon={Scale}
                        pressed={!activeStatus}
                        onClick={() => applyStatus(ANY)}
                    />
                    <SummaryCard
                        label={t('legislation.stat_draft')}
                        value={summary.draft}
                        icon={PenLine}
                        pressed={activeStatus === 'draft'}
                        onClick={() => applyStatus('draft')}
                    />
                    <SummaryCard
                        label={t('legislation.stat_pending')}
                        value={summary.pending}
                        icon={ListChecks}
                        pressed={activeStatus === 'pending'}
                        onClick={() => applyStatus('pending')}
                    />
                    <SummaryCard
                        label={t('legislation.stat_enacted')}
                        value={summary.enacted}
                        icon={FileText}
                        pressed={activeStatus === 'enacted'}
                        onClick={() => applyStatus('enacted')}
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
                                    id="search"
                                    value={search}
                                    onChange={setSearch}
                                    placeholder={t('legislation.search_placeholder')}
                                    label={t('legislation.search')}
                                />
                            </FilterCell>
                            <FilterCell className="min-w-44">
                                <Label htmlFor="status">{t('legislation.status')}</Label>
                                <Select
                                    value={status}
                                    onValueChange={(value) => {
                                        setStatus(value);
                                        visit({ status: value, search });
                                    }}
                                >
                                    <SelectTrigger id="status">
                                        <SelectValue placeholder={t('legislation.all_statuses')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ANY}>{t('legislation.all_statuses')}</SelectItem>
                                        {statuses.map((value) => (
                                            <SelectItem key={value} value={value}>
                                                {statusLabel(t, value)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FilterCell>
                            <FilterCell className="min-w-36">
                                <Label htmlFor="year">{t('legislation.year')}</Label>
                                <Select
                                    value={year}
                                    onValueChange={(value) => {
                                        setYear(value);
                                        visit({ year: value, search });
                                    }}
                                >
                                    <SelectTrigger id="year">
                                        <SelectValue placeholder={t('legislation.all_years')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ANY}>{t('legislation.all_years')}</SelectItem>
                                        {years.map((value) => (
                                            <SelectItem key={value} value={String(value)}>
                                                {value}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FilterCell>
                        </FilterBar>
                    }
                    footer={
                        <RegisterFooter
                            inset
                            from={ordinances.from}
                            to={ordinances.to}
                            total={ordinances.total}
                            links={ordinances.links}
                            label={t('legislation.ordinances')}
                        />
                    }
                >
                    <Register flush caption={t('legislation.ordinances')}>
                        <RegisterHead>
                            <RegisterHeadCell>{t('legislation.number')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('legislation.title')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('legislation.status')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('legislation.enacted_on')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">{t('legislation.year')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">
                                <span className="sr-only">{t('register.row_actions')}</span>
                            </RegisterHeadCell>
                        </RegisterHead>
                        <RegisterBody>
                            {ordinances.data.length === 0 ? (
                                <RegisterEmpty colSpan={6}>
                                    <EmptyState
                                        bare
                                        icon={Scale}
                                        title={t('legislation.ordinances_empty')}
                                        description={t('legislation.ordinances_empty_hint')}
                                        action={
                                            can.create ? (
                                                <Button variant="plate" size="sm" asChild>
                                                    <Link href="/ordinances/create">{t('legislation.create_ordinance')}</Link>
                                                </Button>
                                            ) : undefined
                                        }
                                    />
                                </RegisterEmpty>
                            ) : (
                                ordinances.data.map((ordinance) => (
                                    <RegisterRow key={ordinance.id}>
                                        <RegisterCell numeric nowrap>
                                            {ordinance.ordinance_number}
                                        </RegisterCell>
                                        <RegisterCellPrimary href={`/ordinances/${ordinance.id}`}>
                                            {ordinance.title}
                                        </RegisterCellPrimary>
                                        <RegisterCell nowrap>
                                            <StatusChip tone={toneForState(ordinance.status)} size="sm">
                                                {statusLabel(t, ordinance.status, ordinance.status_label)}
                                            </StatusChip>
                                        </RegisterCell>
                                        <RegisterCellDate value={ordinance.enacted_on} />
                                        <RegisterCell numeric nowrap align="right">
                                            {ordinance.series_year}
                                        </RegisterCell>
                                        <RegisterCellActions>
                                            <RegisterOpenLink href={`/ordinances/${ordinance.id}`}>
                                                {t('register.open')}
                                            </RegisterOpenLink>
                                            <LegislativeHistoryDrawer
                                                href={`/ordinances/${ordinance.id}/history`}
                                                title={ordinance.title}
                                                number={ordinance.ordinance_number}
                                            >
                                                <Button type="button" variant="ghost" size="sm">
                                                    {t('legislation.history_title')}
                                                </Button>
                                            </LegislativeHistoryDrawer>
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

function statusLabel(
    t: (key: string) => string,
    status: string,
    fallback: string | null = null,
): string {
    const key = `legislation.status_${status}`;
    const translated = t(key);

    return translated === key ? (fallback ?? status) : translated;
}
