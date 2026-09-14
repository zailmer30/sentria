import { PortalContainer } from '@/components/portal/PortalContainer';
import { PortalRecordCard, type PortalPublicationCard } from '@/components/portal/PortalRecordCard';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue, SELECT_NONE } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import PortalLayout from '@/layouts/PortalLayout';
import { useTranslations } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { Settings2 } from 'lucide-react';
import { FormEvent } from 'react';

type Filters = {
    keyword?: string | null;
    type?: string | null;
    year?: number | null;
    author?: string | null;
    committee?: string | null;
    status?: string | null;
    date_from?: string | null;
    date_to?: string | null;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total: number;
};

type Props = {
    results: Paginated<PortalPublicationCard>;
    filters: Filters;
    committees: string[];
    years: number[];
};

const TYPES = [
    { value: 'ordinance', key: 'portal.document_type.ordinance' },
    { value: 'resolution', key: 'portal.document_type.resolution' },
    { value: 'minutes', key: 'portal.document_type.minutes' },
] as const;

export default function PortalSearch({ results, filters, committees, years }: Props) {
    const { t } = useTranslations();
    const hasFilters = Boolean(
        filters.keyword || filters.type || filters.year || filters.author || filters.committee,
    );

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const payload = Object.fromEntries(
            [...form.entries()].filter(([, value]) => String(value).length > 0),
        );
        router.get('/portal/search', payload);
    }

    function clearFilters() {
        router.get('/portal/search');
    }

    return (
        <PortalLayout title={t('portal.search_title')} description={t('portal.search_description')}>
            <PortalContainer className="flex flex-col gap-10 py-10 lg:flex-row">
                <aside className="w-full shrink-0 lg:sticky lg:top-24 lg:w-72 lg:self-start">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <h1 className="flex items-center gap-2 font-display text-base font-semibold">
                            <Settings2 aria-hidden="true" className="size-4 text-brand" />
                            {t('portal.refine')}
                        </h1>
                        {hasFilters ? (
                            <Button type="button" variant="ghost" size="sm" onClick={clearFilters}>
                                {t('portal.clear_filters')}
                            </Button>
                        ) : null}
                    </div>

                    <form
                        onSubmit={submit}
                        className="rounded-[var(--radius-lg)] border border-line bg-surface p-5 shadow-plate"
                    >
                        <Label htmlFor="keyword" className="text-eyebrow text-ink-muted">
                            {t('portal.filter.keyword')}
                        </Label>
                        <Input
                            id="keyword"
                            name="keyword"
                            defaultValue={filters.keyword ?? ''}
                            className="mt-3"
                        />

                        <Separator className="my-5" />

                        <p className="text-eyebrow text-ink-muted">{t('portal.filter.type')}</p>
                        <input type="hidden" name="type" defaultValue={filters.type ?? ''} id="type-field" />
                        <ul className="mt-3 space-y-2">
                            {TYPES.map((type) => {
                                const selected = filters.type === type.value;

                                return (
                                    <li key={type.value}>
                                        <Button
                                            type="button"
                                            variant={selected ? 'secondary' : 'ghost'}
                                            className="h-auto w-full justify-start px-2.5 py-1.5 font-normal"
                                            onClick={() => {
                                                const field = document.getElementById('type-field') as HTMLInputElement | null;
                                                if (field) {
                                                    field.value = selected ? '' : type.value;
                                                }
                                                const form = field?.form;
                                                if (form) {
                                                    form.requestSubmit();
                                                }
                                            }}
                                        >
                                            {t(type.key)}
                                        </Button>
                                    </li>
                                );
                            })}
                        </ul>

                        <Separator className="my-5" />

                        <Label htmlFor="year" className="text-eyebrow text-ink-muted">
                            {t('portal.filter.year')}
                        </Label>
                        <Select
                            name="year"
                            defaultValue={filters.year ? String(filters.year) : SELECT_NONE}
                            onValueChange={(value) => {
                                const field = document.getElementById('year-field') as HTMLInputElement | null;
                                if (field) {
                                    field.value = value === SELECT_NONE ? '' : value;
                                }
                            }}
                        >
                            <SelectTrigger id="year" className="mt-3">
                                <SelectValue placeholder={t('portal.filter.year')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={SELECT_NONE}>{t('portal.filter.all_years')}</SelectItem>
                                {years.map((year) => (
                                    <SelectItem key={year} value={String(year)}>
                                        {year}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <input type="hidden" id="year-field" name="year" defaultValue={filters.year ?? ''} />

                        <Separator className="my-5" />

                        <Label htmlFor="committee" className="text-eyebrow text-ink-muted">
                            {t('portal.filter.committee')}
                        </Label>
                        <Select
                            defaultValue={filters.committee ?? SELECT_NONE}
                            onValueChange={(value) => {
                                const field = document.getElementById('committee-field') as HTMLInputElement | null;
                                if (field) {
                                    field.value = value === SELECT_NONE ? '' : value;
                                }
                            }}
                        >
                            <SelectTrigger id="committee" className="mt-3">
                                <SelectValue placeholder={t('portal.filter.all_committees')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={SELECT_NONE}>{t('portal.filter.all_committees')}</SelectItem>
                                {committees.map((name) => (
                                    <SelectItem key={name} value={name}>
                                        {name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            id="committee-field"
                            name="committee"
                            defaultValue={filters.committee ?? ''}
                        />

                        <Button type="submit" className="mt-6 w-full">
                            {t('portal.search_cta')}
                        </Button>
                    </form>

                    <p className="mt-4 text-xs text-ink-subtle">{t('portal.certified_footnote')}</p>
                </aside>

                <section className="min-w-0 flex-1" aria-labelledby="results-heading">
                    <p id="results-heading" className="mb-5 text-sm text-ink-muted">
                        {t('portal.results_count', { count: results.total })}
                    </p>

                    {results.data.length === 0 ? (
                        <div className="rounded-[var(--radius-lg)] border border-dashed border-line bg-surface p-10 text-center">
                            <p className="font-display text-lg font-semibold">{t('portal.no_matching')}</p>
                            <Button type="button" className="mt-5" onClick={clearFilters}>
                                {t('portal.clear_filters')}
                            </Button>
                        </div>
                    ) : (
                        <ul className="space-y-4">
                            {results.data.map((item) => (
                                <li key={item.slug}>
                                    <PortalRecordCard item={item} />
                                </li>
                            ))}
                        </ul>
                    )}

                    <Pagination links={results.links} label={t('portal.pagination')} className="mt-6" />
                </section>
            </PortalContainer>
        </PortalLayout>
    );
}
