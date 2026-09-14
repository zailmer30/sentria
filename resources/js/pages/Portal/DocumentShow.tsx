import { PortalContainer } from '@/components/portal/PortalContainer';
import { PortalDetailRow } from '@/components/portal/PortalDetailRow';
import { recordNumber, type PortalPublicationCard } from '@/components/portal/PortalRecordCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Notice } from '@/components/ui/notice';
import { Separator } from '@/components/ui/separator';
import PortalLayout from '@/layouts/PortalLayout';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, Download, FileText, Users } from 'lucide-react';
import { useMemo } from 'react';

type Ordinance = {
    ordinance_number: string;
    series_year: number;
    enacted_on: string | null;
    effectivity_date: string | null;
    status?: string | null;
    has_signed_copy?: boolean;
    signed_copy_filename?: string | null;
};

type Resolution = {
    resolution_number: string;
    series_year: number;
    adopted_on: string | null;
    status?: string | null;
    has_signed_copy?: boolean;
    signed_copy_filename?: string | null;
};

type PublicationDetail = PortalPublicationCard & {
    abstract: string | null;
    session: string | null;
    tags: string[];
    redaction_applied: boolean;
    ordinance: Ordinance | null;
    resolution: Resolution | null;
};

type Seo = {
    title: string;
    description: string;
    type: string;
    url: string;
};

type Props = {
    publication: PublicationDetail;
    seo: Seo;
    variant?: 'ordinance' | 'resolution' | 'document';
    related?: PortalPublicationCard[];
};

type Passage = {
    heading: string | null;
    body: string;
};

/** "Section 3 — Mandatory Segregation at Source" opens a provision, not a paragraph. */
const PROVISION_HEADING = /^(section|sec\.|article|art\.|rule|chapter)\s+[\dIVXLC]+\b/i;

function splitIntoPassages(text: string | null): Passage[] {
    if (text === null || text.trim() === '') {
        return [];
    }

    return text
        .split(/\n\s*\n/)
        .map((block) => block.trim())
        .filter((block) => block !== '')
        .map((block) => {
            const [first = '', ...rest] = block.split('\n');
            const opener = first.trim();

            // A heading is a short line that titles the provision. A sentence that
            // merely opens with "Section 3 ..." keeps its punctuation and stays a
            // paragraph, so no record is silently restyled into headings.
            if (PROVISION_HEADING.test(opener) && opener.length <= 90 && !/[.;]$/.test(opener)) {
                return { heading: opener, body: rest.join('\n').trim() };
            }

            return { heading: null, body: block };
        });
}

export default function PortalDocumentShow({
    publication,
    seo,
    variant = 'document',
    related = [],
}: Props) {
    const { t } = useTranslations();
    const { formatDateLong } = useFormatters();

    const typeLabel =
        variant === 'ordinance'
            ? t('portal.document_type.ordinance')
            : variant === 'resolution'
              ? t('portal.document_type.resolution')
              : publication.document_type_label;

    const statusLabel =
        publication.status_label ??
        publication.ordinance?.status ??
        publication.resolution?.status ??
        null;
    const number = recordNumber(publication);
    const actionDate =
        publication.ordinance?.enacted_on ?? publication.resolution?.adopted_on ?? publication.published_at;
    const signedCopyAvailable = Boolean(
        publication.ordinance?.has_signed_copy || publication.resolution?.has_signed_copy,
    );
    const signedCopyDownload = `/portal/documents/${publication.slug}/signed-copy/download`;
    const signedCopyPreview = `/portal/documents/${publication.slug}/signed-copy/preview`;
    const passages = useMemo(() => splitIntoPassages(publication.abstract), [publication.abstract]);

    return (
        <PortalLayout title={seo.title} description={seo.description} ogType={seo.type} ogUrl={seo.url}>
            <section className="border-b border-line bg-surface-alt">
                <PortalContainer className="py-8 lg:py-10">
                    <Link
                        href="/portal/search"
                        className="inline-flex items-center gap-1.5 text-sm font-medium text-ink-muted hover:text-ink"
                    >
                        <ArrowLeft aria-hidden="true" className="size-4" />
                        {t('portal.back_to_search')}
                    </Link>

                    <div className="mt-5 flex flex-wrap items-center gap-2">
                        {typeLabel ? <Badge variant="secondary">{typeLabel}</Badge> : null}
                        {statusLabel ? <Badge variant="outline">{statusLabel}</Badge> : null}
                        {number ? <span className="font-mono text-xs text-brand">{number}</span> : null}
                    </div>

                    <h1 className="mt-4 max-w-4xl text-3xl font-bold text-ink lg:text-4xl">{publication.title}</h1>
                    {publication.summary ? <p className="mt-4 max-w-3xl text-ink-muted">{publication.summary}</p> : null}

                    <div data-print-hide className="mt-6 flex flex-wrap items-center gap-3">
                        {signedCopyAvailable ? (
                            <>
                                <Button asChild>
                                    <a href={signedCopyDownload}>
                                        <Download aria-hidden="true" />
                                        {t('portal.download_pdf')}
                                    </a>
                                </Button>
                                <Button variant="outline" asChild>
                                    <a href={signedCopyPreview} target="_blank" rel="noreferrer">
                                        {t('portal.view_pdf')}
                                    </a>
                                </Button>
                            </>
                        ) : (
                            <p className="text-sm text-ink-muted">{t('portal.no_signed_copy')}</p>
                        )}
                    </div>
                </PortalContainer>
            </section>

            <PortalContainer className="grid flex-1 gap-8 py-10 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="order-1 min-w-0">
                    <article className="rounded-[var(--radius-lg)] border border-line bg-surface p-6 shadow-plate sm:p-8">
                        {number ? <p className="text-eyebrow font-mono text-ink-muted">{number}</p> : null}

                        {publication.redaction_applied ? (
                            <Notice tone="restricted" className="mt-6">
                                {t('portal.redaction_notice')}
                            </Notice>
                        ) : null}

                        {passages.length > 0 ? (
                            <div className="record-prose mt-6 space-y-6">
                                {passages.map((passage, index) => (
                                    <section key={`${passage.heading ?? 'passage'}-${index}`}>
                                        {passage.heading ? (
                                            <h2 className="text-md font-semibold text-ink">{passage.heading}</h2>
                                        ) : null}
                                        {passage.body ? (
                                            <p
                                                className={
                                                    passage.heading
                                                        ? 'mt-2 whitespace-pre-line text-ink-muted'
                                                        : 'whitespace-pre-line text-ink-muted'
                                                }
                                            >
                                                {passage.body}
                                            </p>
                                        ) : null}
                                    </section>
                                ))}
                            </div>
                        ) : publication.summary ? (
                            <p className="mt-6 text-ink-muted">{publication.summary}</p>
                        ) : null}

                        {publication.tags.length > 0 ? (
                            <ul className="mt-8 flex flex-wrap gap-2">
                                {publication.tags.map((tag) => (
                                    <li key={tag}>
                                        <Badge variant="outline">{tag}</Badge>
                                    </li>
                                ))}
                            </ul>
                        ) : null}

                        <Separator className="my-8" />
                        <p className="text-sm text-ink-subtle">{t('portal.disclaimer')}</p>
                    </article>
                </div>

                <aside className="order-2 space-y-5 lg:sticky lg:top-24 lg:self-start">
                    <div className="rounded-[var(--radius-lg)] border border-line bg-surface p-6 shadow-plate">
                        <p className="text-eyebrow text-ink-muted">{t('portal.record_details')}</p>
                        <div className="mt-5 space-y-4">
                            {actionDate ? (
                                <PortalDetailRow
                                    icon={CalendarDays}
                                    label={t('portal.field.date_of_action')}
                                    value={formatDateLong(actionDate)}
                                />
                            ) : null}
                            {publication.session ? (
                                <PortalDetailRow
                                    icon={FileText}
                                    label={t('portal.field.session')}
                                    value={publication.session}
                                />
                            ) : null}
                            {publication.committee ? (
                                <PortalDetailRow icon={Users} label={t('portal.field.committee')} value={publication.committee} />
                            ) : null}
                        </div>
                        {publication.author ? (
                            <>
                                <Separator className="my-5" />
                                <p className="text-eyebrow text-ink-muted">{t('portal.authors')}</p>
                                <p className="mt-3 text-sm font-medium text-ink">{publication.author}</p>
                            </>
                        ) : null}
                    </div>

                    {related.length > 0 ? (
                        <div className="rounded-[var(--radius-lg)] border border-line bg-surface p-6 shadow-plate">
                            <p className="text-eyebrow text-ink-muted">{t('portal.same_committee')}</p>
                            <ul className="mt-4 space-y-4">
                                {related.map((item) => (
                                    <li key={item.slug}>
                                        <Link href={`/portal/documents/${item.slug}`} className="block hover:text-brand">
                                            <p className="text-sm font-medium">{item.title}</p>
                                            {recordNumber(item) ? (
                                                <p className="mt-1 font-mono text-xs text-brand">{recordNumber(item)}</p>
                                            ) : null}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : null}
                </aside>
            </PortalContainer>
        </PortalLayout>
    );
}
