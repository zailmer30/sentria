import { Badge } from '@/components/ui/badge';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';

export type PortalPublicationCard = {
    slug: string;
    title: string;
    summary: string | null;
    published_at: string | null;
    document_type_label: string | null;
    status_label?: string | null;
    author: string | null;
    committee: string | null;
    ordinance_number?: string | null;
    resolution_number?: string | null;
    reference_number?: string | null;
};

export function recordNumber(item: PortalPublicationCard): string | null {
    return item.ordinance_number ?? item.resolution_number ?? item.reference_number ?? null;
}

export function PortalRecordCard({ item }: { item: PortalPublicationCard }) {
    const { t } = useTranslations();
    const { formatDateLong, toDateTimeAttribute } = useFormatters();
    const number = recordNumber(item);

    return (
        <Link
            href={`/portal/documents/${item.slug}`}
            className="group block rounded-[var(--radius-lg)] border border-line bg-surface p-5 shadow-plate transition duration-[var(--duration-base)] hover:-translate-y-0.5 hover:border-brand/40 hover:shadow-lift sm:p-6"
        >
            <div className="flex flex-wrap items-start gap-2">
                {item.document_type_label ? <Badge variant="secondary">{item.document_type_label}</Badge> : null}
                {item.status_label ? <Badge variant="outline">{item.status_label}</Badge> : null}
                {item.published_at ? (
                    <time
                        dateTime={toDateTimeAttribute(item.published_at)}
                        className="ml-auto text-sm text-ink-muted"
                    >
                        {formatDateLong(item.published_at)}
                    </time>
                ) : null}
            </div>
            {number ? <p className="mt-3 font-mono text-xs text-brand">{number}</p> : null}
            <h3 className="mt-1 text-lg font-semibold text-ink">{item.title}</h3>
            {item.summary ? <p className="mt-2 line-clamp-2 text-sm text-ink-muted">{item.summary}</p> : null}
            <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-subtle">
                {item.committee ? <span>{item.committee}</span> : null}
                {item.author ? <span>{item.author}</span> : null}
                <span className="ml-auto font-medium text-brand opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">
                    {t('portal.open_record')}
                </span>
            </div>
        </Link>
    );
}
