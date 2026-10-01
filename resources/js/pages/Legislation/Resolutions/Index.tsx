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
import { FileText, ListChecks, PenLine, Plus, ScrollText, SlidersHorizontal, Upload } from 'lucide-react';
import { FormEvent, useState } from 'react';

type ResolutionRow = {
    id: string;
    resolution_number: string;
    series_year: number;
    title: string;
    status: string;
    status_label: string | null;
    adopted_on: string | null;
    document: { slug: string } | null;
};

type Props = {
    resolutions: Paginated<ResolutionRow>;
    summary: {
        matching: number;
        draft: number;
        pending: number;
        adopted: number;
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

export default function ResolutionsIndex({ resolutions, summary, filters, statuses, years, can }: Props) {
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
            '/resolutions',
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
        <AppLayout title={t('legislation.resolutions')}>
            <div className="flex flex-col gap-5">
                <IndexHeader
                    eyebrow={t('index.eyebrow.legislation')}
                    title={t('legislation.resolutions')}
                    description={t('legislation.resolutions_subtitle')}
                    actions={
                        can.create ? (
                            <>
                                <Button variant="secondary" asChild>
                                    <Link href="/resolutions/import">
                                        <Upload aria-hidden="true" strokeWidth={1.75} />
                                        {t('legislation.import_resolutions')}
                                    </Link>
                                </Button>
                                <Button variant="plate" asChild>
                                    <Link href="/resolutions/create">
                                        <Plus aria-hidden="true" strokeWidth={1.75} />
                                        {t('legislation.create_resolution')}
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
                        icon={ScrollText}
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
                        label={t('legislation.stat_adopted')}
                        value={summary.adopted}
                        icon={FileText}
                        pressed={activeStatus === 'adopted'}
                        onClick={() => applyStatus('adopted')}
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
                            from={resolutions.from}
                            to={resolutions.to}
                            total={resolutions.total}
                            links={resolutions.links}
                            label={t('legislation.resolutions')}
                        />
                    }
                >
                    <Register flush caption={t('legislation.resolutions')}>
                        <RegisterHead>
                            <RegisterHeadCell>{t('legislation.number')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('legislation.title')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('legislation.status')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('legislation.adopted_on')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">{t('legislation.year')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">
                                <span className="sr-only">{t('register.row_actions')}</span>
                            </RegisterHeadCell>
                        </RegisterHead>
                        <RegisterBody>
                            {resolutions.data.length === 0 ? (
                                <RegisterEmpty colSpan={6}>
                                    <EmptyState
                                        bare
                                        icon={ScrollText}
                                        title={t('legislation.resolutions_empty')}
                                        description={t('legislation.resolutions_empty_hint')}
                                        action={
                                            can.create ? (
                                                <Button variant="plate" size="sm" asChild>
                                                    <Link href="/resolutions/create">{t('legislation.create_resolution')}</Link>
                                                </Button>
                                            ) : undefined
                                        }
                                    />
                                </RegisterEmpty>
                            ) : (
                                resolutions.data.map((resolution) => (
                                    <RegisterRow key={resolution.id}>
                                        <RegisterCell numeric nowrap>
                                            {resolution.resolution_number}
                                        </RegisterCell>
                                        <RegisterCellPrimary href={`/resolutions/${resolution.id}`}>
                                            {resolution.title}
                                        </RegisterCellPrimary>
                                        <RegisterCell nowrap>
                                            <StatusChip tone={toneForState(resolution.status)} size="sm">
                                                {statusLabel(t, resolution.status, resolution.status_label)}
                                            </StatusChip>
                                        </RegisterCell>
                                        <RegisterCellDate value={resolution.adopted_on} />
                                        <RegisterCell numeric nowrap align="right">
                                            {resolution.series_year}
                                        </RegisterCell>
                                        <RegisterCellActions>
                                            <RegisterOpenLink href={`/resolutions/${resolution.id}`}>
                                                {t('register.open')}
                                            </RegisterOpenLink>
                                            <LegislativeHistoryDrawer
                                                href={`/resolutions/${resolution.id}/history`}
                                                title={resolution.title}
                                                number={resolution.resolution_number}
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
