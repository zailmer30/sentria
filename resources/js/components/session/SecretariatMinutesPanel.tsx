import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/input';
import { Notice } from '@/components/ui/notice';
import { Panel, PanelBody, PanelFoot, PanelHead, PanelTitle } from '@/components/ui/panel';
import { cn } from '@/lib/utils';
import { formatAgendaNumber, type AgendaItem, type Translate } from '@/pages/Sessions/Floor/shared';
import type { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';

type Props = {
    sessionId: string;
    value: string | null | undefined;
    canRecord: boolean;
    currentItem: AgendaItem | null;
    t: Translate;
};

export function SecretariatMinutesPanel({ sessionId, value, canRecord, currentItem, t }: Props) {
    const serverText = value ?? '';
    const [text, setText] = useState(serverText);
    const [baseline, setBaseline] = useState(serverText);
    const [saving, setSaving] = useState(false);
    const pending = useRef(serverText);
    const dirtyRef = useRef(false);
    const { errors } = usePage<PageProps & { errors: Record<string, string> }>().props;

    pending.current = text;
    dirtyRef.current = text !== baseline;
    const dirty = dirtyRef.current;
    const currentLabel = currentItem
        ? [formatAgendaNumber(currentItem.item_number), currentItem.title].filter(Boolean).join(' ')
        : null;

    useEffect(() => {
        if (dirtyRef.current) {
            return;
        }

        setText(serverText);
        setBaseline(serverText);
        pending.current = serverText;
    }, [serverText]);

    useEffect(() => {
        if (!canRecord || !dirty || saving) {
            return;
        }

        const timer = window.setTimeout(() => save(), 1200);

        return () => window.clearTimeout(timer);
        // pending ref carries the latest draft into save()
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [text, dirty, saving, canRecord]);

    function save() {
        if (!canRecord) {
            return;
        }

        const snapshot = pending.current;
        setSaving(true);

        router.put(
            `/sessions/${sessionId}/floor/minutes`,
            { secretariat_minutes: snapshot },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    if (pending.current === snapshot) {
                        setBaseline(snapshot);
                    }
                },
                onFinish: () => setSaving(false),
            },
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        save();
    }

    const status = saving
        ? t('sessions.floor.minutes_saving')
        : dirty
          ? t('sessions.floor.minutes_unsaved')
          : t('sessions.floor.minutes_saved_status');

    return (
        <Panel>
            <form onSubmit={submit}>
                <PanelHead className="items-start gap-3 px-6 py-5">
                    <div className="flex min-w-0 items-start gap-3">
                        <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full border border-line bg-canvas-sunk text-ink-muted">
                            <FileText aria-hidden="true" strokeWidth={1.75} className="size-4" />
                        </span>
                        <div className="min-w-0">
                            <PanelTitle className="text-base tracking-[-0.02em]">
                                {t('sessions.floor.minutes_heading')}
                            </PanelTitle>
                            <p id="minutes-hint" className="mt-1 max-w-xl text-sm leading-5 text-ink-muted">
                                {t('sessions.floor.minutes_hint')}
                            </p>
                            {currentLabel ? (
                                <p className="mt-2.5 truncate text-xs text-ink-subtle">
                                    {t('sessions.floor.minutes_current', { item: currentLabel })}
                                </p>
                            ) : null}
                        </div>
                    </div>
                    <Badge
                        variant={dirty ? 'warning' : 'secondary'}
                        className="rounded-full px-2.5"
                        aria-live="polite"
                    >
                        {status}
                    </Badge>
                </PanelHead>

                <PanelBody className="space-y-4 px-6 pt-2 pb-5">
                    {!canRecord ? (
                        <Notice tone="caution" compact>
                            {t('sessions.floor.minutes_readonly')}
                        </Notice>
                    ) : null}

                    <label htmlFor="secretariat_minutes" className="sr-only">
                        {t('sessions.floor.minutes_record')}
                    </label>
                    <Textarea
                        id="secretariat_minutes"
                        value={text}
                        rows={18}
                        disabled={!canRecord}
                        placeholder={t('sessions.floor.minutes_placeholder')}
                        aria-describedby="minutes-hint"
                        aria-invalid={Boolean(errors.secretariat_minutes)}
                        className={cn(
                            'min-h-112 resize-y rounded-lg border-line bg-canvas-sunk px-4 py-3.5 text-base leading-7 shadow-none',
                            'placeholder:text-ink-faint',
                        )}
                        onChange={(event) => setText(event.target.value)}
                    />
                    {errors.secretariat_minutes ? (
                        <p className="text-xs font-medium text-critical">{errors.secretariat_minutes}</p>
                    ) : null}
                </PanelBody>

                {canRecord ? (
                    <PanelFoot className="justify-end border-line bg-transparent px-6 py-4">
                        <Button type="submit" variant="primary" disabled={saving || !dirty}>
                            {saving ? t('sessions.floor.minutes_saving') : t('sessions.floor.minutes_save')}
                        </Button>
                    </PanelFoot>
                ) : null}
            </form>
        </Panel>
    );
}
