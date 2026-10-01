import { ReferToCommitteeDialog } from '@/components/documents/ReferToCommitteeDialog';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Checkbox } from '@/components/ui/input';
import { Panel, PanelBody, PanelFoot, PanelHead, PanelTitle } from '@/components/ui/panel';
import { StatusChip, type StatusTone } from '@/components/ui/status';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { formatAgendaNumber, referredCommitteeNames, type CalendarDocketItem, type Translate } from '@/pages/Sessions/Floor/shared';
import { router } from '@inertiajs/react';
import { BookOpen, Pencil, RotateCcw, Scale, ScrollText, Timer } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

type CalendarRouteAction = 'second-reading' | 'postpone' | 'third-reading' | 'undo';

type Props = {
    sessionId: string;
    items: CalendarDocketItem[];
    t?: Translate;
    committees?: { id: string; name: string }[];
};

export function CalendarDocket({ sessionId, items, t: translate, committees = [] }: Props) {
    const { t: hookT } = useTranslations();
    const t = translate ?? hookT;
    const [selectedIds, setSelectedIds] = useState<string[]>([]);
    const [pending, setPending] = useState<CalendarRouteAction | null>(null);
    const [processing, setProcessing] = useState(false);
    const selectAllRef = useRef<HTMLInputElement>(null);

    const actionable = useMemo(() => items.filter(itemHasAction), [items]);
    const actionableIds = useMemo(() => new Set(actionable.map((item) => item.id)), [actionable]);
    const selected = useMemo(
        () => items.filter((item) => selectedIds.includes(item.id)),
        [items, selectedIds],
    );
    const allSelected = actionable.length > 0 && actionable.every((item) => selectedIds.includes(item.id));
    const someSelected = selectedIds.some((id) => actionableIds.has(id));

    useEffect(() => {
        const live = new Set(items.map((item) => item.id));

        setSelectedIds((current) => current.filter((id) => live.has(id)));
    }, [items]);

    useEffect(() => {
        if (selectAllRef.current) {
            selectAllRef.current.indeterminate = someSelected && !allSelected;
        }
    }, [allSelected, someSelected]);

    function toggleSelected(id: string) {
        setSelectedIds((current) =>
            current.includes(id) ? current.filter((value) => value !== id) : [...current, id],
        );
    }

    function toggleAll() {
        setSelectedIds(allSelected ? [] : actionable.map((item) => item.id));
    }

    function eligible(action: CalendarRouteAction) {
        return selected.filter((item) => itemAllows(item, action));
    }

    function confirmBulk(action: CalendarRouteAction) {
        if (eligible(action).length === 0) {
            return;
        }

        setPending(action);
    }

    function performBulk() {
        if (!pending) {
            return;
        }

        const targets = eligible(pending);

        if (targets.length === 0) {
            return;
        }

        router.post(
            `/sessions/${sessionId}/calendar/bulk`,
            {
                action: pending,
                items: targets.map(bulkItemPayload),
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onSuccess: () => setSelectedIds([]),
                onFinish: () => {
                    setProcessing(false);
                    setPending(null);
                },
            },
        );
    }

    return (
        <Panel as="section">
            <PanelHead sunk>
                <div className="min-w-0">
                    <PanelTitle>{t('sessions.calendar.title')}</PanelTitle>
                    {items.length > 0 ? (
                        <p className="mt-0.5 font-mono text-xs text-ink-faint">
                            {t('sessions.calendar.count', { count: items.length })}
                        </p>
                    ) : null}
                </div>
                {actionable.length > 0 ? (
                    <label className="flex cursor-pointer items-center gap-2 rounded-[var(--radius-md)] px-2 py-1.5 text-xs text-ink-muted transition-colors duration-[var(--duration-fast)] hover:bg-canvas-sunk hover:text-ink">
                        <Checkbox
                            ref={selectAllRef}
                            checked={allSelected}
                            onChange={toggleAll}
                            aria-label={t('sessions.calendar.select_all')}
                        />
                        {t('sessions.calendar.select_all')}
                    </label>
                ) : null}
            </PanelHead>
            {items.length === 0 ? (
                <EmptyState
                    bare
                    icon={ScrollText}
                    title={t('sessions.calendar.empty_title')}
                    description={t('sessions.calendar.empty')}
                />
            ) : (
                <PanelBody className="px-0 py-0">
                    <ul>
                        {items.map((item) => (
                            <CalendarDocketRow
                                key={item.id}
                                sessionId={sessionId}
                                item={item}
                                t={t}
                                committees={committees}
                                selectable={actionableIds.has(item.id)}
                                showSelection={actionable.length > 0}
                                selected={selectedIds.includes(item.id)}
                                onToggle={() => toggleSelected(item.id)}
                            />
                        ))}
                    </ul>
                </PanelBody>
            )}
            {selected.length > 0 ? (
                <PanelFoot className="justify-between gap-3">
                    <p className="text-sm font-medium text-ink">
                        {t('sessions.calendar.selected_count', { count: selected.length })}
                    </p>
                    <span className="flex flex-wrap items-center justify-end gap-1.5" role="group" aria-label={t('sessions.calendar.bulk_actions')}>
                        <Button
                            type="button"
                            size="sm"
                            variant="plate"
                            disabled={eligible('second-reading').length === 0}
                            onClick={() => confirmBulk('second-reading')}
                        >
                            <BookOpen aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                            {t('sessions.calendar.second_reading')}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="plate"
                            disabled={eligible('third-reading').length === 0}
                            onClick={() => confirmBulk('third-reading')}
                        >
                            <ScrollText aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                            {t('sessions.calendar.third_reading')}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            disabled={eligible('postpone').length === 0}
                            onClick={() => confirmBulk('postpone')}
                        >
                            <Timer aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                            {t('sessions.calendar.postpone')}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            disabled={eligible('undo').length === 0}
                            onClick={() => confirmBulk('undo')}
                        >
                            <RotateCcw aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                            {t('sessions.calendar.undo')}
                        </Button>
                    </span>
                </PanelFoot>
            ) : null}

            <Dialog
                open={pending !== null}
                onOpenChange={(open) => {
                    if (!open && !processing) {
                        setPending(null);
                    }
                }}
            >
                <DialogContent
                    title={bulkConfirmCopy(pending, eligible(pending ?? 'second-reading').length, t).title}
                    description={bulkConfirmCopy(pending, eligible(pending ?? 'second-reading').length, t).hint}
                >
                    <DialogFooter className="mt-0">
                        <Button type="button" variant="ghost" disabled={processing} onClick={() => setPending(null)}>
                            {t('sessions.cancel')}
                        </Button>
                        <Button type="button" variant="primary" disabled={processing} onClick={performBulk}>
                            {t('sessions.calendar.confirm_continue')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Panel>
    );
}

export function CalendarItemActions({
    sessionId,
    item,
    t: translate,
    emphasis = false,
    committees = [],
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
        | 'can_refer'
        | 'can_edit_referral'
        | 'referral_agenda_item_id'
        | 'document'
    >;
    t?: Translate;
    /** Navy plate on the forward routing action — used on the calendar docket. */
    emphasis?: boolean;
    committees?: { id: string; name: string }[];
}) {
    const { t: hookT } = useTranslations();
    const t = translate ?? hookT;
    const agendaItemId = item.agenda_item_id === undefined ? item.id : item.agenda_item_id;
    const referralAgendaItemId = item.referral_agenda_item_id ?? agendaItemId;
    const [pending, setPending] = useState<CalendarRouteAction | null>(null);
    const [referOpen, setReferOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const forward = emphasis ? 'plate' : 'secondary';
    const canRefer = item.can_refer === true && Boolean(item.document?.slug);
    const canEditReferral = item.can_edit_referral === true && Boolean(item.document?.slug);
    const hasCalendarAction = Boolean(
        item.can_second_reading || item.can_third_reading || item.can_postpone || item.can_undo,
    );

    function post(path: string, data: Record<string, string> = {}) {
        router.post(path, data, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setPending(null);
            },
        });
    }

    function perform(action: CalendarRouteAction) {
        if (agendaItemId) {
            post(`/sessions/${sessionId}/agenda/${agendaItemId}/${actionPath(action)}`);
            return;
        }

        if (item.document_id && action !== 'undo') {
            post(`/sessions/${sessionId}/calendar/${action}`, { document_id: item.document_id });
        }
    }

    if (!hasCalendarAction && !canRefer && !canEditReferral) {
        if (item.placed_on_third_reading) {
            return <span className="text-xs text-ink-muted">{t('sessions.calendar.placed_on_third')}</span>;
        }

        return null;
    }

    return (
        <>
            {canRefer ? (
                <Button type="button" size="sm" variant={forward} onClick={() => setReferOpen(true)}>
                    <Scale aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                    {t('documents.refer_title')}
                </Button>
            ) : null}
            {canEditReferral ? (
                <Button type="button" size="sm" variant="secondary" onClick={() => setReferOpen(true)}>
                    <Pencil aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                    {t('documents.refer_edit')}
                </Button>
            ) : null}
            {item.can_second_reading ? (
                <Button type="button" size="sm" variant={forward} onClick={() => setPending('second-reading')}>
                    <BookOpen aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                    {t('sessions.calendar.second_reading')}
                </Button>
            ) : null}
            {item.can_third_reading ? (
                <Button type="button" size="sm" variant={forward} onClick={() => setPending('third-reading')}>
                    <ScrollText aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                    {t('sessions.calendar.third_reading')}
                </Button>
            ) : null}
            {item.can_postpone ? (
                <Button type="button" size="sm" variant="secondary" onClick={() => setPending('postpone')}>
                    <Timer aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                    {t('sessions.calendar.postpone')}
                </Button>
            ) : null}
            {item.can_undo && agendaItemId ? (
                <Button type="button" size="sm" variant="ghost" onClick={() => setPending('undo')}>
                    <RotateCcw aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                    {t('sessions.calendar.undo')}
                </Button>
            ) : null}

            <Dialog
                open={pending !== null}
                onOpenChange={(open) => {
                    if (!open && !processing) {
                        setPending(null);
                    }
                }}
            >
                <DialogContent
                    title={itemConfirmCopy(pending, t).title}
                    description={itemConfirmCopy(pending, t).hint}
                >
                    <DialogFooter className="mt-0">
                        <Button type="button" variant="ghost" disabled={processing} onClick={() => setPending(null)}>
                            {t('sessions.cancel')}
                        </Button>
                        <Button
                            type="button"
                            variant="primary"
                            disabled={processing}
                            onClick={() => {
                                if (pending) {
                                    perform(pending);
                                }
                            }}
                        >
                            {t('sessions.calendar.confirm_continue')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {item.document?.slug && (canRefer || canEditReferral) ? (
                <ReferToCommitteeDialog
                    documentSlug={item.document.slug}
                    committeeId={item.document.committee_id ?? null}
                    committeeIds={item.document.open_referral?.committee_ids}
                    meetingOn={item.document.open_referral?.meeting_on}
                    remarks={item.document.open_referral?.remarks}
                    committees={committees}
                    open={referOpen}
                    onOpenChange={setReferOpen}
                    action={`/sessions/${sessionId}/floor/refer`}
                    agendaItemId={referralAgendaItemId ?? undefined}
                    mode={canEditReferral ? 'edit' : 'create'}
                    idPrefix={`docket-refer-${item.id}`}
                />
            ) : null}
        </>
    );
}

function CalendarDocketRow({
    sessionId,
    item,
    t,
    committees,
    selectable,
    showSelection,
    selected,
    onToggle,
}: {
    sessionId: string;
    item: CalendarDocketItem;
    t: Translate;
    committees: { id: string; name: string }[];
    selectable: boolean;
    showSelection: boolean;
    selected: boolean;
    onToggle: () => void;
}) {
    const title = item.document?.title ?? item.title;
    const reference = item.agenda_item_id ? formatAgendaNumber(item.item_number) : (item.reference_number ?? '');
    const hasActions = itemHasAction(item) || Boolean(item.placed_on_third_reading) || Boolean(item.can_refer) || Boolean(item.can_edit_referral);
    const carryNote =
        item.status === 'postponed'
            ? item.carried_to
                ? t('sessions.calendar.carried_to', { sitting: item.carried_to.session_number })
                : t('sessions.calendar.queued')
            : null;
    const { formatList } = useFormatters();
    const referredNames = referredCommitteeNames(item.document);
    const referredNote =
        referredNames.length > 0 ? t('sessions.floor.referred_to', { committee: formatList(referredNames) }) : null;

    const measureBody = (
        <>
            <span
                className={cn(
                    'flex size-8 shrink-0 items-center justify-center rounded-[var(--radius-md)] border text-ink-faint shadow-[var(--shadow-xs)]',
                    selected ? 'border-accent-line bg-surface text-accent' : 'border-line bg-surface-alt',
                )}
                aria-hidden="true"
            >
                <ScrollText strokeWidth={1.75} className="size-4" />
            </span>
            <div className="min-w-0 flex-1">
                {reference ? (
                    <p className="font-mono text-xs tracking-wide text-ink-subtle">{reference}</p>
                ) : null}
                <p className="text-sm font-semibold leading-snug text-ink">{title}</p>
                {referredNote ? <p className="mt-0.5 text-xs text-ink-muted">{referredNote}</p> : null}
                {carryNote ? <p className="mt-0.5 text-xs text-ink-muted">{carryNote}</p> : null}
            </div>
        </>
    );

    const statusAndActions = (
        <span className="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
            <StatusChip tone={docketTone(item.status)} size="sm">
                {docketStatusLabel(item.status, t)}
            </StatusChip>
            {hasActions ? <CalendarItemActions sessionId={sessionId} item={item} t={t} emphasis committees={committees} /> : null}
        </span>
    );

    return (
        <li
            className={cn(
                'relative flex items-start gap-3 border-b border-line px-5 py-3 last:border-b-0',
                'transition-colors duration-[var(--duration-fast)]',
                selected ? 'bg-accent-soft' : 'hover:bg-surface-alt',
            )}
        >
            {selected ? (
                <span aria-hidden="true" className="absolute inset-y-0 left-0 w-0.5 bg-accent" />
            ) : null}
            {showSelection ? (
                selectable ? (
                    <>
                        <Checkbox
                            checked={selected}
                            onChange={onToggle}
                            aria-label={t('sessions.calendar.select_measure', { title })}
                            className="mt-1.5 shrink-0"
                        />
                        <label className="flex min-w-0 flex-1 cursor-pointer items-start gap-3">{measureBody}</label>
                    </>
                ) : (
                    <>
                        <span className="mt-1.5 size-4 shrink-0" aria-hidden="true" />
                        <div className="flex min-w-0 flex-1 items-start gap-3">{measureBody}</div>
                    </>
                )
            ) : (
                <div className="flex min-w-0 flex-1 items-start gap-3">{measureBody}</div>
            )}
            {statusAndActions}
        </li>
    );
}

function itemHasAction(item: CalendarDocketItem): boolean {
    return Boolean(item.can_second_reading || item.can_third_reading || item.can_postpone || item.can_undo);
}

function itemAllows(item: CalendarDocketItem, action: CalendarRouteAction): boolean {
    if (action === 'second-reading') {
        return Boolean(item.can_second_reading);
    }

    if (action === 'postpone') {
        return Boolean(item.can_postpone);
    }

    if (action === 'third-reading') {
        return Boolean(item.can_third_reading);
    }

    return Boolean(item.can_undo);
}

function bulkItemPayload(item: CalendarDocketItem): { agenda_item_id?: string; document_id?: string } {
    const payload: { agenda_item_id?: string; document_id?: string } = {};

    if (item.agenda_item_id) {
        payload.agenda_item_id = item.agenda_item_id;
    }

    if (item.document_id) {
        payload.document_id = item.document_id;
    }

    return payload;
}

function actionPath(action: CalendarRouteAction): string {
    return action === 'undo' ? 'undo-postpone' : action;
}

function itemConfirmCopy(action: CalendarRouteAction | null, t: Translate): { title: string; hint: string } {
    if (action === 'postpone') {
        return {
            title: t('sessions.calendar.postpone_confirm_title'),
            hint: t('sessions.calendar.postpone_confirm_hint'),
        };
    }

    if (action === 'third-reading') {
        return {
            title: t('sessions.calendar.third_reading_confirm_title'),
            hint: t('sessions.calendar.third_reading_confirm_hint'),
        };
    }

    if (action === 'undo') {
        return {
            title: t('sessions.calendar.undo_confirm_title'),
            hint: t('sessions.calendar.undo_confirm_hint'),
        };
    }

    return {
        title: t('sessions.calendar.second_reading_confirm_title'),
        hint: t('sessions.calendar.second_reading_confirm_hint'),
    };
}

function bulkConfirmCopy(
    action: CalendarRouteAction | null,
    count: number,
    t: Translate,
): { title: string; hint: string } {
    if (action === 'postpone') {
        return {
            title: t('sessions.calendar.postpone_confirm_title_bulk'),
            hint: t('sessions.calendar.postpone_confirm_hint_bulk', { count }),
        };
    }

    if (action === 'third-reading') {
        return {
            title: t('sessions.calendar.third_reading_confirm_title_bulk'),
            hint: t('sessions.calendar.third_reading_confirm_hint_bulk', { count }),
        };
    }

    if (action === 'undo') {
        return {
            title: t('sessions.calendar.undo_confirm_title_bulk'),
            hint: t('sessions.calendar.undo_confirm_hint_bulk', { count }),
        };
    }

    return {
        title: t('sessions.calendar.second_reading_confirm_title_bulk'),
        hint: t('sessions.calendar.second_reading_confirm_hint_bulk', { count }),
    };
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

    if (status === 'considered') {
        return 'review';
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

    if (status === 'considered') {
        return t('sessions.item_considered');
    }

    if (status === 'postponed') {
        return t('sessions.item_postponed');
    }

    return status;
}
