import { Button } from '@/components/ui/button';
import { Field, fieldAria } from '@/components/ui/field';
import { Input, Textarea } from '@/components/ui/input';
import { Panel, PanelBody, PanelHead, PanelTitle } from '@/components/ui/panel';
import { cn } from '@/lib/utils';
import { router, useForm } from '@inertiajs/react';
import { Check, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

export type MinutesCorrectionRow = {
    id: string;
    agenda_item_id: string;
    as_written: string;
    should_read: string;
    page_number: number | null;
    recorded_by: string | null;
    applied_at: string | null;
    created_at: string | null;
};

type Translate = (key: string, replacements?: Record<string, string | number>) => string;

type Props = {
    sessionId: string;
    agendaItemId: string;
    corrections: MinutesCorrectionRow[];
    canWrite?: boolean;
    canApply?: boolean;
    t: Translate;
    className?: string;
    compact?: boolean;
};

export function MinutesCorrectionsPanel({
    sessionId,
    agendaItemId,
    corrections,
    canWrite = false,
    canApply = false,
    t,
    className,
    compact = false,
}: Props) {
    const rows = corrections.filter((row) => row.agenda_item_id === agendaItemId);
    const [editingId, setEditingId] = useState<string | null>(null);
    const createForm = useForm({
        as_written: '',
        should_read: '',
        page_number: '' as string,
    });

    function submitNew(event: FormEvent) {
        event.preventDefault();

        createForm.post(`/sessions/${sessionId}/agenda/${agendaItemId}/minutes-corrections`, {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    }

    return (
        <Panel as="section" className={cn(compact && 'shadow-none', className)}>
            <PanelHead className="px-4 py-3">
                <PanelTitle>{t('sessions.minutes_corrections')}</PanelTitle>
            </PanelHead>
            <PanelBody className="space-y-4 px-4 py-4">
                {canWrite ? (
                    <p className="text-xs text-ink-muted">{t('sessions.minutes_corrections_hint')}</p>
                ) : canApply ? (
                    <p className="text-xs text-ink-muted">{t('sessions.minutes_corrections_apply_hint')}</p>
                ) : (
                    <p className="text-xs text-ink-muted">{t('sessions.minutes_corrections_frozen')}</p>
                )}

                {canWrite ? (
                    <form onSubmit={submitNew} className="space-y-3 rounded-[var(--radius-md)] border border-line bg-surface-alt p-3">
                        <Field
                            id={`minutes-as-written-${agendaItemId}`}
                            label={t('sessions.minutes_corrections_as_written')}
                            error={createForm.errors.as_written}
                            required
                        >
                            <Textarea
                                {...fieldAria(`minutes-as-written-${agendaItemId}`, { error: createForm.errors.as_written })}
                                rows={2}
                                value={createForm.data.as_written}
                                onChange={(event) => createForm.setData('as_written', event.target.value)}
                            />
                        </Field>
                        <Field
                            id={`minutes-should-read-${agendaItemId}`}
                            label={t('sessions.minutes_corrections_should_read')}
                            error={createForm.errors.should_read}
                            required
                        >
                            <Textarea
                                {...fieldAria(`minutes-should-read-${agendaItemId}`, { error: createForm.errors.should_read })}
                                rows={2}
                                value={createForm.data.should_read}
                                onChange={(event) => createForm.setData('should_read', event.target.value)}
                            />
                        </Field>
                        <Field
                            id={`minutes-page-${agendaItemId}`}
                            label={t('sessions.minutes_corrections_page')}
                            error={createForm.errors.page_number}
                        >
                            <Input
                                {...fieldAria(`minutes-page-${agendaItemId}`, { error: createForm.errors.page_number })}
                                type="number"
                                min={1}
                                inputMode="numeric"
                                value={createForm.data.page_number}
                                onChange={(event) => createForm.setData('page_number', event.target.value)}
                            />
                        </Field>
                        <Button type="submit" size="sm" variant="secondary" disabled={createForm.processing}>
                            <Plus aria-hidden="true" className="size-3.5" strokeWidth={2} />
                            {createForm.processing ? t('sessions.saving') : t('sessions.minutes_corrections_add')}
                        </Button>
                    </form>
                ) : null}

                {rows.length === 0 ? (
                    <p className="text-sm text-ink-muted">{t('sessions.minutes_corrections_empty')}</p>
                ) : (
                    <ul className="divide-y divide-line rounded-[var(--radius-md)] border border-line">
                        {rows.map((row) => (
                            <li key={row.id} className="px-3 py-3">
                                {editingId === row.id && canWrite ? (
                                    <CorrectionEditor
                                        sessionId={sessionId}
                                        agendaItemId={agendaItemId}
                                        row={row}
                                        t={t}
                                        onDone={() => setEditingId(null)}
                                    />
                                ) : (
                                    <div className="space-y-2">
                                        <p className="text-sm text-ink">
                                            {row.page_number !== null ? (
                                                <span className="mr-2 font-mono text-xs text-ink-faint">
                                                    {t('sessions.minutes_corrections_page_n', { page: row.page_number })}
                                                </span>
                                            ) : null}
                                            <span className="text-ink-muted">{t('sessions.minutes_corrections_as_written')}: </span>
                                            “{row.as_written}”
                                        </p>
                                        <p className="text-sm text-ink">
                                            <span className="text-ink-muted">{t('sessions.minutes_corrections_should_read')}: </span>
                                            “{row.should_read}”
                                        </p>
                                        {row.recorded_by ? (
                                            <p className="text-2xs text-ink-faint">
                                                {t('sessions.minutes_corrections_recorded', { name: row.recorded_by })}
                                            </p>
                                        ) : null}
                                        <div className="flex flex-wrap items-center gap-2">
                                            {canWrite ? (
                                                <>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => setEditingId(row.id)}
                                                    >
                                                        <Pencil aria-hidden="true" className="size-3.5" strokeWidth={2} />
                                                        {t('sessions.minutes_corrections_edit')}
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            router.delete(
                                                                `/sessions/${sessionId}/agenda/${agendaItemId}/minutes-corrections/${row.id}`,
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        <Trash2 aria-hidden="true" className="size-3.5" strokeWidth={2} />
                                                        {t('sessions.minutes_corrections_delete')}
                                                    </Button>
                                                </>
                                            ) : null}
                                            {canApply ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant={row.applied_at ? 'secondary' : 'ghost'}
                                                    onClick={() =>
                                                        router.patch(
                                                            `/sessions/${sessionId}/agenda/${agendaItemId}/minutes-corrections/${row.id}/apply`,
                                                            { applied: !row.applied_at },
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    <Check aria-hidden="true" className="size-3.5" strokeWidth={2} />
                                                    {row.applied_at
                                                        ? t('sessions.minutes_corrections_applied')
                                                        : t('sessions.minutes_corrections_mark_applied')}
                                                </Button>
                                            ) : row.applied_at ? (
                                                <p className="text-2xs font-medium text-ink-muted">
                                                    {t('sessions.minutes_corrections_applied')}
                                                </p>
                                            ) : null}
                                        </div>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </PanelBody>
        </Panel>
    );
}

function CorrectionEditor({
    sessionId,
    agendaItemId,
    row,
    t,
    onDone,
}: {
    sessionId: string;
    agendaItemId: string;
    row: MinutesCorrectionRow;
    t: Translate;
    onDone: () => void;
}) {
    const form = useForm({
        as_written: row.as_written,
        should_read: row.should_read,
        page_number: row.page_number !== null ? String(row.page_number) : '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.put(`/sessions/${sessionId}/agenda/${agendaItemId}/minutes-corrections/${row.id}`, {
            preserveScroll: true,
            onSuccess: () => onDone(),
        });
    }

    return (
        <form onSubmit={submit} className="space-y-3">
            <Field id={`edit-as-${row.id}`} label={t('sessions.minutes_corrections_as_written')} required>
                <Textarea
                    id={`edit-as-${row.id}`}
                    rows={2}
                    value={form.data.as_written}
                    onChange={(event) => form.setData('as_written', event.target.value)}
                />
            </Field>
            <Field id={`edit-should-${row.id}`} label={t('sessions.minutes_corrections_should_read')} required>
                <Textarea
                    id={`edit-should-${row.id}`}
                    rows={2}
                    value={form.data.should_read}
                    onChange={(event) => form.setData('should_read', event.target.value)}
                />
            </Field>
            <Field id={`edit-page-${row.id}`} label={t('sessions.minutes_corrections_page')}>
                <Input
                    id={`edit-page-${row.id}`}
                    type="number"
                    min={1}
                    value={form.data.page_number}
                    onChange={(event) => form.setData('page_number', event.target.value)}
                />
            </Field>
            <div className="flex gap-2">
                <Button type="submit" size="sm" variant="primary" disabled={form.processing}>
                    {form.processing ? t('sessions.saving') : t('sessions.minutes_corrections_save')}
                </Button>
                <Button type="button" size="sm" variant="ghost" onClick={onDone}>
                    {t('sessions.cancel')}
                </Button>
            </div>
        </form>
    );
}
