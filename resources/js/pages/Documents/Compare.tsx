import { AiContent } from '@/components/ai/AiContent';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelBody } from '@/components/ui/panel';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';

type Version = {
    id: string;
    version_number: number;
    original_filename: string;
};

type Hunk = {
    type: 'added' | 'removed' | 'unchanged' | 'changed';
    lines: string[];
};

type StructuredComparison = {
    hunks: Hunk[];
    changed_sections: Array<{
        change: string;
        section_number: string;
        before: string | null;
        after: string | null;
    }>;
    amount_changes: Array<{ change: string; type: string; value: string }>;
    date_changes: Array<{ change: string; type: string; value: string }>;
};

type Props = {
    document: { slug: string; title: string };
    from: Version;
    to: Version;
    comparison: StructuredComparison;
};

export default function DocumentsCompare({ document, from, to, comparison }: Props) {
    const { t } = useTranslations();

    return (
        <AppLayout title={t('documents.compare_title')}>
            <div className="mx-auto max-w-4xl space-y-6">
                <PageHeader
                    title={t('documents.compare_title')}
                    description={`${document.title} — v${from.version_number} → v${to.version_number}`}
                    actions={
                        <Button variant="secondary" asChild>
                            <Link href={`/documents/${document.slug}`}>{t('documents.back_to_document')}</Link>
                        </Button>
                    }
                />

                <Panel>
                    <PanelBody className="p-0">
                        <AiContent showBadge={false}>
                            {comparison.changed_sections.length > 0 ? (
                                <section className="mb-6">
                                    <h3 className="text-sm font-semibold text-ink">{t('ai.compare_sections')}</h3>
                                    <ul className="mt-3 space-y-3">
                                        {comparison.changed_sections.map((section) => (
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

                            {[...comparison.amount_changes, ...comparison.date_changes].length > 0 ? (
                                <section className="mb-6">
                                    <h3 className="text-sm font-semibold text-ink">{t('ai.compare_amounts_dates')}</h3>
                                    <ul className="mt-3 list-disc space-y-1 pl-5 text-sm">
                                        {[...comparison.amount_changes, ...comparison.date_changes].map((item, index) => (
                                            <li key={`${item.change}-${item.value}-${index}`}>
                                                {t(`ai.compare_change_${item.change}`)}: {item.value}
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            ) : null}

                            <section>
                                <h3 className="text-sm font-semibold text-ink">{t('ai.compare_line_diff')}</h3>
                                <div className="mt-3 overflow-hidden rounded-[var(--radius-sm)] border border-line bg-surface font-mono text-sm">
                                    {comparison.hunks.length === 0 ? (
                                        <p className="px-4 py-8 text-center text-ink-muted">{t('documents.no_diff')}</p>
                                    ) : (
                                        comparison.hunks.map((hunk, index) => (
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
                                </div>
                            </section>
                        </AiContent>
                    </PanelBody>
                </Panel>
            </div>
        </AppLayout>
    );
}
