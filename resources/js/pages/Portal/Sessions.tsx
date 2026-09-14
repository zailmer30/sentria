import { PortalContainer } from '@/components/portal/PortalContainer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import PortalLayout from '@/layouts/PortalLayout';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { CalendarDays, Clock, MapPin } from 'lucide-react';

type SessionSummary = {
    id: string;
    session_number: string;
    title: string;
    session_type: string | null;
    session_type_label?: string | null;
    scheduled_start_at: string | null;
    scheduled_end_at: string | null;
    location: string | null;
    status_label: string;
    agenda?: string[];
};

type Paginated<T> = {
    data: T[];
    total: number;
};

type Props = {
    sessions: Paginated<SessionSummary>;
    filters?: { type?: string | null };
};

const SITTINGS = [
    { value: '', key: 'portal.sitting.all' },
    { value: 'regular', key: 'portal.sitting.regular' },
    { value: 'special', key: 'portal.sitting.special' },
    { value: 'committee-hearing', key: 'portal.sitting.hearing' },
    { value: 'public-hearing', key: 'portal.sitting.consultation' },
] as const;

function dateParts(iso: string | null): { day: string; month: string } | null {
    if (!iso) {
        return null;
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return {
        day: new Intl.DateTimeFormat(undefined, { day: 'numeric' }).format(date),
        month: new Intl.DateTimeFormat(undefined, { month: 'short' }).format(date).toUpperCase(),
    };
}

export default function PortalSessions({ sessions, filters = {} }: Props) {
    const { t } = useTranslations();
    const { formatDateLong, formatTime } = useFormatters();
    const activeType = filters.type ?? '';

    return (
        <PortalLayout title={t('portal.sessions_title')} description={t('portal.sessions_description')}>
            <section className="border-b border-line bg-surface-alt">
                <PortalContainer className="py-10 lg:py-12">
                    <p className="text-eyebrow text-brand">{t('portal.sessions_eyebrow')}</p>
                    <h1 className="mt-3 text-3xl font-bold text-ink lg:text-4xl">{t('portal.sessions_headline')}</h1>
                    <p className="mt-4 max-w-2xl text-ink-muted">{t('portal.sessions_intro')}</p>
                </PortalContainer>
            </section>

            <PortalContainer className="flex flex-1 flex-col gap-10 py-10 lg:flex-row">
                <aside className="w-full shrink-0 lg:sticky lg:top-24 lg:w-64 lg:self-start">
                    <p className="text-eyebrow text-ink-muted">{t('portal.sitting_type')}</p>
                    <div className="mt-3 flex flex-col gap-1">
                        {SITTINGS.map((item) => (
                            <Button
                                key={item.value || 'all'}
                                type="button"
                                variant={activeType === item.value ? 'secondary' : 'ghost'}
                                className="justify-start"
                                onClick={() =>
                                    router.get('/portal/sessions', item.value ? { type: item.value } : {})
                                }
                            >
                                {t(item.key)}
                            </Button>
                        ))}
                    </div>
                </aside>

                <section className="min-w-0 flex-1 space-y-4">
                    {sessions.data.length === 0 ? (
                        <div className="rounded-[var(--radius-lg)] border border-dashed border-line bg-surface p-10 text-center">
                            <p className="font-display text-lg font-semibold">{t('portal.sessions_empty')}</p>
                        </div>
                    ) : (
                        sessions.data.map((session) => {
                            const tile = dateParts(session.scheduled_start_at);

                            return (
                                <article
                                    key={session.id}
                                    className="flex flex-col gap-5 rounded-[var(--radius-lg)] border border-line bg-surface p-5 shadow-plate sm:flex-row sm:p-6"
                                >
                                    {tile ? (
                                        <div className="flex size-20 shrink-0 flex-col items-center justify-center rounded-[var(--radius-md)] bg-plate text-accent-on">
                                            <span className="font-display text-3xl leading-none font-bold">{tile.day}</span>
                                            <span className="mt-1 text-[0.6875rem] font-semibold tracking-[0.16em]">
                                                {tile.month}
                                            </span>
                                        </div>
                                    ) : null}

                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            {session.session_type_label || session.session_type ? (
                                                <Badge variant="secondary">
                                                    {session.session_type_label ?? session.session_type}
                                                </Badge>
                                            ) : null}
                                            <Badge variant="outline">{session.status_label}</Badge>
                                        </div>
                                        <h2 className="mt-3 text-xl font-semibold text-ink">{session.title}</h2>
                                        <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-ink-muted">
                                            <span className="inline-flex items-center gap-1.5">
                                                <CalendarDays aria-hidden="true" className="size-3.5 text-brand" />
                                                {session.scheduled_start_at
                                                    ? formatDateLong(session.scheduled_start_at)
                                                    : t('portal.date_tbd')}
                                            </span>
                                            {session.scheduled_start_at ? (
                                                <span className="inline-flex items-center gap-1.5">
                                                    <Clock aria-hidden="true" className="size-3.5 text-brand" />
                                                    {formatTime(session.scheduled_start_at)}
                                                </span>
                                            ) : null}
                                            {session.location ? (
                                                <span className="inline-flex items-center gap-1.5">
                                                    <MapPin aria-hidden="true" className="size-3.5 text-brand" />
                                                    {session.location}
                                                </span>
                                            ) : null}
                                        </div>

                                        {session.agenda && session.agenda.length > 0 ? (
                                            <>
                                                <Separator className="my-4" />
                                                <p className="text-eyebrow text-ink-muted">{t('portal.agenda')}</p>
                                                <ul className="mt-3 space-y-2">
                                                    {session.agenda.map((item) => (
                                                        <li key={item} className="flex gap-2.5 text-sm text-ink">
                                                            <span
                                                                className="mt-1.5 size-1.5 shrink-0 rounded-full bg-brand"
                                                                aria-hidden="true"
                                                            />
                                                            <span>{item}</span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </>
                                        ) : null}
                                    </div>
                                </article>
                            );
                        })
                    )}
                </section>
            </PortalContainer>
        </PortalLayout>
    );
}
