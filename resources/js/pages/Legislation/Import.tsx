import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { FileDrop } from '@/components/ui/file-drop';
import { Notice } from '@/components/ui/notice';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelBody, PanelFoot, PanelHead, PanelSection, PanelTitle } from '@/components/ui/panel';
import {
    Register,
    RegisterBody,
    RegisterCell,
    RegisterCellPrimary,
    RegisterEmpty,
    RegisterHead,
    RegisterHeadCell,
    RegisterRow,
} from '@/components/ui/register';
import { SummaryCard, SummaryGrid } from '@/components/ui/summary-card';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link, router, useForm } from '@inertiajs/react';
import { Check, FileArchive, FileSpreadsheet, FileWarning, Link2, Upload } from 'lucide-react';
import { FormEvent } from 'react';

type PreviewRow = {
    line?: number;
    number?: string;
    series_year?: number;
    title?: string;
    status?: string;
    filename?: string;
    reason?: string;
    existing_number?: string;
    entry?: string;
};

type Preview = {
    matched: PreviewRow[];
    unmatched_rows: PreviewRow[];
    unmatched_files: PreviewRow[];
    duplicates: PreviewRow[];
    invalid: PreviewRow[];
    counts: {
        matched: number;
        unmatched_rows: number;
        unmatched_files: number;
        duplicates: number;
        invalid: number;
    };
};

type Result = {
    created: number;
    skipped: number;
    failed: { number?: string; reason?: string; message?: string }[];
};

type Props = {
    kind: 'ordinance' | 'resolution';
    batch: {
        id: string;
        status: string;
        csv_filename: string;
        zip_filename: string;
        committed_at: string | null;
    } | null;
    preview: Preview | null;
    result: Result | null;
    maxCsvKb: number;
    maxZipKb: number;
    templateUrl: string;
    storeUrl: string;
    commitUrl?: string;
    indexUrl: string;
};

export default function LegislationImport({
    kind,
    batch,
    preview,
    result,
    maxCsvKb,
    maxZipKb,
    templateUrl,
    storeUrl,
    commitUrl,
    indexUrl,
}: Props) {
    const { t } = useTranslations();
    const isOrdinance = kind === 'ordinance';
    const committed = batch?.status === 'committed';
    const csvMaxMb = kbToMb(maxCsvKb);
    const zipMaxMb = kbToMb(maxZipKb);

    const form = useForm<{ csv: File | null; zip: File | null }>({
        csv: null,
        zip: null,
    });

    function takeCsv(file: File | null) {
        form.setData('csv', file);

        if (file && file.size > maxCsvKb * 1024) {
            form.setError('csv', t('legislation.import_file_too_large', { max: csvMaxMb }));

            return;
        }

        form.clearErrors('csv');
    }

    function takeZip(file: File | null) {
        form.setData('zip', file);

        if (file && file.size > maxZipKb * 1024) {
            form.setError('zip', t('legislation.import_file_too_large', { max: zipMaxMb }));

            return;
        }

        form.clearErrors('zip');
    }

    function submitPreview(event: FormEvent) {
        event.preventDefault();

        if (form.data.csv && form.data.csv.size > maxCsvKb * 1024) {
            form.setError('csv', t('legislation.import_file_too_large', { max: csvMaxMb }));

            return;
        }

        if (form.data.zip && form.data.zip.size > maxZipKb * 1024) {
            form.setError('zip', t('legislation.import_file_too_large', { max: zipMaxMb }));

            return;
        }

        if (form.errors.csv || form.errors.zip) {
            return;
        }

        form.post(storeUrl, { forceFormData: true });
    }

    function submitCommit() {
        if (!commitUrl || committed) {
            return;
        }

        router.post(commitUrl);
    }

    return (
        <AppLayout title={t(isOrdinance ? 'legislation.import_ordinances' : 'legislation.import_resolutions')}>
            <div className="mx-auto flex max-w-5xl flex-col gap-5">
                <PageHeader
                    title={t(isOrdinance ? 'legislation.import_ordinances' : 'legislation.import_resolutions')}
                    description={t(
                        isOrdinance ? 'legislation.import_ordinances_subtitle' : 'legislation.import_resolutions_subtitle',
                    )}
                    actions={
                        <Button variant="ghost" asChild>
                            <Link href={indexUrl}>{t('legislation.cancel')}</Link>
                        </Button>
                    }
                />

                <Notice tone="info">{t('legislation.import_notice')}</Notice>

                {committed && result ? (
                    <Notice tone="info">
                        {t('legislation.import_result', {
                            created: result.created,
                            skipped: result.skipped,
                            failed: result.failed.length,
                        })}
                    </Notice>
                ) : null}

                {batch === null ? (
                    <Panel>
                        <form onSubmit={submitPreview}>
                            <PanelSection className="space-y-4">
                                <div>
                                    <h2 className="text-sm font-semibold text-ink">{t('legislation.import_files')}</h2>
                                    <p className="mt-1 text-sm text-ink-muted">{t('legislation.import_files_hint')}</p>
                                </div>

                                <Field
                                    id="csv"
                                    label={t('legislation.import_csv')}
                                    hint={t('legislation.import_csv_hint', { max: csvMaxMb })}
                                    error={form.errors.csv}
                                    required
                                >
                                    <FileDrop
                                        id="csv"
                                        file={form.data.csv}
                                        onFileChange={takeCsv}
                                        accept=".csv,text/csv"
                                        invalid={Boolean(form.errors.csv)}
                                        dropLabel={t('legislation.import_csv_drop')}
                                        browseLabel={t('legislation.import_browse')}
                                        replaceLabel={t('legislation.import_replace')}
                                        removeLabel={t('legislation.import_remove')}
                                        emptyHint={t('legislation.import_csv_empty')}
                                        icon={FileSpreadsheet}
                                    />
                                </Field>

                                <Field
                                    id="zip"
                                    label={t('legislation.import_zip')}
                                    hint={t('legislation.import_zip_hint', { max: zipMaxMb })}
                                    error={form.errors.zip}
                                    required
                                >
                                    <FileDrop
                                        id="zip"
                                        file={form.data.zip}
                                        onFileChange={takeZip}
                                        accept=".zip,application/zip"
                                        invalid={Boolean(form.errors.zip)}
                                        dropLabel={t('legislation.import_zip_drop')}
                                        browseLabel={t('legislation.import_browse')}
                                        replaceLabel={t('legislation.import_replace')}
                                        removeLabel={t('legislation.import_remove')}
                                        emptyHint={t('legislation.import_zip_empty')}
                                        icon={FileArchive}
                                    />
                                </Field>
                            </PanelSection>

                            <PanelFoot>
                                <Button variant="ghost" asChild>
                                    <a href={templateUrl}>{t('legislation.import_download_template')}</a>
                                </Button>
                                <div className="ml-auto flex gap-2">
                                    <Button variant="ghost" asChild>
                                        <Link href={indexUrl}>{t('legislation.cancel')}</Link>
                                    </Button>
                                    <Button variant="primary" type="submit" disabled={form.processing}>
                                        <Upload aria-hidden="true" strokeWidth={1.75} />
                                        {form.processing ? t('legislation.import_previewing') : t('legislation.import_preview')}
                                    </Button>
                                </div>
                            </PanelFoot>
                        </form>
                    </Panel>
                ) : preview ? (
                    <>
                        <SummaryGrid>
                            <SummaryCard label={t('legislation.import_stat_matched')} value={preview.counts.matched} icon={Link2} />
                            <SummaryCard
                                label={t('legislation.import_stat_unmatched_rows')}
                                value={preview.counts.unmatched_rows}
                                icon={FileWarning}
                            />
                            <SummaryCard
                                label={t('legislation.import_stat_unmatched_files')}
                                value={preview.counts.unmatched_files}
                                icon={FileArchive}
                            />
                            <SummaryCard
                                label={t('legislation.import_stat_duplicates')}
                                value={preview.counts.duplicates}
                                icon={FileSpreadsheet}
                            />
                        </SummaryGrid>

                        <PreviewTable
                            title={t('legislation.import_matched')}
                            hint={t('legislation.import_matched_hint')}
                            caption={t('legislation.import_matched')}
                            rows={preview.matched}
                            empty={t('legislation.import_matched_empty')}
                            showFile
                        />

                        <PreviewTable
                            title={t('legislation.import_unmatched_rows')}
                            hint={t('legislation.import_unmatched_rows_hint')}
                            caption={t('legislation.import_unmatched_rows')}
                            rows={preview.unmatched_rows}
                            empty={t('legislation.import_section_empty')}
                            showReason
                        />

                        <PreviewTable
                            title={t('legislation.import_unmatched_files')}
                            hint={t('legislation.import_unmatched_files_hint')}
                            caption={t('legislation.import_unmatched_files')}
                            rows={preview.unmatched_files}
                            empty={t('legislation.import_section_empty')}
                            showFile
                            showReason
                            filenameAsPrimary
                        />

                        <PreviewTable
                            title={t('legislation.import_duplicates')}
                            hint={t('legislation.import_duplicates_hint')}
                            caption={t('legislation.import_duplicates')}
                            rows={preview.duplicates}
                            empty={t('legislation.import_section_empty')}
                            showFile
                        />

                        <PreviewTable
                            title={t('legislation.import_invalid')}
                            hint={t('legislation.import_invalid_hint')}
                            caption={t('legislation.import_invalid')}
                            rows={preview.invalid}
                            empty={t('legislation.import_section_empty')}
                            showReason
                        />

                        <Panel>
                            <PanelFoot>
                                <Button variant="ghost" asChild>
                                    <Link href={isOrdinance ? '/ordinances/import' : '/resolutions/import'}>
                                        {t('legislation.import_another')}
                                    </Link>
                                </Button>
                                <div className="ml-auto flex gap-2">
                                    <Button variant="ghost" asChild>
                                        <Link href={indexUrl}>{t('legislation.back')}</Link>
                                    </Button>
                                    {committed ? (
                                        <Button variant="primary" asChild>
                                            <Link href={indexUrl}>
                                                <Check aria-hidden="true" strokeWidth={1.75} />
                                                {t('legislation.import_view_register')}
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            variant="primary"
                                            type="button"
                                            onClick={submitCommit}
                                            disabled={preview.counts.matched === 0}
                                        >
                                            {t('legislation.import_commit', { count: preview.counts.matched })}
                                        </Button>
                                    )}
                                </div>
                            </PanelFoot>
                        </Panel>
                    </>
                ) : null}
            </div>
        </AppLayout>
    );
}

function kbToMb(kb: number): number {
    return Math.max(1, Math.round(kb / 1024));
}

function PreviewTable({
    title,
    hint,
    caption,
    rows,
    empty,
    showFile = false,
    showReason = false,
    filenameAsPrimary = false,
}: {
    title: string;
    hint: string;
    caption: string;
    rows: PreviewRow[];
    empty: string;
    showFile?: boolean;
    showReason?: boolean;
    filenameAsPrimary?: boolean;
}) {
    const { t } = useTranslations();

    return (
        <Panel>
            <PanelHead sunk>
                <PanelTitle>{title}</PanelTitle>
            </PanelHead>
            <PanelBody className="px-0 py-0">
                <p className="border-b border-line px-5 py-3 text-sm text-ink-muted">{hint}</p>
                <Register caption={caption} flush minWidth="40rem">
                    <RegisterHead>
                        <RegisterHeadCell>{t('legislation.number')}</RegisterHeadCell>
                        <RegisterHeadCell>{t('legislation.title')}</RegisterHeadCell>
                        {showFile ? <RegisterHeadCell>{t('legislation.import_file')}</RegisterHeadCell> : null}
                        {showReason ? <RegisterHeadCell>{t('legislation.import_reason')}</RegisterHeadCell> : null}
                    </RegisterHead>
                    <RegisterBody>
                        {rows.length === 0 ? (
                            <RegisterEmpty colSpan={2 + (showFile ? 1 : 0) + (showReason ? 1 : 0)}>{empty}</RegisterEmpty>
                        ) : (
                            rows.map((row, index) => (
                                <RegisterRow key={`${row.line ?? row.filename ?? 'row'}-${index}`}>
                                    <RegisterCell className="font-mono text-xs">
                                        {filenameAsPrimary ? (row.filename ?? '—') : (row.number ?? '—')}
                                    </RegisterCell>
                                    <RegisterCell>
                                        <RegisterCellPrimary>
                                            {filenameAsPrimary ? (row.number ?? row.entry ?? '—') : (row.title ?? '—')}
                                        </RegisterCellPrimary>
                                    </RegisterCell>
                                    {showFile && !filenameAsPrimary ? (
                                        <RegisterCell className="text-xs text-ink-muted">{row.filename ?? '—'}</RegisterCell>
                                    ) : null}
                                    {showReason ? (
                                        <RegisterCell className="text-xs text-ink-muted">
                                            {row.reason ? t(`legislation.import_reason_${row.reason}`) : '—'}
                                        </RegisterCell>
                                    ) : null}
                                </RegisterRow>
                            ))
                        )}
                    </RegisterBody>
                </Register>
            </PanelBody>
        </Panel>
    );
}
