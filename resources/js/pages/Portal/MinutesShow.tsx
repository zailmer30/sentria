import { PortalContainer } from '@/components/portal/PortalContainer';
import { Badge } from '@/components/ui/badge';
import PortalLayout from '@/layouts/PortalLayout';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

type MinutesDetail = {
    id: string;
    title: string;
    content: string | null;
    finalized_at: string | null;
    session: {
        session_number: string;
        title: string;
        scheduled_start_at: string | null;
    } | null;
};

type Seo = {
    title: string;
    description: string;
    type: string;
    url: string;
};

type Props = {
    minutes: MinutesDetail;
    seo: Seo;
};

export default function PortalMinutesShow({ minutes, seo }: Props) {
    const { t } = useTranslations();
    const { formatDateLong } = useFormatters();

    return (
        <PortalLayout title={seo.title} description={seo.description} ogType={seo.type} ogUrl={seo.url}>
            <section className="border-b border-line bg-surface-alt">
                <PortalContainer className="py-8 lg:py-10">
                    <Link
                        href="/portal/sessions"
                        className="inline-flex items-center gap-1.5 text-sm font-medium text-ink-muted hover:text-ink"
                    >
                        <ArrowLeft aria-hidden="true" className="size-4" />
                        {t('portal.back_to_sessions')}
                    </Link>
                    <div className="mt-5">
                        <Badge variant="secondary">{t('portal.minutes_label')}</Badge>
                    </div>
                    <h1 className="mt-4 max-w-4xl text-3xl font-bold text-ink lg:text-4xl">{minutes.title}</h1>
                    {minutes.session?.scheduled_start_at ? (
                        <p className="mt-3 text-sm text-ink-muted">{formatDateLong(minutes.session.scheduled_start_at)}</p>
                    ) : null}
                </PortalContainer>
            </section>

            <PortalContainer className="py-10">
                <article className="max-w-3xl rounded-[var(--radius-lg)] border border-line bg-surface p-6 shadow-plate sm:p-8">
                    {minutes.content ? (
                        <div className="record-prose whitespace-pre-wrap">{minutes.content}</div>
                    ) : (
                        <p className="text-sm text-ink-muted">{t('portal.minutes_empty')}</p>
                    )}
                </article>
            </PortalContainer>
        </PortalLayout>
    );
}
