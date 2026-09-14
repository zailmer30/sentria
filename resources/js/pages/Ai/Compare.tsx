import { AiContent } from '@/components/ai/AiContent';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { SimpleSelect } from '@/components/ui/select';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelBody } from '@/components/ui/panel';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type VersionOption = {
    id: string;
    version_number: number;
};

type DocumentOption = {
    slug: string;
    title: string;
    reference_number: string | null;
    versions: VersionOption[];
};

type ComparisonSide = {
    document: { slug: string; title: string; reference_number: string | null };
    version: { id: string; version_number: number };
};

type StructuredComparison = {
    hunks: Array<{ type: string; lines: string[] }>;
    changed_sections: Array<{
        change: string;
        section_number: string;
        before: string | null;
        after: string | null;
    }>;
    amount_changes: Array<{ change: string; type: string; value: string }>;
    date_changes: Array<{ change: string; type: string; value: string }>;
    penalty_changes: Array<{ change: string; type: string; line: string }>;
    definition_changes: Array<{ change: string; type: string; line: string }>;
};

type Props = {
    documents: DocumentOption[];
    selected: {
        left_slug: string | null;
        right_slug: string | null;
        left_version_id: string | null;
        right_version_id: string | null;
    };
    comparison?: {
        left: ComparisonSide;
        right: ComparisonSide;
        result: StructuredComparison;
    };
};

export default function AiCompare({ documents, selected, comparison }: Props) {
    const { t } = useTranslations();
    const [leftSlug, setLeftSlug] = useState(selected.left_slug ?? '');
    const [rightSlug, setRightSlug] = useState(selected.right_slug ?? '');
    const [leftVersionId, setLeftVersionId] = useState(selected.left_version_id ?? '');
    const [rightVersionId, setRightVersionId] = useState(selected.right_version_id ?? '');

    const leftDocument = documents.find((document) => document.slug === leftSlug);
    const rightDocument = documents.find((document) => document.slug === rightSlug);

    function submitCompare(event: FormEvent) {
        event.preventDefault();

        if (!leftSlug || !rightSlug) {
            return;
        }

        router.get('/ai/compare/result', {
            a: leftSlug,
            b: rightSlug,
            a_version: leftVersionId || undefined,
            b_version: rightVersionId || undefined,
        });
    }

    return (
        <AppLayout title={t('ai.compare_title')}>
            <div className="mx-auto max-w-5xl space-y-8">
                <PageHeader
                    title={t('ai.compare_title')}
                    description={t('ai.compare_subtitle')}
                    actions={
                        <Button variant="secondary" asChild>
                            <Link href="/ai">{t('ai.back_to_assistant')}</Link>
                        </Button>
                    }
                />

                <form onSubmit={submitCompare}>
                    <Panel>
                        <PanelBody className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-3">
                                <Field id="compare-left" label={t('ai.compare_left')}>
                                    <SimpleSelect
                                        id="compare-left"
                                        value={leftSlug}
                                        onValueChange={(value) => {
                                            setLeftSlug(value);
                                            setLeftVersionId('');
                                        }}
                                        noneLabel={t('ai.compare_select_document')}
                                        items={documents.map((document) => ({
                                            value: document.slug,
                                            label: `${document.reference_number ? `${document.reference_number} · ` : ''}${document.title}`,
                                        }))}
                                    />
                                </Field>
                                {leftDocument && leftDocument.versions.length > 0 ? (
                                    <SimpleSelect
                                        value={leftVersionId}
                                        onValueChange={setLeftVersionId}
                                        aria-label={t('ai.compare_left_version')}
                                        noneLabel={t('ai.compare_current_version')}
                                        items={leftDocument.versions.map((version) => ({
                                            value: version.id,
                                            label: `v${version.version_number}`,
                                        }))}
                                    />
                                ) : null}
                            </div>

                            <div className="space-y-3">
                                <Field id="compare-right" label={t('ai.compare_right')}>
                                    <SimpleSelect
                                        id="compare-right"
                                        value={rightSlug}
                                        onValueChange={(value) => {
                                            setRightSlug(value);
                                            setRightVersionId('');
                                        }}
                                        noneLabel={t('ai.compare_select_document')}
                                        items={documents.map((document) => ({
                                            value: document.slug,
                                            label: `${document.reference_number ? `${document.reference_number} · ` : ''}${document.title}`,
                                        }))}
                                    />
                                </Field>
                                {rightDocument && rightDocument.versions.length > 0 ? (
                                    <SimpleSelect
                                        value={rightVersionId}
                                        onValueChange={setRightVersionId}
                                        aria-label={t('ai.compare_right_version')}
                                        noneLabel={t('ai.compare_current_version')}
                                        items={rightDocument.versions.map((version) => ({
                                            value: version.id,
                                            label: `v${version.version_number}`,
                                        }))}
                                    />
                                ) : null}
                            </div>

                            <div className="md:col-span-2">
                                <Button type="submit" variant="primary">
                                    {t('ai.compare_run')}
                                </Button>
                            </div>
                        </PanelBody>
                    </Panel>
                </form>

                {comparison ? (
                    <AiContent>
                        <div className="space-y-6">
                            <p className="text-sm text-ink-muted">
                                {comparison.left.document.title} (v{comparison.left.version.version_number}) →{' '}
                                {comparison.right.document.title} (v{comparison.right.version.version_number})
                            </p>

                            {comparison.result.changed_sections.length > 0 ? (
                                <section>
                                    <h3 className="text-lg font-semibold text-ink">{t('ai.compare_sections')}</h3>
                                    <ul className="mt-3 space-y-3">
                                        {comparison.result.changed_sections.map((section) => (
                                            <li
                                                key={`${section.change}-${section.section_number}`}
                                                className="rounded-[var(--radius-sm)] border border-line p-3 text-sm"
                                            >
                                                <p className="font-medium text-ink">
                                                    {t(`ai.compare_change_${section.change}`)} · Section {section.section_number}
                                                </p>
                                                {section.before ? <p className="mt-2 text-ink-muted">{section.before}</p> : null}
                                                {section.after ? <p className="mt-2 text-ink">{section.after}</p> : null}
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            ) : null}

                            {[...comparison.result.amount_changes, ...comparison.result.date_changes].length > 0 ? (
                                <section>
                                    <h3 className="text-lg font-semibold text-ink">{t('ai.compare_amounts_dates')}</h3>
                                    <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-ink">
                                        {[...comparison.result.amount_changes, ...comparison.result.date_changes].map(
                                            (item, index) => (
                                                <li key={`${item.change}-${item.value}-${index}`}>
                                                    {t(`ai.compare_change_${item.change}`)}: {item.value}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </section>
                            ) : null}

                            <section>
                                <h3 className="text-lg font-semibold text-ink">{t('ai.compare_line_diff')}</h3>
                                <Panel className="mt-3 overflow-hidden">
                                    <PanelBody className="p-0 font-mono text-sm">
                                        {comparison.result.hunks.length === 0 ? (
                                            <p className="px-4 py-8 text-center text-ink-muted">{t('documents.no_diff')}</p>
                                        ) : (
                                            comparison.result.hunks.map((hunk, index) => (
                                                <pre
                                                    key={index}
                                                    className={
                                                        hunk.type === 'added'
                                                            ? 'diff-added border-b border-line px-4 py-2 whitespace-pre-wrap'
                                                            : hunk.type === 'removed'
                                                              ? 'diff-removed border-b border-line px-4 py-2 whitespace-pre-wrap'
                                                              : 'border-b border-line px-4 py-2 whitespace-pre-wrap text-ink-muted'
                                                    }
                                                >
                                                    {hunk.lines.join('\n')}
                                                </pre>
                                            ))
                                        )}
                                    </PanelBody>
                                </Panel>
                            </section>
                        </div>
                    </AiContent>
                ) : null}
            </div>
        </AppLayout>
    );
}
