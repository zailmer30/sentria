import { DocumentPreviewDialog } from '@/components/documents/DocumentPreviewDialog';
import { CalendarItemActions } from '@/components/session/CalendarDocket';
import { MinutesCorrectionsPanel } from '@/components/session/MinutesCorrectionsPanel';
import { Button } from '@/components/ui/button';
import { FileDrop } from '@/components/ui/file-drop';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Field, fieldAria } from '@/components/ui/field';
import { Input, Textarea } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { StatusChip, toneForState, type StatusTone } from '@/components/ui/status';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
    type UniqueIdentifier,
} from '@dnd-kit/core';
import { restrictToParentElement, restrictToVerticalAxis } from '@dnd-kit/modifiers';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Link, router, useForm } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    ChevronUp,
    Eye,
    GripVertical,
    Paperclip,
    Pencil,
    Plus,
    SquareArrowOutUpRight,
    Trash2,
} from 'lucide-react';
import { FormEvent, useEffect, useMemo, useState } from 'react';

type DocumentOption = {
    id: string;
    title: string;
    reference_number: string | null;
    status?: string;
    document_type?: string;
    current_reading?: number | null;
    suggested?: boolean;
    source_session?: { id: string; session_number: string; title: string } | null;
};

export type MinutesConsideration = {
    previous_session: { id: string; session_number: string; title: string } | null;
    sessions: { id: string; session_number: string; title: string }[];
    suggested_document_ids: string[];
    documents: DocumentOption[];
};

export type AgendaLinkedDocument = {
    id?: string;
    slug: string;
    title: string;
    version_id?: string | null;
    mime_type?: string | null;
    can_preview?: boolean;
    preview_url?: string | null;
};

export type AgendaListItem = {
    id: string;
    parent_id?: string | null;
    position: number;
    item_number?: string | null;
    title: string;
    description?: string | null;
    category?: string | null;
    status: string;
    document_id?: string | null;
    document?: AgendaLinkedDocument | null;
    can_second_reading?: boolean;
    can_third_reading?: boolean;
    can_postpone?: boolean;
    can_undo?: boolean;
    carried_to?: { id: string; session_number: string; title: string } | null;
    minutes_corrections?: import('@/components/session/MinutesCorrectionsPanel').MinutesCorrectionRow[];
};

type Props = {
    sessionId: string;
    items: AgendaListItem[];
    documents: DocumentOption[];
    locked?: boolean;
};

const NONE = '__none';

const ATTACHABLE_CATEGORIES = [
    'first-reading',
    'committee-reports',
    'unfinished-business',
    'business-for-the-day',
    'unassigned-business',
    'third-reading',
    'approval-minutes',
    'referred-measures',
] as const;

const MEASURE_TYPES = ['proposed-ordinance', 'proposed-resolution', 'ordinance', 'resolution'];
const ORDINANCE_MEASURE_TYPES = ['proposed-ordinance', 'ordinance'];

export function AgendaBuilderToolbar({
    sessionId,
    documents,
    locked = false,
}: Omit<Props, 'items'> & { items?: AgendaListItem[] }) {
    const { t } = useTranslations();
    const [open, setOpen] = useState(false);

    if (locked) {
        return null;
    }

    return (
        <>
            <Button type="button" size="sm" variant="secondary" onClick={() => setOpen(true)}>
                <Plus aria-hidden="true" strokeWidth={2} className="size-4" />
                {t('sessions.agenda_add')}
            </Button>
            <AddAgendaItemDialog sessionId={sessionId} documents={documents} open={open} onOpenChange={setOpen} />
        </>
    );
}

/**
 * The order of business is a sequence the chamber can still rewrite until the
 * sitting is closed. Dragging a row is the primary rewrite; the chevrons remain
 * for a single-step move without picking the item up.
 */
export function AgendaList({
    sessionId,
    items: serverItems,
    documents = [],
    editable = false,
    minutesConsideration = null,
    applyCorrections = false,
}: {
    sessionId: string;
    items: AgendaListItem[];
    documents?: DocumentOption[];
    editable?: boolean;
    minutesConsideration?: MinutesConsideration | null;
    applyCorrections?: boolean;
}) {
    const { t } = useTranslations();
    const [items, setItems] = useState(serverItems);
    const [prevServerItems, setPrevServerItems] = useState(serverItems);
    const [activeId, setActiveId] = useState<UniqueIdentifier | null>(null);
    const [preview, setPreview] = useState<AgendaLinkedDocument | null>(null);
    const sortable = editable && items.length > 1;
    // One item with a linked measure reserves the column for all of them, so
    // the status pills still line up down the list.
    const documented = items.some((item) => Boolean(item.document));
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    if (serverItems !== prevServerItems) {
        setPrevServerItems(serverItems);
        setItems(serverItems);
    }

    const activeItem = items.find((item) => item.id === activeId) ?? null;

    function persist(next: AgendaListItem[]) {
        const numbered = withPositions(next);
        setItems(numbered);
        router.post(
            `/sessions/${sessionId}/agenda/reorder`,
            { ordered_ids: numbered.map((item) => item.id) },
            {
                preserveScroll: true,
                onError: () => setItems(serverItems),
            },
        );
    }

    function move(itemId: string, direction: 'up' | 'down') {
        const index = items.findIndex((item) => item.id === itemId);
        const swapWith = direction === 'up' ? index - 1 : index + 1;

        if (index < 0 || swapWith < 0 || swapWith >= items.length) {
            return;
        }

        const next = [...items];
        const current = next[index];
        const other = next[swapWith];

        if (!current || !other) {
            return;
        }

        next[index] = other;
        next[swapWith] = current;
        persist(next);
    }

    function onDragEnd(event: DragEndEvent) {
        setActiveId(null);

        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        const oldIndex = items.findIndex((item) => item.id === active.id);
        const newIndex = items.findIndex((item) => item.id === over.id);

        if (oldIndex < 0 || newIndex < 0 || oldIndex === newIndex) {
            return;
        }

        persist(arrayMove(items, oldIndex, newIndex));
    }

    return (
        <>
            <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                modifiers={[restrictToVerticalAxis, restrictToParentElement]}
                accessibility={{
                    screenReaderInstructions: { draggable: t('sessions.agenda_drag_instructions') },
                    announcements: {
                        onDragStart({ active }) {
                            return t('sessions.agenda_drag_picked', { title: titleFor(items, active.id) });
                        },
                        onDragOver({ active, over }) {
                            if (!over) {
                                return;
                            }

                            return t('sessions.agenda_drag_moved', {
                                title: titleFor(items, active.id),
                                position: positionFor(items, over.id),
                            });
                        },
                        onDragEnd({ active, over }) {
                            if (!over) {
                                return t('sessions.agenda_drag_cancelled');
                            }

                            return t('sessions.agenda_drag_dropped', {
                                title: titleFor(items, active.id),
                                position: positionFor(items, over.id),
                            });
                        },
                        onDragCancel() {
                            return t('sessions.agenda_drag_cancelled');
                        },
                    },
                }}
                onDragStart={({ active }) => setActiveId(active.id)}
                onDragCancel={() => setActiveId(null)}
                onDragEnd={onDragEnd}
            >
                <ol className="divide-y divide-line">
                    <SortableContext items={items.map((item) => item.id)} strategy={verticalListSortingStrategy}>
                        {items.map((item) => (
                            <AgendaRow
                                key={item.id}
                                sessionId={sessionId}
                                item={item}
                                items={items}
                                documents={
                                    item.category === 'approval-minutes'
                                        ? (minutesConsideration?.documents ?? documents)
                                        : documents
                                }
                                editable={editable}
                                sortable={sortable}
                                documented={documented}
                                minutesConsideration={minutesConsideration}
                                applyCorrections={applyCorrections}
                                onMove={move}
                                onView={(document) => setPreview(document)}
                            />
                        ))}
                    </SortableContext>
                </ol>
                <DragOverlay>
                    {activeItem ? (
                        <div className="flex items-center gap-3 rounded-[var(--radius-md)] border border-line bg-surface px-4 py-2.5 shadow-[var(--shadow-md)]">
                            <GripVertical aria-hidden="true" strokeWidth={2} className="size-4 text-ink-faint" />
                            <span className="text-sm font-medium text-ink">{activeItem.title}</span>
                        </div>
                    ) : null}
                </DragOverlay>
            </DndContext>
            <DocumentPreviewDialog
                open={preview !== null}
                onOpenChange={(open) => !open && setPreview(null)}
                title={preview?.title ?? t('documents.view')}
                src={preview?.preview_url ?? null}
            />
        </>
    );
}

function AgendaRow({
    sessionId,
    item,
    items,
    documents,
    editable,
    sortable,
    documented,
    minutesConsideration,
    applyCorrections,
    onMove,
    onView,
}: {
    sessionId: string;
    item: AgendaListItem;
    items: AgendaListItem[];
    documents: DocumentOption[];
    editable: boolean;
    sortable: boolean;
    documented: boolean;
    minutesConsideration: MinutesConsideration | null;
    applyCorrections: boolean;
    onMove: (itemId: string, direction: 'up' | 'down') => void;
    onView: (document: AgendaLinkedDocument) => void;
}) {
    const { t } = useTranslations();
    const [bindOpen, setBindOpen] = useState(false);
    const current = item.status === 'in-progress';
    const canDrag = sortable && item.status !== 'completed' && item.status !== 'in-progress' && item.status !== 'postponed';
    const depth = nestingDepth(item, items);
    const attachable = editable && isAttachableHeading(item);
    const linked = item.document ?? null;
    const isMinutesHeading = item.category === 'approval-minutes' && !item.document_id && !item.document;
    const hasMinutesChild = items.some((row) => row.parent_id === item.id && Boolean(row.document_id || row.document));
    const isMinutesPacket = item.category === 'approval-minutes' && Boolean(item.document_id || item.document);
    const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
        id: item.id,
        disabled: !canDrag,
    });

    return (
        <li
            ref={setNodeRef}
            className={cn(
                'flex flex-wrap items-center gap-x-3 gap-y-2 px-5 py-2.5 transition-colors duration-[var(--duration-fast)]',
                current ? 'bg-live-soft' : 'hover:bg-surface-alt',
                isDragging && 'relative z-10 bg-surface opacity-40',
            )}
            style={{
                transform: CSS.Transform.toString(transform),
                transition,
                paddingLeft: depth > 0 ? `${1.25 + depth * 1.25}rem` : undefined,
            }}
        >
            {sortable ? (
                <Button
                    ref={canDrag ? setActivatorNodeRef : undefined}
                    type="button"
                    size="icon-sm"
                    variant="ghost"
                    disabled={!canDrag}
                    className={cn('touch-none text-ink-faint', canDrag && 'cursor-grab active:cursor-grabbing')}
                    {...(canDrag ? { ...attributes, ...listeners } : {})}
                    aria-label={t('sessions.agenda_drag')}
                >
                    <GripVertical aria-hidden="true" strokeWidth={2} className="size-4" />
                </Button>
            ) : null}
            <span className="w-10 shrink-0 font-mono text-xs text-ink-faint">
                {item.item_number ?? String(item.position).padStart(2, '0')}
            </span>
            <div className="min-w-0 flex-1">
                <p className="flex items-center gap-2 text-sm font-medium text-ink">{item.title}</p>
                {item.description ? (
                    <p className="mt-0.5 text-xs whitespace-pre-wrap text-ink-muted">{item.description}</p>
                ) : null}
                {isMinutesHeading && editable && !hasMinutesChild ? (
                    <p className="mt-0.5 text-xs text-ink-muted">{t('sessions.agenda_minutes_missing')}</p>
                ) : null}
            </div>
            {documented ? (
                <span className="flex shrink-0 items-center justify-end gap-2 sm:min-w-32">
                    {linked?.can_preview && linked.preview_url ? (
                        <Button type="button" size="sm" variant="secondary" onClick={() => onView(linked)}>
                            <Eye aria-hidden="true" strokeWidth={2} className="size-3.5" />
                            {t('documents.view')}
                        </Button>
                    ) : null}
                    {linked ? (
                        <Button variant="secondary" size="sm" asChild>
                            <Link href={`/documents/${linked.slug}`}>
                                <SquareArrowOutUpRight aria-hidden="true" strokeWidth={2} className="size-3.5" />
                                {t('sessions.open_document')}
                            </Link>
                        </Button>
                    ) : null}
                </span>
            ) : null}
            <span className="flex shrink-0 justify-end sm:min-w-24">
                <StatusChip tone={agendaTone(item.status)} size="sm">
                    {agendaStatusLabel(item.status, t)}
                </StatusChip>
            </span>
            {editable ? (
                <span className="flex shrink-0 items-center justify-end gap-0.5 sm:min-w-25">
                    {attachable ? (
                        <Button
                            type="button"
                            size="icon-sm"
                            variant="ghost"
                            onClick={() => setBindOpen(true)}
                            aria-label={
                                isMinutesHeading ? t('sessions.agenda_bind_minutes') : t('sessions.agenda_bind')
                            }
                        >
                            <Paperclip aria-hidden="true" strokeWidth={2} className="size-4" />
                        </Button>
                    ) : null}
                    <CalendarItemActions sessionId={sessionId} item={item} />
                    <AgendaItemActions sessionId={sessionId} item={item} items={items} onMove={onMove} />
                    {attachable ? (
                        <BindDocumentsDialog
                            sessionId={sessionId}
                            heading={item}
                            documents={documents}
                            minutesConsideration={isMinutesHeading ? minutesConsideration : null}
                            open={bindOpen}
                            onOpenChange={setBindOpen}
                        />
                    ) : null}
                </span>
            ) : null}
            {applyCorrections && isMinutesPacket ? (
                <div className="basis-full pt-2">
                    <MinutesCorrectionsPanel
                        sessionId={sessionId}
                        agendaItemId={item.id}
                        corrections={item.minutes_corrections ?? []}
                        canApply
                        compact
                        t={t}
                    />
                </div>
            ) : null}
        </li>
    );
}

export function AgendaItemActions({
    sessionId,
    item,
    items,
    locked = false,
    onMove,
}: {
    sessionId: string;
    item: AgendaListItem;
    items: AgendaListItem[];
    locked?: boolean;
    onMove?: (itemId: string, direction: 'up' | 'down') => void;
}) {
    const { t } = useTranslations();
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [editOpen, setEditOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    if (locked || item.status === 'completed' || item.status === 'in-progress' || item.status === 'postponed' || item.status === 'considered') {
        return null;
    }

    const index = items.findIndex((row) => row.id === item.id);
    const canMoveUp = index > 0;
    const canMoveDown = index >= 0 && index < items.length - 1;

    function reorder(direction: 'up' | 'down') {
        if (onMove) {
            onMove(item.id, direction);
            return;
        }

        if (index < 0) {
            return;
        }

        const next = [...items];
        const swapWith = direction === 'up' ? index - 1 : index + 1;

        if (swapWith < 0 || swapWith >= next.length) {
            return;
        }

        const current = next[index];
        const other = next[swapWith];

        if (!current || !other) {
            return;
        }

        next[index] = other;
        next[swapWith] = current;

        router.post(
            `/sessions/${sessionId}/agenda/reorder`,
            { ordered_ids: next.map((row) => row.id) },
            { preserveScroll: true },
        );
    }

    function remove() {
        router.delete(`/sessions/${sessionId}/agenda/${item.id}`, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setConfirmOpen(false);
            },
        });
    }

    // No wrapper: the row reserves the column so the pills above and below a
    // locked item still line up with this one's controls.
    return (
        <>
            <Button
                type="button"
                size="icon-sm"
                variant="ghost"
                disabled={!canMoveUp}
                onClick={() => reorder('up')}
                aria-label={t('sessions.agenda_move_up')}
            >
                <ChevronUp aria-hidden="true" strokeWidth={2} className="size-4" />
            </Button>
            <Button
                type="button"
                size="icon-sm"
                variant="ghost"
                disabled={!canMoveDown}
                onClick={() => reorder('down')}
                aria-label={t('sessions.agenda_move_down')}
            >
                <ChevronDown aria-hidden="true" strokeWidth={2} className="size-4" />
            </Button>
            <Button
                type="button"
                size="icon-sm"
                variant="ghost"
                onClick={() => setEditOpen(true)}
                aria-label={t('sessions.agenda_edit')}
            >
                <Pencil aria-hidden="true" strokeWidth={2} className="size-4" />
            </Button>
            <Button
                type="button"
                size="icon-sm"
                variant="ghost"
                onClick={() => setConfirmOpen(true)}
                aria-label={t('sessions.agenda_remove')}
                className="hover:bg-critical-soft hover:text-critical"
            >
                <Trash2 aria-hidden="true" strokeWidth={2} className="size-4" />
            </Button>

            <EditAgendaItemDialog sessionId={sessionId} item={item} open={editOpen} onOpenChange={setEditOpen} />

            <Dialog
                open={confirmOpen}
                onOpenChange={(open) => {
                    if (!open && !processing) {
                        setConfirmOpen(false);
                    }
                }}
            >
                <DialogContent
                    title={t('sessions.agenda_remove_title')}
                    description={t('sessions.agenda_remove_confirm', { title: item.title })}
                >
                    <DialogFooter className="mt-0">
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={processing}
                            onClick={() => setConfirmOpen(false)}
                        >
                            {t('sessions.cancel')}
                        </Button>
                        <Button type="button" variant="danger" disabled={processing} onClick={remove}>
                            {t('sessions.agenda_remove')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function EditAgendaItemDialog({
    sessionId,
    item,
    open,
    onOpenChange,
}: {
    sessionId: string;
    item: AgendaListItem;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslations();
    const form = useForm({
        title: item.title,
        description: item.description ?? '',
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({
            title: item.title,
            description: item.description ?? '',
        });
        form.clearErrors();
        // Refresh from the item when the dialog opens. `form` is a new object each render.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, item.id, item.title, item.description]);

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            title: data.title.trim(),
            description: data.description.trim() === '' ? null : data.description.trim(),
        }));
        form.put(`/sessions/${sessionId}/agenda/${item.id}`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent title={t('sessions.agenda_edit_title')} description={t('sessions.agenda_edit_hint')}>
                <form onSubmit={submit} className="space-y-4">
                    <Field
                        id={`agenda_edit_title_${item.id}`}
                        label={t('sessions.agenda_item_title')}
                        error={form.errors.title}
                        required
                    >
                        <Input
                            {...fieldAria(`agenda_edit_title_${item.id}`, { error: form.errors.title })}
                            value={form.data.title}
                            onChange={(event) => form.setData('title', event.target.value)}
                        />
                    </Field>
                    <Field
                        id={`agenda_edit_description_${item.id}`}
                        label={t('sessions.agenda_item_description')}
                        hint={t('sessions.agenda_item_description_hint')}
                        error={form.errors.description}
                    >
                        <Textarea
                            {...fieldAria(`agenda_edit_description_${item.id}`, {
                                hint: t('sessions.agenda_item_description_hint'),
                                error: form.errors.description,
                            })}
                            rows={4}
                            value={form.data.description}
                            onChange={(event) => form.setData('description', event.target.value)}
                        />
                    </Field>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            {t('sessions.cancel')}
                        </Button>
                        <Button type="submit" variant="primary" disabled={form.processing || !form.data.title.trim()}>
                            {form.processing ? t('sessions.saving') : t('sessions.agenda_edit_save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AddAgendaItemDialog({
    sessionId,
    documents,
    open,
    onOpenChange,
}: {
    sessionId: string;
    documents: DocumentOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslations();
    const form = useForm({
        title: '',
        document_id: NONE,
        requires_vote: false,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            title: data.title,
            document_id: data.document_id === NONE ? null : data.document_id,
            requires_vote: data.requires_vote,
        }));
        form.post(`/sessions/${sessionId}/agenda`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent title={t('sessions.agenda_add_title')} description={t('sessions.agenda_add_hint')}>
                <form onSubmit={submit} className="space-y-4">
                    <Field id="agenda_title" label={t('sessions.agenda_item_title')} error={form.errors.title} required>
                        <Input
                            {...fieldAria('agenda_title', { error: form.errors.title })}
                            value={form.data.title}
                            onChange={(event) => form.setData('title', event.target.value)}
                        />
                    </Field>
                    {documents.length > 0 ? (
                        <Field
                            id="agenda_document"
                            label={t('sessions.agenda_link_document')}
                            hint={t('sessions.agenda_link_document_hint')}
                        >
                            <Select value={form.data.document_id} onValueChange={(value) => form.setData('document_id', value)}>
                                <SelectTrigger id="agenda_document">
                                    <SelectValue placeholder={t('sessions.agenda_no_document')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>{t('sessions.agenda_no_document')}</SelectItem>
                                    {documents.map((document) => (
                                        <SelectItem key={document.id} value={document.id}>
                                            {document.reference_number
                                                ? `${document.reference_number} — ${document.title}`
                                                : document.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    ) : null}
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            {t('sessions.cancel')}
                        </Button>
                        <Button type="submit" variant="primary" disabled={form.processing || !form.data.title.trim()}>
                            {form.processing ? t('sessions.saving') : t('sessions.agenda_add_save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function BindDocumentsDialog({
    sessionId,
    heading,
    documents,
    minutesConsideration,
    open,
    onOpenChange,
}: {
    sessionId: string;
    heading: AgendaListItem;
    documents: DocumentOption[];
    minutesConsideration: MinutesConsideration | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslations();
    const minutes = heading.category === 'approval-minutes';
    const [query, setQuery] = useState('');
    const form = useForm({ document_ids: [] as string[] });
    const uploadForm = useForm({
        file: null as File | null,
        title: minutesConsideration?.previous_session
            ? t('sessions.agenda_minutes_title_default', { title: minutesConsideration.previous_session.title })
            : '',
        of_session_id: minutesConsideration?.previous_session?.id ?? '',
    });
    const eligible = useMemo(
        () => documentsForHeading(documents, heading.category ?? ''),
        [documents, heading.category],
    );
    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (needle === '') {
            return eligible;
        }

        return eligible.filter((document) => {
            const haystack = `${document.reference_number ?? ''} ${document.title}`.toLowerCase();

            return haystack.includes(needle);
        });
    }, [eligible, query]);

    function toggle(id: string) {
        const selected = form.data.document_ids.includes(id)
            ? form.data.document_ids.filter((value) => value !== id)
            : [...form.data.document_ids, id];

        form.setData('document_ids', selected);
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        if (form.data.document_ids.length === 0) {
            return;
        }

        form.post(`/sessions/${sessionId}/agenda/${heading.id}/documents`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setQuery('');
                onOpenChange(false);
            },
        });
    }

    function submitUpload(event: FormEvent) {
        event.preventDefault();

        if (!uploadForm.data.file) {
            return;
        }

        uploadForm.post(`/sessions/${sessionId}/agenda/${heading.id}/minutes`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadForm.reset();
                onOpenChange(false);
            },
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    form.reset();
                    uploadForm.reset();
                    setQuery('');
                } else if (minutes && form.data.document_ids.length === 0) {
                    form.setData('document_ids', minutesConsideration?.suggested_document_ids ?? []);
                }

                onOpenChange(next);
            }}
        >
            <DialogContent
                title={minutes ? t('sessions.agenda_bind_minutes_title') : t('sessions.agenda_bind_title')}
                description={minutes ? t('sessions.agenda_bind_minutes_hint') : t('sessions.agenda_bind_hint')}
            >
                {minutes ? (
                    <form onSubmit={submitUpload} className="space-y-3 border-b border-line pb-4">
                        <Field
                            id={`minutes-file-${heading.id}`}
                            label={t('sessions.agenda_minutes_file')}
                            hint={t('sessions.agenda_minutes_file_hint')}
                            error={uploadForm.errors.file}
                            required
                        >
                            <FileDrop
                                id={`minutes-file-${heading.id}`}
                                file={uploadForm.data.file}
                                onFileChange={(file) => {
                                    uploadForm.setData('file', file);
                                    uploadForm.clearErrors('file');
                                }}
                                accept="application/pdf,.pdf"
                                invalid={Boolean(uploadForm.errors.file)}
                                dropLabel={t('documents.file_drop')}
                                browseLabel={t('documents.file_browse')}
                                replaceLabel={t('documents.file_replace')}
                                removeLabel={t('documents.file_remove')}
                            />
                        </Field>
                        <Field id={`minutes-title-${heading.id}`} label={t('sessions.agenda_minutes_title')} error={uploadForm.errors.title}>
                            <Input
                                id={`minutes-title-${heading.id}`}
                                value={uploadForm.data.title}
                                onChange={(event) => uploadForm.setData('title', event.target.value)}
                                placeholder={t('sessions.agenda_minutes_title_placeholder')}
                            />
                        </Field>
                        {minutesConsideration && minutesConsideration.sessions.length > 0 ? (
                            <Field id={`minutes-of-${heading.id}`} label={t('sessions.agenda_minutes_of_session')}>
                                <Select
                                    value={uploadForm.data.of_session_id || '__none'}
                                    onValueChange={(value) =>
                                        uploadForm.setData('of_session_id', value === '__none' ? '' : value)
                                    }
                                >
                                    <SelectTrigger id={`minutes-of-${heading.id}`}>
                                        <SelectValue placeholder={t('sessions.agenda_minutes_of_session')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none">{t('sessions.agenda_no_document')}</SelectItem>
                                        {minutesConsideration.sessions.map((row) => (
                                            <SelectItem key={row.id} value={row.id}>
                                                {row.session_number} — {row.title}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                        ) : null}
                        <DialogFooter>
                            <Button type="submit" variant="secondary" disabled={uploadForm.processing || !uploadForm.data.file}>
                                {uploadForm.processing ? t('sessions.saving') : t('sessions.agenda_minutes_upload_save')}
                            </Button>
                        </DialogFooter>
                    </form>
                ) : null}
                <form onSubmit={submit} className="space-y-4">
                    {eligible.length === 0 ? (
                        <p className="text-sm text-ink-muted">
                            {minutes ? t('sessions.agenda_bind_minutes_empty') : t('sessions.agenda_bind_empty')}
                        </p>
                    ) : (
                        <>
                            <Field id={`bind-search-${heading.id}`} label={t('sessions.agenda_bind_search')}>
                                <Input
                                    id={`bind-search-${heading.id}`}
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    placeholder={t('sessions.agenda_bind_search')}
                                />
                            </Field>
                            <ul className="max-h-64 divide-y divide-line overflow-y-auto rounded-[var(--radius-md)] border border-line">
                                {visible.length === 0 ? (
                                    <li className="px-3 py-4 text-sm text-ink-muted">{t('sessions.agenda_bind_none')}</li>
                                ) : (
                                    visible.map((document) => {
                                        const selected = form.data.document_ids.includes(document.id);

                                        return (
                                            <li key={document.id}>
                                                <button
                                                    type="button"
                                                    onClick={() => toggle(document.id)}
                                                    aria-pressed={selected}
                                                    className={cn(
                                                        'flex w-full items-start gap-3 px-3 py-2.5 text-left transition-colors',
                                                        selected ? 'bg-accent-soft' : 'hover:bg-surface-alt',
                                                    )}
                                                >
                                                    <span
                                                        className={cn(
                                                            'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-[var(--radius-sm)] border',
                                                            selected
                                                                ? 'border-accent bg-accent text-ink-inverse'
                                                                : 'border-line-strong bg-surface text-transparent',
                                                        )}
                                                        aria-hidden="true"
                                                    >
                                                        <Check className="size-3" strokeWidth={2.5} />
                                                    </span>
                                                    <span className="min-w-0 flex-1">
                                                        <span className="block text-sm font-medium text-ink">
                                                            {document.title}
                                                            {document.suggested ? (
                                                                <span className="ml-2 text-xs font-normal text-accent">
                                                                    {t('sessions.agenda_bind_minutes_suggested')}
                                                                </span>
                                                            ) : null}
                                                        </span>
                                                        {document.reference_number ? (
                                                            <span className="mt-0.5 block font-mono text-xs text-ink-faint">
                                                                {document.reference_number}
                                                            </span>
                                                        ) : null}
                                                        {document.source_session ? (
                                                            <span className="mt-0.5 block text-xs text-ink-muted">
                                                                {document.source_session.session_number} —{' '}
                                                                {document.source_session.title}
                                                            </span>
                                                        ) : null}
                                                    </span>
                                                </button>
                                            </li>
                                        );
                                    })
                                )}
                            </ul>
                            {form.data.document_ids.length > 0 ? (
                                <p className="text-xs text-ink-muted">
                                    {t('sessions.agenda_bind_selected', { count: form.data.document_ids.length })}
                                </p>
                            ) : null}
                        </>
                    )}
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            {t('sessions.cancel')}
                        </Button>
                        <Button type="submit" variant="primary" disabled={form.processing || form.data.document_ids.length === 0}>
                            {form.processing ? t('sessions.saving') : t('sessions.agenda_bind_save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function withPositions(items: AgendaListItem[]): AgendaListItem[] {
    return items.map((item, index) => ({
        ...item,
        position: index + 1,
    }));
}

function titleFor(items: AgendaListItem[], id: UniqueIdentifier): string {
    return items.find((item) => item.id === id)?.title ?? String(id);
}

function positionFor(items: AgendaListItem[], id: UniqueIdentifier): number {
    const index = items.findIndex((item) => item.id === id);

    return index < 0 ? 0 : index + 1;
}

/**
 * An agenda item's states are not the session state machine's, so they get
 * their own tones: the item on the floor takes the national red because that
 * is where the chamber is, and the item's row is already tinted to say so.
 */
const AGENDA_TONE: Record<string, StatusTone> = {
    pending: 'draft',
    'in-progress': 'live',
    considered: 'review',
    completed: 'moving',
    postponed: 'review',
};

function agendaTone(status: string): StatusTone {
    return AGENDA_TONE[status] ?? toneForState(status);
}

function agendaStatusLabel(status: string, t: (key: string) => string): string {
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

function isAttachableHeading(item: AgendaListItem): boolean {
    if (item.document_id || item.document) {
        return false;
    }

    if (item.status === 'completed' || item.status === 'in-progress') {
        return false;
    }

    return ATTACHABLE_CATEGORIES.includes(item.category as (typeof ATTACHABLE_CATEGORIES)[number]);
}

function documentsForHeading(documents: DocumentOption[], category: string): DocumentOption[] {
    return documents.filter((document) => {
        const reading = document.current_reading ?? null;

        if (category === 'first-reading') {
            const isMeasure = document.document_type === undefined || MEASURE_TYPES.includes(document.document_type);

            return document.status === 'agenda-inclusion' && isMeasure && (reading === null || reading === 1);
        }

        if (category === 'business-for-the-day') {
            return (
                document.status === undefined ||
                document.status === 'committee-report' ||
                (document.status === 'agenda-inclusion' && reading === 2)
            );
        }

        if (category === 'third-reading') {
            const isOrdinance =
                document.document_type === undefined || ORDINANCE_MEASURE_TYPES.includes(document.document_type);

            return (
                isOrdinance &&
                (document.status === undefined ||
                    document.status === 'final-document' ||
                    (document.status === 'agenda-inclusion' && reading === 3))
            );
        }

        if (category === 'approval-minutes') {
            return document.document_type === 'minutes' && (document.status === undefined || document.status === 'registered');
        }

        if (category === 'referred-measures') {
            const isMeasure = document.document_type === undefined || MEASURE_TYPES.includes(document.document_type);

            return (
                isMeasure &&
                (document.status === undefined ||
                    document.status === 'committee-referral' ||
                    document.status === 'committee-review' ||
                    document.status === 'agenda-inclusion')
            );
        }

        return true;
    });
}

function nestingDepth(item: AgendaListItem, items: AgendaListItem[]): number {
    const byId = new Map(items.map((row) => [row.id, row]));
    let depth = 0;
    let parentId = item.parent_id ?? null;

    while (parentId) {
        depth += 1;
        parentId = byId.get(parentId)?.parent_id ?? null;

        if (depth > 4) {
            break;
        }
    }

    return depth;
}
