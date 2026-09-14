import { Button } from '@/components/ui/button';
import { Panel, PanelBody, PanelHead, PanelTitle } from '@/components/ui/panel';
import { StatusChip, type StatusTone } from '@/components/ui/status';
import { useTranslations } from '@/lib/i18n';
import { formatAgendaNumber, type CalendarDocketItem, type Translate } from '@/pages/Sessions/Floor/shared';
import { router } from '@inertiajs/react';
import { BookOpen, RotateCcw, ScrollText, Timer } from 'lucide-react';

type Props = {
    sessionId: string;
    items: CalendarDocketItem[];
    t?: Translate;
};

export function CalendarDocket({ sessionId, items, t: translate }: Props) {
    const { t: hookT } = useTranslations();
    const t = translate ?? hookT;

    return (
        <Panel as="section">
            <PanelHead sunk>
                <PanelTitle>{t('sessions.calendar.title')}</PanelTitle>
            </PanelHead>
            {items.length === 0 ? (
                <PanelBody>
                    <p className="text-sm text-ink-muted">{t('sessions.calendar.empty')}</p>
                </PanelBody>
            ) : (
                <PanelBody className="px-0 py-0">
                    <ul>
                        {items.map((item) => (
                            <CalendarDocketRow key={item.id} sessionId={sessionId} item={item} t={t} />
                        ))}
                    </ul>
                </PanelBody>
            )}
        </Panel>
    );
}

export function CalendarItemActions({
    sessionId,
    item,
    t: translate,
}: {
    sessionId: string;
    item: Pick<
        CalendarDocketItem,
        | 'id'
        | 'agenda_item_id'
        | 'document_id'
        | 'can_second_reading'
        | 'can_third_reading'
        | 'placed_on_third_reading'
        | 'can_postpone'
        | 'can_undo'
    >;
    t?: Translate;
}) {
    const { t: hookT } = useTranslations();
    const t = translate ?? hookT;
    const agendaItemId = item.agenda_item_id === undefined ? item.id : item.agenda_item_id;

    function post(path: string, data: Record<string, string> = {}) {
        router.post(path, data, { preserveScroll: true });
    }

    if (!item.can_second_reading && !item.can_third_reading && !item.can_postpone && !item.can_undo) {
        if (item.placed_on_third_reading) {
            return <span className="text-xs text-ink-muted">{t('sessions.calendar.placed_on_third')}</span>;
        }

        return null;
    }

    return (
        <>
            {item.can_second_reading ? (
                <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    onClick={() => {
                        if (agendaItemId) {
                            post(`/sessions/${sessionId}/agenda/${agendaItemId}/second-reading`);
                            return;
                        }

                        if (item.document_id) {
                            post(`/sessions/${sessionId}/calendar/second-reading`, { document_id: item.document_id });
                        }
                    }}
                >
                    <BookOpen aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                    {t('sessions.calendar.second_reading')}
                </Button>
            ) : null}
            {item.can_third_reading ? (
                <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    onClick={() => {
                        if (agendaItemId) {
                            post(`/sessions/${sessionId}/agenda/${agendaItemId}/third-reading`);
                            return;
                        }

                        if (item.document_id) {
                            post(`/sessions/${sessionId}/calendar/third-reading`, { document_id: item.document_id });
                        }
                    }}
                >
                    <ScrollText aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                    {t('sessions.calendar.third_reading')}
                </Button>
            ) : null}
            {item.can_postpone ? (
                <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    onClick={() => {
                        if (agendaItemId) {
                            post(`/sessions/${sessionId}/agenda/${agendaItemId}/postpone`);
                            return;
                        }

                        if (item.document_id) {
                            post(`/sessions/${sessionId}/calendar/postpone`, { document_id: item.document_id });
                        }
                    }}
                >
                    <Timer aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                    {t('sessions.calendar.postpone')}
                </Button>
            ) : null}
            {item.can_undo && agendaItemId ? (
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() => post(`/sessions/${sessionId}/agenda/${agendaItemId}/undo-postpone`)}
                >
                    <RotateCcw aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                    {t('sessions.calendar.undo')}
                </Button>
            ) : null}
        </>
    );
}

function CalendarDocketRow({
    sessionId,
    item,
    t,
}: {
    sessionId: string;
    item: CalendarDocketItem;
    t: Translate;
}) {
    const title = item.document?.title ?? item.title;

    return (
        <li className="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-line px-5 py-3 last:border-b-0">
            <span className="w-14 shrink-0 font-mono text-xs text-ink-faint">
                {item.agenda_item_id ? formatAgendaNumber(item.item_number) : (item.reference_number ?? '')}
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium text-ink">{title}</p>
                {item.status === 'postponed' ? (
                    <p className="mt-0.5 text-xs text-ink-muted">
                        {item.carried_to
                            ? t('sessions.calendar.carried_to', { sitting: item.carried_to.session_number })
                            : t('sessions.calendar.queued')}
                    </p>
                ) : null}
            </div>
            <StatusChip tone={docketTone(item.status)} size="sm">
                {docketStatusLabel(item.status, t)}
            </StatusChip>
            <span className="flex shrink-0 flex-wrap items-center justify-end gap-1">
                <CalendarItemActions sessionId={sessionId} item={item} t={t} />
            </span>
        </li>
    );
}

function docketTone(status: string): StatusTone {
    if (status === 'in-progress') {
        return 'live';
    }

    if (status === 'postponed') {
        return 'review';
    }

    if (status === 'completed') {
        return 'moving';
    }

    return 'draft';
}

function docketStatusLabel(status: string, t: Translate): string {
    if (status === 'pending') {
        return t('sessions.item_pending');
    }

    if (status === 'in-progress') {
        return t('sessions.item_in_progress');
    }

    if (status === 'completed') {
        return t('sessions.item_completed');
    }

    if (status === 'postponed') {
        return t('sessions.item_postponed');
    }

    return status;
}
