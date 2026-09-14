import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/input';
import { Panel, PanelBody, PanelHead, PanelTitle } from '@/components/ui/panel';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { PrivateNoteRow, ReadingPackItem } from '@/pages/Sessions/Floor/shared';
import { useForm } from '@inertiajs/react';
import { PanelRightClose, Sparkles } from 'lucide-react';
import { FormEvent, useEffect } from 'react';

type MemberActionRailProps = {
    sessionId: string;
    selectedItem: ReadingPackItem | null;
    privateNotes: PrivateNoteRow[];
    className?: string;
    canUseAssistant?: boolean;
    onOpenAssistant?: () => void;
    /** Desktop only. The sheet host already has its own close control. */
    onHide?: () => void;
};

export function MemberActionRail({
    sessionId,
    selectedItem,
    privateNotes,
    className,
    canUseAssistant = false,
    onOpenAssistant,
    onHide,
}: MemberActionRailProps) {
    const { t } = useTranslations();
    const document = selectedItem?.document ?? null;
    const notableType = document ? 'App\\Models\\Document' : 'App\\Models\\LegislativeSession';
    const notableId = document?.id ?? sessionId;

    const noteForm = useForm({
        notable_type: notableType,
        notable_id: notableId,
        body: '',
    });

    useEffect(() => {
        noteForm.setData({
            notable_type: notableType,
            notable_id: notableId,
            body: noteForm.data.body,
        });
        // Only re-target when the notable changes; body must stay editable.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [notableType, notableId]);

    const visibleNotes = privateNotes.filter((note) => {
        if (document) {
            return note.notable_type === 'App\\Models\\Document' && note.notable_id === document.id;
        }

        return note.notable_type === 'App\\Models\\LegislativeSession' && note.notable_id === sessionId;
    });

    function saveNote(event: FormEvent) {
        event.preventDefault();
        noteForm.post('/private-notes', {
            preserveScroll: true,
            onSuccess: () => noteForm.setData('body', ''),
        });
    }

    const itemLabel = selectedItem?.item_number ?? selectedItem?.title;
    const placeholder = itemLabel
        ? t('sessions.reading.note_on_item', { item: itemLabel })
        : t('sessions.note_placeholder');

    return (
        <aside
            id={onHide ? 'member-notes-rail' : undefined}
            className={cn('flex h-full min-h-0 flex-col gap-3', className)}
        >
            <Panel as="section" className="flex min-h-0 flex-1 flex-col overflow-hidden">
                <PanelHead className="flex-nowrap items-start">
                    <div className="min-w-0 flex-1">
                        <PanelTitle>{t('sessions.my_notes')}</PanelTitle>
                        <p className="mt-0.5 text-xs text-ink-muted">{t('sessions.reading.notes_private')}</p>
                    </div>
                    {onHide ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            className="hidden shrink-0 xl:inline-flex"
                            onClick={onHide}
                            aria-label={t('sessions.reading.hide_notes')}
                            title={t('sessions.reading.hide_notes')}
                            aria-expanded="true"
                            aria-controls="member-notes-rail"
                        >
                            <PanelRightClose aria-hidden strokeWidth={1.75} className="size-4" />
                        </Button>
                    ) : null}
                </PanelHead>
                <PanelBody className="flex min-h-0 flex-1 flex-col overflow-y-auto">
                    {visibleNotes.length > 0 ? (
                        <ul className="space-y-2">
                            {visibleNotes.map((note) => (
                                <li
                                    key={note.id}
                                    className="rounded-[var(--radius-sm)] bg-canvas-sunk px-3 py-2 text-sm text-ink"
                                >
                                    {note.body}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-ink-muted">{t('sessions.reading.notes_empty')}</p>
                    )}
                    <form onSubmit={saveNote} className="mt-4 flex flex-1 flex-col gap-3">
                        <Textarea
                            value={noteForm.data.body}
                            onChange={(e) => noteForm.setData('body', e.target.value)}
                            rows={5}
                            placeholder={placeholder}
                            className="min-h-24 flex-1"
                        />
                        <div className="flex justify-end">
                            <Button type="submit" variant="primary" size="sm" disabled={noteForm.processing}>
                                {t('sessions.save_note')}
                            </Button>
                        </div>
                    </form>
                </PanelBody>
            </Panel>

            {canUseAssistant && onOpenAssistant ? (
                <button
                    type="button"
                    onClick={onOpenAssistant}
                    className="flex shrink-0 items-start gap-3 rounded-[var(--radius-lg)] border border-[var(--color-accent-line)] bg-accent-soft px-4 py-3 text-left text-accent shadow-[var(--shadow-xs)] transition-colors hover:bg-accent-soft/80"
                >
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-[var(--radius-md)] bg-accent text-[var(--color-accent-on)]">
                        <Sparkles aria-hidden className="size-4" strokeWidth={1.75} />
                    </span>
                    <span className="min-w-0">
                        <span className="block text-sm font-semibold text-accent-ink">{t('sessions.assistant.open')}</span>
                        <span className="mt-0.5 block text-xs leading-5 text-accent-ink/80">
                            {t('sessions.assistant.invite')}
                        </span>
                    </span>
                </button>
            ) : null}
        </aside>
    );
}
