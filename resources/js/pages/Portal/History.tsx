import { PortalContainer } from '@/components/portal/PortalContainer';
import { Button } from '@/components/ui/button';
import PortalLayout from '@/layouts/PortalLayout';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

type HistoryEvent = {
    stage: string;
    label: string;
    occurred_at: string | null;
    description: string | null;
};

type Props = {
    subject: {
        title: string;
        slug: string;
        reference_number: string | null;
    };
    events: HistoryEvent[];
    seo: {
        title: string;
        description: string;
        type: string;
        url: string;
    };
};

export default function PortalHistory({ subject, events, seo }: Props) {
    const { t } = useTranslations();
    const { formatDateLong, toDateTimeAttribute } = useFormatters();

    return (
        <PortalLayout title={seo.title} description={seo.description} ogType={seo.type} ogUrl={seo.url}>
            <section className="border-b border-line bg-surface-alt">
                <PortalContainer className="py-8 lg:py-10">
                    <Link
                        href={`/portal/documents/${subject.slug}`}
                        className="inline-flex items-center gap-1.5 text-sm font-medium text-ink-muted hover:text-ink"
                    >
                        <ArrowLeft aria-hidden="true" className="size-4" />
                        {t('portal.back_to_document')}
                    </Link>
                    <p className="mt-5 text-eyebrow text-brand">{t('portal.history_label')}</p>
                    <h1 className="mt-3 max-w-4xl text-3xl font-bold text-ink lg:text-4xl">{subject.title}</h1>
                    {subject.reference_number ? (
                        <p className="mt-3 font-mono text-xs text-brand">{subject.reference_number}</p>
                    ) : null}
                </PortalContainer>
            </section>

            <PortalContainer className="py-10">
                <ol className="relative ml-2 max-w-3xl">
                    {events.map((event, index) => (
                        <li key={`${event.stage}-${index}`} className="relative flex gap-4 pb-8 last:pb-0">
                            {index < events.length - 1 ? (
                                <span className="absolute top-3 left-[5px] h-full w-px bg-line" aria-hidden="true" />
                            ) : null}
                            <span className="relative z-10 mt-1 size-2.5 shrink-0 rounded-full bg-brand" aria-hidden="true" />
                            <div>
                                <p className="text-eyebrow text-ink-muted">{event.label}</p>
                                {event.description ? <p className="mt-1 font-medium text-ink">{event.description}</p> : null}
                                {event.occurred_at ? (
                                    <time
                                        dateTime={toDateTimeAttribute(event.occurred_at)}
                                        className="mt-1 block text-sm text-ink-subtle"
                                    >
                                        {formatDateLong(event.occurred_at)}
                                    </time>
                                ) : null}
                            </div>
                        </li>
                    ))}
                </ol>

                <Button variant="link" size="sm" className="mt-4" asChild>
                    <Link href={`/portal/documents/${subject.slug}`}>{t('portal.back_to_document')}</Link>
                </Button>
            </PortalContainer>
        </PortalLayout>
    );
}
