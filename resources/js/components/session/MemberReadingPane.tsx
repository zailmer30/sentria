import { DocumentPdfViewer } from '@/components/documents/DocumentPdfViewer';
import { CommitteeReportBody } from '@/components/documents/CommitteeReportBody';
import { Button } from '@/components/ui/button';
import { Notice } from '@/components/ui/notice';
import { Panel } from '@/components/ui/panel';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { withHonorific } from '@/lib/sessionFloor';
import { cn } from '@/lib/utils';
import { referredCommitteeNames, type ReadingPackItem } from '@/pages/Sessions/Floor/shared';
import { Link } from '@inertiajs/react';
import { FileText, FileWarning } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

type MemberReadingPaneProps = {
    item: ReadingPackItem | null;
    currentItemId: string | null;
    followingFloor: boolean;
    onFollowFloor: () => void;
    /** Extra controls on the navy plate, next to the full-text action. */
    headerActions?: ReactNode;
    className?: string;
};

function MeasureBody({ text }: { text: string }) {
    const blocks = text.trim().split(/\n{2,}/).filter(Boolean);

    if (blocks.length === 0) {
        return null;
    }

    return (
        <div className="space-y-4 px-5 py-5">
            {blocks.map((block, index) => {
                const first = block.split('\n')[0]?.trim() ?? '';
                const isSection = /^(section\s+\d+)/i.test(first);

                if (isSection) {
                    const lines = block.split('\n');
                    const rest = lines.slice(1).join('\n').trim();

                    return (
                        <div key={index}>
                            <h3 className="text-sm font-semibold text-ink">{first}</h3>
                            {rest ? (
                                <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-ink-muted">{rest}</p>
                            ) : null}
                        </div>
                    );
                }

                return (
                    <p key={index} className="whitespace-pre-wrap text-sm leading-6 text-ink-muted">
                        {block}
                    </p>
                );
            })}
        </div>
    );
}

export function MemberReadingPane({
    item,
    currentItemId,
    followingFloor,
    onFollowFloor,
    headerActions,
    className,
}: MemberReadingPaneProps) {
    const { t } = useTranslations();
    const { formatList } = useFormatters();
    const document = item?.document ?? null;
    const report = item?.committee_report ?? null;
    const canPreview = Boolean(document?.can_preview && document.preview_url && document.annotations_url);
    const [fullTextOpen, setFullTextOpen] = useState(false);
    const [annotations, setAnnotations] = useState<unknown[]>([]);
    const [annotationsReady, setAnnotationsReady] = useState(false);
    const [annotationsError, setAnnotationsError] = useState(false);

    useEffect(() => {
        setFullTextOpen(item?.title_only === true);
    }, [item?.id, item?.title_only]);

    useEffect(() => {
        if (!canPreview || !document?.annotations_url) {
            setAnnotations([]);
            setAnnotationsReady(true);
            setAnnotationsError(false);

            return;
        }

        let cancelled = false;

        setAnnotationsReady(false);
        setAnnotationsError(false);
        setAnnotations([]);

        void fetch(document.annotations_url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`annotations ${response.status}`);
                }

                return response.json() as Promise<{ payload?: unknown[] }>;
            })
            .then((data) => {
                if (!cancelled) {
                    setAnnotations(Array.isArray(data.payload) ? data.payload : []);
                    setAnnotationsReady(true);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setAnnotations([]);
                    setAnnotationsReady(true);
                    setAnnotationsError(true);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [canPreview, document?.annotations_url, document?.version_id]);

    const readingAhead = Boolean(item && currentItemId && item.id !== currentItemId && !followingFloor);
    const author = withHonorific(document?.author);
    const body = item?.description?.trim() || document?.abstract?.trim() || '';

    const meta = [
        document?.reference_number,
        document?.document_type_label,
        author ? t('sessions.reading.authored_by', { name: author }) : null,
    ].filter(Boolean);

    const stage = document?.status_label ?? t('sessions.current_item');
    const referredNames = referredCommitteeNames(document);
    const referred =
        document?.status === 'committee-referral' || document?.status === 'committee-review';
    const referralNotice = referred
        ? referredNames.length > 0
            ? t('sessions.reading.referred_to_committee', { committee: formatList(referredNames) })
            : t('sessions.reading.referred_to_committee_unnamed')
        : null;

    return (
        <section className={cn('flex min-h-0 min-w-0 flex-1 flex-col', className)}>
            {readingAhead ? (
                <div className="mb-3 shrink-0">
                    <Notice tone="live" compact>
                        <span className="flex flex-wrap items-center gap-2">
                            <span>{t('sessions.reading.floor_moved')}</span>
                            <button
                                type="button"
                                onClick={onFollowFloor}
                                className="font-semibold underline-offset-2 hover:underline"
                            >
                                {t('sessions.reading.follow_floor')}
                            </button>
                        </span>
                    </Notice>
                </div>
            ) : null}

            <Panel as="article" raised className="flex min-h-0 flex-1 flex-col overflow-hidden">
                <div className="relative shrink-0 overflow-hidden bg-floor-plate px-5 py-5 text-floor-ink">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="text-eyebrow text-floor-ink-muted">{stage}</p>
                            {item ? (
                                <h2 className="mt-2 flex flex-wrap items-baseline gap-x-2 text-xl font-semibold tracking-[-0.02em] text-floor-ink md:text-2xl">
                                    {item.item_number ? (
                                        <span className="font-mono font-bold tracking-[-0.04em]">{item.item_number}</span>
                                    ) : null}
                                    <span>{item.title}</span>
                                </h2>
                            ) : (
                                <h2 className="mt-2 text-xl font-semibold text-floor-ink">
                                    {t('sessions.no_current_item')}
                                </h2>
                            )}
                            {meta.length > 0 ? (
                                <p className="mt-2 truncate text-sm text-floor-ink-muted">{meta.join(' · ')}</p>
                            ) : null}
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                            {headerActions}
                            {canPreview ? (
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    aria-expanded={fullTextOpen}
                                    aria-controls="member-full-text"
                                    onClick={() => setFullTextOpen((open) => !open)}
                                    className="border-floor-line bg-floor-sunk text-floor-ink hover:bg-floor-sunk"
                                >
                                    <FileText aria-hidden className="size-3.5" strokeWidth={1.75} />
                                    {fullTextOpen
                                        ? t('sessions.reading.close_full_text')
                                        : report
                                          ? t('sessions.reading.open_measure')
                                          : t('sessions.reading.open_full_text')}
                                </Button>
                            ) : document ? (
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    asChild
                                    className="border-floor-line bg-floor-sunk text-floor-ink hover:bg-floor-sunk"
                                >
                                    <Link href={`/documents/${document.slug}`}>
                                        <FileText aria-hidden className="size-3.5" strokeWidth={1.75} />
                                        {t('sessions.reading.open_full_text')}
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    </div>
                </div>

                {referralNotice ? (
                    <div className="shrink-0 border-b border-line bg-surface px-5 py-2">
                        <Notice tone="info" compact>
                            {referralNotice}
                        </Notice>
                    </div>
                ) : null}

                {fullTextOpen && annotationsError ? (
                    <div className="shrink-0 border-b border-line px-5 py-2">
                        <Notice tone="caution" compact>
                            {t('sessions.reading.annotations_load_failed')}
                        </Notice>
                    </div>
                ) : null}

                {body && !fullTextOpen && !report ? <MeasureBody text={body} /> : null}

                {report && !fullTextOpen ? (
                    <div className="min-h-0 flex-1 overflow-y-auto px-5 py-5">
                        <CommitteeReportBody report={report} />
                    </div>
                ) : null}

                {fullTextOpen && canPreview ? (
                    <div id="member-full-text" className="min-h-0 flex-1 overflow-hidden">
                        {annotationsReady ? (
                            <DocumentPdfViewer
                                key={`${document!.version_id}-${document!.preview_url}`}
                                src={document!.preview_url!}
                                saveUrl={document!.annotations_url!}
                                annotations={annotations}
                                className="h-full min-h-0 rounded-none border-0"
                            />
                        ) : (
                            <div className="flex h-full items-center justify-center text-sm text-ink-muted">
                                {t('sessions.reading.loading_annotations')}
                            </div>
                        )}
                    </div>
                ) : !item ? (
                    <EmptyReading message={t('sessions.no_current_item')} />
                ) : !document || body || report || canPreview ? null : (
                    <EmptyReading
                        message={
                            document.mime_type && document.mime_type !== 'application/pdf'
                                ? t('documents.preview_pdf_only')
                                : t('sessions.reading.preview_unavailable')
                        }
                        showDeskLink={document.slug}
                    />
                )}
            </Panel>
        </section>
    );
}

function EmptyReading({ message, showDeskLink }: { message: string; showDeskLink?: string }) {
    const { t } = useTranslations();

    return (
        <div className="flex h-full flex-col items-center justify-center gap-3 px-6 py-10 text-center">
            <FileWarning aria-hidden className="size-8 text-ink-faint" strokeWidth={1.5} />
            <p className="max-w-sm text-sm text-ink-muted">{message}</p>
            {showDeskLink ? (
                <Button variant="secondary" size="sm" asChild>
                    <Link href={`/documents/${showDeskLink}`}>{t('sessions.open_document')}</Link>
                </Button>
            ) : null}
        </div>
    );
}
