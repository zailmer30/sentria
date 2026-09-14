import { SubmitDocumentDialog } from '@/components/documents/SubmitDocumentDialog';
import { Button } from '@/components/ui/button';
import { ConfidentialityDot, ConfidentialityLegend, type Confidentiality } from '@/components/ui/confidentiality';
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
import { SummaryCard, SummaryGrid } from '@/components/ui/summary-card';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { CalendarClock, FilePlus, FileWarning, Files, Lock, SlidersHorizontal, Trash2 } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

/**
 * The canonical index: a strip of figures describing the filtered set, the
 * finding aid, then the register itself.
 *
 * The figures are counted over the whole result set rather than the page on
 * screen, and they move when the filter moves — otherwise a strip sitting above
 * twenty filtered rows would be reporting on a different question than the one
 * the reader just asked. Clicking a figure scopes the register to that subset.
 */

type DocumentRow = {
    id: string;
    slug: string;
    title: string;
    reference_number: string | null;
    document_type_label: string;
    status: string;
    status_label: string;
    confidentiality: string;
    version_count: number;
    processing_status: string | null;
    processing_status_label: string | null;
    submitted_at: string | null;
    author: string | null;
    committee: string | null;
    deleted_at?: string | null;
};

type Scope = 'matching' | 'awaiting' | 'restricted' | 'recent';

type Props = {
    documents: Paginated<DocumentRow>;
    summary: {
        matching: number;
        awaiting_action: number;
        restricted: number;
        recent: number;
    };
    filters: {
        type: string | null;
        status: string | null;
        committee: string | null;
        search: string | null;
        processing: string | null;
        trashed: boolean;
        scope: Scope | null;
    };
    documentTypes: { value: string; label: string; tag?: string; next_reference?: string }[];
    processingStatuses: { value: string; label: string }[];
    committees: { id: string; name: string }[];
    confidentialityLevels: { value: string; label: string }[];
    maxUploadSizeKb: number;
    acceptedFileTypes: string;
    openSubmit?: boolean;
    can?: { create?: boolean };
};

const ANY = '__any';

type VisitInput = {
    search?: string;
    type?: string;
    committee?: string;
    processing?: string;
    trashed?: boolean;
    scope?: Scope;
};

function processingDotClass(status: string | null): string {
    if (status === 'completed') {
        return 'bg-success';
    }

    if (status === 'failed') {
        return 'bg-critical';
    }

    if (status === 'processing' || status === 'pending') {
        return 'bg-warning';
    }

    return 'bg-ink-faint';
}

export default function DocumentsIndex({
    documents,
    summary,
    filters,
    documentTypes,
    processingStatuses,
    committees,
    confidentialityLevels,
    maxUploadSizeKb,
    acceptedFileTypes,
    openSubmit = false,
    can = {},
}: Props) {
    const { t } = useTranslations();
    const [search, setSearch] = useState(filters.search ?? '');
    const [type, setType] = useState(filters.type ?? ANY);
    const [committee, setCommittee] = useState(filters.committee ?? ANY);
    const [processing, setProcessing] = useState(filters.processing ?? ANY);
    const [trashed, setTrashed] = useState(filters.trashed);
    const [submitOpen, setSubmitOpen] = useState(openSubmit);
    const scope: Scope = filters.scope ?? 'matching';

    const confidentialityLabels = useMemo(
        () => Object.fromEntries(confidentialityLevels.map((level) => [level.value, level.label])),
        [confidentialityLevels],
    );

    function visit(next: VisitInput = {}) {
        const nextSearch = next.search ?? search;
        const nextType = next.type ?? type;
        const nextCommittee = next.committee ?? committee;
        const nextProcessing = next.processing ?? processing;
        const nextTrashed = next.trashed ?? trashed;
        const nextScope = next.scope ?? scope;

        router.get(
            '/documents',
            {
                search: nextSearch || undefined,
                type: nextType === ANY ? undefined : nextType,
                committee: nextCommittee === ANY ? undefined : nextCommittee,
                processing: nextProcessing === ANY ? undefined : nextProcessing,
                trashed: nextTrashed ? 1 : undefined,
                scope: nextScope === 'matching' ? undefined : nextScope,
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
        setCommittee(ANY);
        setProcessing(ANY);
        visit({ search: '', type: ANY, committee: ANY, processing: ANY });
    }

    function toggleTrashed() {
        const next = !trashed;
        setTrashed(next);
        visit({ trashed: next });
    }

    function restoreDocument(slug: string) {
        router.post(`/documents/${slug}/restore`, {}, { preserveScroll: true });
    }

    return (
        <AppLayout title={t('documents.title')}>
            <div className="flex flex-col gap-5">
                <IndexHeader
                    eyebrow={t('index.eyebrow.record')}
                    title={t('documents.title')}
                    description={t('documents.index_subtitle')}
                    actions={
                        <>
                            <Button type="button" variant={trashed ? 'plate' : 'secondary'} onClick={toggleTrashed}>
                                <Trash2 aria-hidden="true" strokeWidth={1.75} />
                                {trashed ? t('documents.hide_trashed') : t('documents.show_trashed')}
                            </Button>
                            {can.create ? (
                                <Button type="button" variant="plate" onClick={() => setSubmitOpen(true)}>
                                    <FilePlus aria-hidden="true" strokeWidth={1.75} />
                                    {t('documents.create')}
                                </Button>
                            ) : null}
                        </>
                    }
                />

                <SummaryGrid>
                    <SummaryCard
                        label={t('documents.stat_matching')}
                        value={summary.matching}
                        icon={Files}
                        pressed={scope === 'matching'}
                        onClick={() => visit({ scope: 'matching' })}
                    />
                    <SummaryCard
                        label={t('documents.stat_awaiting')}
                        value={summary.awaiting_action}
                        icon={FileWarning}
                        pressed={scope === 'awaiting'}
                        onClick={() => visit({ scope: 'awaiting' })}
                    />
                    <SummaryCard
                        label={t('documents.stat_restricted')}
                        value={summary.restricted}
                        icon={Lock}
                        pressed={scope === 'restricted'}
                        onClick={() => visit({ scope: 'restricted' })}
                    />
                    <SummaryCard
                        label={t('documents.stat_recent')}
                        value={summary.recent}
                        icon={CalendarClock}
                        pressed={scope === 'recent'}
                        onClick={() => visit({ scope: 'recent' })}
                    />
                </SummaryGrid>

                <RegisterFrame
                    toolbar={
                        <>
                            <ToggleGroup
                                type="single"
                                size="default"
                                value={scope === 'restricted' ? 'restricted' : 'active'}
                                aria-label={t('documents.index_tabs')}
                                onValueChange={(next) => {
                                    if (next === 'restricted') {
                                        visit({ scope: 'restricted' });
                                    } else if (next === 'active') {
                                        visit({ scope: 'matching' });
                                    }
                                }}
                            >
                                <ToggleGroupItem value="active">{t('documents.tab_active')}</ToggleGroupItem>
                                <ToggleGroupItem value="restricted">{t('documents.tab_restricted')}</ToggleGroupItem>
                            </ToggleGroup>
                            <ConfidentialityLegend
                                items={confidentialityLevels.map((level) => ({
                                    level: level.value as Confidentiality,
                                    label: level.label,
                                }))}
                            />
                        </>
                    }
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
                                    placeholder={t('documents.search_placeholder')}
                                    label={t('documents.search')}
                                />
                            </FilterCell>

                            <FilterCell className="min-w-40">
                                <Label htmlFor="type">{t('documents.type')}</Label>
                                <Select
                                    value={type}
                                    onValueChange={(value) => {
                                        setType(value);
                                        visit({ type: value, search });
                                    }}
                                >
                                    <SelectTrigger id="type">
                                        <SelectValue placeholder={t('documents.all_types')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ANY}>{t('documents.all_types')}</SelectItem>
                                        {documentTypes.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FilterCell>

                            <FilterCell className="min-w-40">
                                <Label htmlFor="committee">{t('documents.committee')}</Label>
                                <Select
                                    value={committee}
                                    onValueChange={(value) => {
                                        setCommittee(value);
                                        visit({ committee: value, search });
                                    }}
                                >
                                    <SelectTrigger id="committee">
                                        <SelectValue placeholder={t('documents.all_committees')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ANY}>{t('documents.all_committees')}</SelectItem>
                                        {committees.map((option) => (
                                            <SelectItem key={option.id} value={option.id}>
                                                {option.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FilterCell>

                            <FilterCell className="min-w-40">
                                <Label htmlFor="processing">{t('documents.processing_status')}</Label>
                                <Select
                                    value={processing}
                                    onValueChange={(value) => {
                                        setProcessing(value);
                                        visit({ processing: value, search });
                                    }}
                                >
                                    <SelectTrigger id="processing">
                                        <SelectValue placeholder={t('documents.all_processing')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ANY}>{t('documents.all_processing')}</SelectItem>
                                        {processingStatuses.map((option) => (
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
                            from={documents.from}
                            to={documents.to}
                            total={documents.total}
                            links={documents.links}
                            label={t('documents.title')}
                        />
                    }
                >
                    <Register flush caption={t('documents.title')}>
                        <RegisterHead>
                            <RegisterHeadCell tight />
                            <RegisterHeadCell>{t('documents.reference')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('documents.title_label')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('documents.type')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('documents.status')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('documents.processing_status')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('documents.submitted')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">{t('documents.versions')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">
                                <span className="sr-only">{t('register.row_actions')}</span>
                            </RegisterHeadCell>
                        </RegisterHead>
                        <RegisterBody>
                            {documents.data.length === 0 ? (
                                <RegisterEmpty colSpan={9}>
                                    <EmptyState bare icon={Files} title={t('documents.empty')} />
                                </RegisterEmpty>
                            ) : (
                                documents.data.map((doc) => {
                                    const meta = [doc.committee, doc.author].filter(Boolean).join(' · ');

                                    return (
                                        <RegisterRow key={doc.id}>
                                            <RegisterCell tight>
                                                <ConfidentialityDot
                                                    confidentiality={doc.confidentiality}
                                                    label={confidentialityLabels[doc.confidentiality] ?? doc.confidentiality}
                                                />
                                            </RegisterCell>
                                            <RegisterCell numeric nowrap className="text-ink-faint">
                                                {doc.reference_number ?? '—'}
                                            </RegisterCell>
                                            <RegisterCellPrimary href={`/documents/${doc.slug}`} secondary={meta || undefined}>
                                                {doc.title}
                                            </RegisterCellPrimary>
                                            <RegisterCell nowrap>{doc.document_type_label}</RegisterCell>
                                            <RegisterCell nowrap>
                                                <span className="inline-flex max-w-full items-center rounded-xs border border-line bg-canvas-sunk px-2 py-0.5 text-2xs font-medium text-ink-muted">
                                                    {doc.status_label}
                                                </span>
                                            </RegisterCell>
                                            <RegisterCell nowrap>
                                                <span className="inline-flex items-center gap-1.5">
                                                    <span
                                                        aria-hidden="true"
                                                        className={cn(
                                                            'size-1.5 shrink-0 rounded-full',
                                                            processingDotClass(doc.processing_status),
                                                        )}
                                                    />
                                                    {doc.processing_status_label ?? doc.processing_status ?? '—'}
                                                </span>
                                            </RegisterCell>
                                            <RegisterCellDate value={doc.submitted_at} />
                                            <RegisterCell align="right" numeric>
                                                {doc.version_count}
                                            </RegisterCell>
                                            <RegisterCellActions>
                                                {trashed || doc.deleted_at ? (
                                                    <Button
                                                        variant="secondary"
                                                        size="sm"
                                                        onClick={() => restoreDocument(doc.slug)}
                                                    >
                                                        {t('documents.restore')}
                                                    </Button>
                                                ) : (
                                                    <RegisterOpenLink href={`/documents/${doc.slug}`}>
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

            {can.create ? (
                <SubmitDocumentDialog
                    open={submitOpen}
                    onOpenChange={setSubmitOpen}
                    documentTypes={documentTypes}
                    confidentialityLevels={confidentialityLevels}
                    committees={committees}
                    maxUploadSizeKb={maxUploadSizeKb}
                    acceptedFileTypes={acceptedFileTypes}
                />
            ) : null}
        </AppLayout>
    );
}
