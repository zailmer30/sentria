import { AiContent } from '@/components/ai/AiContent';
import { Button } from '@/components/ui/button';
import { Notice } from '@/components/ui/notice';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelBody } from '@/components/ui/panel';
import { StatusChip } from '@/components/ui/status';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

type DocumentSummary = {
    slug: string;
    title: string;
    reference_number: string | null;
};

type ConsistencyFinding = {
    type: string;
    severity: string;
    message: string;
    location_hint: string | null;
};

type ConsistencyResult = {
    disclaimer: string;
    is_ai_assisted: boolean;
    findings: ConsistencyFinding[];
};

type Props = {
    document: DocumentSummary;
    can: {
        run: boolean;
    };
};

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function severityTone(severity: string): 'draft' | 'moving' | 'review' | 'live' | 'final' | 'closed' | 'blocked' {
    const key = severity.toLowerCase();

    if (key === 'critical' || key === 'high') {
        return 'blocked';
    }

    if (key === 'warning' || key === 'medium') {
        return 'review';
    }

    return 'moving';
}

export default function AiReview({ document: doc, can }: Props) {
    const { t } = useTranslations();
    const [result, setResult] = useState<ConsistencyResult | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [hasRun, setHasRun] = useState(false);

    async function runReview() {
        setLoading(true);
        setError(null);
        setHasRun(true);

        try {
            const response = await fetch(`/documents/${doc.slug}/consistency`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });

            if (!response.ok) {
                throw new Error(t('ai.review_error'));
            }

            setResult(await response.json());
        } catch (reviewError) {
            setError(reviewError instanceof Error ? reviewError.message : t('ai.review_error'));
        } finally {
            setLoading(false);
        }
    }

    return (
        <AppLayout title={t('ai.review_title')}>
            <div className="mx-auto max-w-4xl space-y-6">
                <PageHeader
                    title={t('ai.review_title')}
                    description={`${doc.reference_number ? `${doc.reference_number} · ` : ''}${doc.title}`}
                    actions={
                        <>
                            <Button variant="secondary" asChild>
                                <Link href={`/documents/${doc.slug}`}>{t('documents.back_to_document')}</Link>
                            </Button>
                            <Button variant="secondary" asChild>
                                <Link href="/ai">{t('ai.back_to_assistant')}</Link>
                            </Button>
                        </>
                    }
                />

                <AiContent>
                    <div className="space-y-4">
                        <Notice tone="info">{t('ai.review_disclaimer')}</Notice>

                        {loading ? <p className="text-sm text-ink-muted">{t('ai.review_running')}</p> : null}
                        {error ? <p className="text-sm text-critical">{error}</p> : null}

                        {result ? (
                            <>
                                <Notice tone="caution">{result.disclaimer}</Notice>

                                {result.findings.length === 0 ? (
                                    <p className="text-sm text-ink">{t('ai.review_no_findings')}</p>
                                ) : (
                                    <ul className="space-y-3">
                                        {result.findings.map((finding, index) => (
                                            <Panel key={`${finding.type}-${index}`}>
                                                <PanelBody className="text-sm">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <StatusChip tone={severityTone(finding.severity)} size="sm">
                                                            {t(`ai.review_severity_${finding.severity}`, {
                                                                defaultValue: finding.severity,
                                                            })}
                                                        </StatusChip>
                                                        <span className="text-ink-muted">
                                                            {t(`ai.review_type_${finding.type}`, { defaultValue: finding.type })}
                                                        </span>
                                                    </div>
                                                    <p className="mt-2 text-ink">{finding.message}</p>
                                                    {finding.location_hint ? (
                                                        <p className="mt-2 font-mono text-xs text-ink-muted">
                                                            {finding.location_hint}
                                                        </p>
                                                    ) : null}
                                                </PanelBody>
                                            </Panel>
                                        ))}
                                    </ul>
                                )}
                            </>
                        ) : null}

                        {can.run ? (
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                disabled={loading}
                                onClick={() => void runReview()}
                            >
                                {hasRun ? t('ai.review_rerun') : t('ai.review_run')}
                            </Button>
                        ) : null}
                    </div>
                </AiContent>
            </div>
        </AppLayout>
    );
}
