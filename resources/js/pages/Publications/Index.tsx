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
import { FilePlus, Globe, Newspaper, PenLine, SearchCheck, SlidersHorizontal } from 'lucide-react';
import { FormEvent, useState } from 'react';

/**
 * The publication register follows the document index: a strip of figures over
 * the filtered set, a finding aid, then the rows. Identity left, state middle,
 * dates right, actions last.
 */

type PublicationRow = {
    id: string;
    public_slug: string;
    title: string;
    status: string;
    status_label: string;
    published_at: string | null;
    updated_at: string | null;
    visibility: string;
    visibility_label: string;
    document: {
        slug: string;
        title: string;
        reference_number: string | null;
        document_type_label: string;
    } | null;
};

type Scope = 'matching' | 'drafting' | 'review' | 'published';

type Props = {
    publications: Paginated<PublicationRow>;
    summary: {
        matching: number;
        drafting: number;
        review: number;
        published: number;
    };
    filters: {
        search: string | null;
        status: string | null;
        scope: Scope;
    };
    statuses: { value: string; label: string }[];
    can: { create: boolean };
};

const ANY = '__any';

type VisitInput = {
    search?: string;
    status?: string;
    scope?: Scope;
};

export default function PublicationsIndex({ publications, summary, filters, statuses, can }: Props) {
    const { t } = useTranslations();
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? ANY);
    const scope: Scope = filters.scope ?? 'matching';

    function visit(next: VisitInput = {}) {
        const nextSearch = next.search ?? search;
        const nextStatus = next.status ?? status;
        const nextScope = next.scope ?? scope;

        router.get(
            '/publications',
            {
                search: nextSearch || undefined,
                status: nextStatus === ANY ? undefined : nextStatus,
                scope: nextScope === 'matching' || nextStatus !== ANY ? undefined : nextScope,
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
        visit({ search: '', status: ANY, scope: 'matching' });
    }

    function applyScope(next: Scope) {
        setStatus(ANY);
        visit({ status: ANY, scope: next, search });
    }

    return (
        <AppLayout title={t('publications.title')}>
            <div className="flex flex-col gap-5">
                <IndexHeader
                    eyebrow={t('index.eyebrow.record')}
                    title={t('publications.title')}
                    description={t('publications.index_intro')}
                    actions={
                        can.create ? (
                            <Button variant="plate" asChild>
                                <Link href="/publications/create">
                                    <FilePlus aria-hidden="true" strokeWidth={1.75} />
                                    {t('publications.start')}
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                <SummaryGrid>
                    <SummaryCard
                        label={t('publications.stat_matching')}
                        value={summary.matching}
                        icon={Newspaper}
                        pressed={scope === 'matching' && status === ANY}
                        onClick={() => applyScope('matching')}
                    />
                    <SummaryCard
                        label={t('publications.stat_drafting')}
                        value={summary.drafting}
                        icon={PenLine}
                        pressed={scope === 'drafting' && status === ANY}
                        onClick={() => applyScope('drafting')}
                    />
                    <SummaryCard
                        label={t('publications.stat_review')}
                        value={summary.review}
                        icon={SearchCheck}
                        pressed={scope === 'review' && status === ANY}
                        onClick={() => applyScope('review')}
                    />
                    <SummaryCard
                        label={t('publications.stat_published')}
                        value={summary.published}
                        icon={Globe}
                        pressed={scope === 'published' && status === ANY}
                        onClick={() => applyScope('published')}
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
                                    placeholder={t('publications.search_placeholder')}
                                    label={t('publications.search_label')}
                                />
                            </FilterCell>
                            <FilterCell className="min-w-44">
                                <Label htmlFor="status">{t('publications.status')}</Label>
                                <Select
                                    value={status}
                                    onValueChange={(value) => {
                                        setStatus(value);
                                        visit({ status: value, search, scope: 'matching' });
                                    }}
                                >
                                    <SelectTrigger id="status">
                                        <SelectValue placeholder={t('publications.all_statuses')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ANY}>{t('publications.all_statuses')}</SelectItem>
                                        {statuses.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
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
                            from={publications.from}
                            to={publications.to}
                            total={publications.total}
                            links={publications.links}
                            label={t('publications.title')}
                        />
                    }
                >
                    <Register flush caption={t('publications.title')}>
                        <RegisterHead>
                            <RegisterHeadCell>{t('publications.reference')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('publications.title_label')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('publications.status')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('publications.visibility')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('publications.updated')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">
                                <span className="sr-only">{t('register.row_actions')}</span>
                            </RegisterHeadCell>
                        </RegisterHead>
                        <RegisterBody>
                            {publications.data.length === 0 ? (
                                <RegisterEmpty colSpan={6}>
                                    <EmptyState
                                        bare
                                        icon={Newspaper}
                                        title={t('publications.empty')}
                                        description={can.create ? t('publications.create_hint') : undefined}
                                        action={
                                            can.create ? (
                                                <Button variant="plate" size="sm" asChild>
                                                    <Link href="/publications/create">{t('publications.start')}</Link>
                                                </Button>
                                            ) : undefined
                                        }
                                    />
                                </RegisterEmpty>
                            ) : (
                                publications.data.map((item) => {
                                    const reference =
                                        item.document?.reference_number ?? item.public_slug;
                                    const meta = item.document?.document_type_label;

                                    return (
                                        <RegisterRow key={item.id}>
                                            <RegisterCell numeric nowrap className="text-ink-faint">
                                                {reference}
                                            </RegisterCell>
                                            <RegisterCellPrimary
                                                href={`/publications/${item.public_slug}`}
                                                secondary={meta || undefined}
                                            >
                                                {item.title}
                                            </RegisterCellPrimary>
                                            <RegisterCell nowrap>
                                                <StatusChip tone={toneForState(item.status)} size="sm">
                                                    {item.status_label}
                                                </StatusChip>
                                            </RegisterCell>
                                            <RegisterCell nowrap>
                                                {item.visibility_label}
                                            </RegisterCell>
                                            <RegisterCellDate value={item.updated_at} />
                                            <RegisterCellActions>
                                                <RegisterOpenLink href={`/publications/${item.public_slug}`}>
                                                    {t('register.open')}
                                                </RegisterOpenLink>
                                                {item.document ? (
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={`/documents/${item.document.slug}`}>
                                                            {t('publications.source')}
                                                        </Link>
                                                    </Button>
                                                ) : null}
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
