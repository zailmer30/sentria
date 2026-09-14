import { EmptyState } from '@/components/ui/empty-state';
import { Panel, PanelBody } from '@/components/ui/panel';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Timeline, TimelineItem } from '@/components/ui/timeline';
import { Button } from '@/components/ui/button';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import {
    BadgeCheck,
    BookOpen,
    CalendarDays,
    CheckCircle2,
    FileCheck,
    FileStack,
    FileText,
    History,
    ScrollText,
    Search,
    Stamp,
    Undo2,
    Users,
    type LucideIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';

export type HistoryEvent = {
    stage: string;
    label: string;
    occurred_at: string | null;
    description: string | null;
    meta?: Record<string, unknown> | null;
};

type Props = {
    events: HistoryEvent[];
    className?: string;
};

const PREVIEW = 6;

const STAGE_ICON: Record<string, LucideIcon> = {
    document: FileText,
    secretariat_review: Search,
    returned_for_revision: Undo2,
    registered: BadgeCheck,
    committee_referral: Users,
    committee_report: ScrollText,
    first_reading: BookOpen,
    second_reading: BookOpen,
    third_reading: BookOpen,
    session: CalendarDays,
    amendment: FileStack,
    vote: CheckCircle2,
    approval: Stamp,
    final_version: FileCheck,
};

export function LegislativeHistoryPreview({ events, className }: Props) {
    const { t } = useTranslations();
    const { formatDate } = useFormatters();
    const newestFirst = events.toReversed();
    const preview = newestFirst.slice(0, PREVIEW);

    return (
        <Panel className={className}>
            <PanelBody className="px-6 pt-6 pb-4">
                <div className="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                    <div className="min-w-0">
                        <h2 className="text-base font-semibold tracking-tight text-ink">
                            {t('legislation.history_title')}
                        </h2>
                        <p className="mt-1 text-sm text-ink-muted">{t('legislation.history_caption')}</p>
                    </div>
                    {events.length > 0 ? (
                        <LegislativeHistorySheet events={events}>
                            <Button variant="ghost" size="sm" className="text-ink-muted">
                                <History aria-hidden="true" strokeWidth={1.75} />
                                {t('legislation.open_full_history')}
                            </Button>
                        </LegislativeHistorySheet>
                    ) : null}
                </div>
            </PanelBody>
            {events.length === 0 ? (
                <EmptyState
                    bare
                    icon={History}
                    title={t('legislation.history_empty')}
                    description={t('legislation.history_subtitle')}
                    className="pt-2 pb-12"
                />
            ) : (
                <PanelBody className="px-6 pt-0 pb-6">
                    <HistoryTimeline events={preview} formatDate={formatDate} />
                </PanelBody>
            )}
        </Panel>
    );
}

export function LegislativeHistorySheet({
    events,
    children,
}: {
    events: HistoryEvent[];
    children: ReactNode;
}) {
    const { t } = useTranslations();
    const { formatDate } = useFormatters();
    const newestFirst = events.toReversed();

    return (
        <Sheet>
            <SheetTrigger asChild>{children}</SheetTrigger>
            <SheetContent
                side="right"
                className="w-[min(36rem,92vw)] p-0"
                closeLabel={t('actions.close')}
            >
                <SheetHeader className="px-6 py-5">
                    <SheetTitle className="text-base">{t('legislation.history_title')}</SheetTitle>
                    <SheetDescription className="text-sm">{t('legislation.history_caption')}</SheetDescription>
                </SheetHeader>
                <div className="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                    {newestFirst.length === 0 ? (
                        <EmptyState
                            bare
                            icon={History}
                            title={t('legislation.history_empty')}
                            description={t('legislation.history_subtitle')}
                            className="py-10"
                        />
                    ) : (
                        <HistoryTimeline events={newestFirst} formatDate={formatDate} showMeta />
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}

export function HistoryTimeline({
    events,
    formatDate,
    showMeta = false,
}: {
    events: HistoryEvent[];
    formatDate: (value: string | null | undefined) => string;
    showMeta?: boolean;
}) {
    const { t } = useTranslations();

    return (
        <Timeline>
            {events.map((event, index) => {
                const current = index === 0;
                const metaEntries = showMeta
                    ? Object.entries(event.meta ?? {}).filter(
                          ([, value]) => value !== null && value !== undefined && value !== '',
                      )
                    : [];

                return (
                    <TimelineItem
                        key={`${event.stage}-${event.occurred_at}-${index}`}
                        label={event.label}
                        when={formatDate(event.occurred_at)}
                        current={current}
                        icon={STAGE_ICON[event.stage] ?? FileText}
                        badge={current ? t('legislation.history_current') : undefined}
                    >
                        {event.description ? <p>{event.description}</p> : null}
                        {metaEntries.length > 0 ? (
                            <dl className={cn('grid gap-1 text-xs text-ink-muted', event.description && 'mt-2')}>
                                {metaEntries.map(([key, value]) => (
                                    <div key={key} className="flex gap-2">
                                        <dt className="capitalize">{key.replaceAll('_', ' ')}:</dt>
                                        <dd className="font-mono">{String(value)}</dd>
                                    </div>
                                ))}
                            </dl>
                        ) : null}
                    </TimelineItem>
                );
            })}
        </Timeline>
    );
}
