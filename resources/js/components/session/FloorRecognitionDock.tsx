import { UserAvatar } from '@/components/users/UserAvatar';
import { Button } from '@/components/ui/button';
import { LiveDot } from '@/components/ui/status';
import { useTranslations } from '@/lib/i18n';
import { withHonorific } from '@/lib/sessionFloor';
import { cn } from '@/lib/utils';
import { formatAgendaNumber, type RecognitionRequest, type RecognitionState } from '@/pages/Sessions/Floor/shared';
import { router } from '@inertiajs/react';
import { Gavel } from 'lucide-react';

function itemLabel(row: RecognitionRequest): string {
    return [formatAgendaNumber(row.item_number), row.item_title].filter(Boolean).join(' ');
}

function RecognitionRow({
    sessionId,
    row,
    recognized,
    hall,
}: {
    sessionId: string;
    row: RecognitionRequest;
    recognized: boolean;
    hall: boolean;
}) {
    const { t } = useTranslations();
    const name = withHonorific(row.display_name) ?? row.display_name ?? '';
    const item = itemLabel(row);
    const actions = row.can ?? {};

    function post(action: 'cancel' | 'recognize' | 'dismiss') {
        router.post(`/sessions/${sessionId}/recognition/${row.id}/${action}`, {}, { preserveScroll: true });
    }

    return (
        <div
            className={cn(
                'flex items-start gap-3',
                hall && 'items-center gap-4',
                recognized && 'border-l-2 border-[var(--color-live)] pl-3',
            )}
        >
            <UserAvatar
                name={row.display_name}
                src={row.avatar_url}
                className={hall ? 'size-12' : 'size-9'}
            />
            <div className="min-w-0 flex-1">
                <p
                    className={cn(
                        'font-semibold tracking-[-0.015em] text-[var(--color-live-ink)]',
                        hall ? 'text-[clamp(1.125rem,2vw,1.75rem)] leading-tight' : 'text-sm',
                    )}
                >
                    {recognized
                        ? t('sessions.recognition_has_floor', { name })
                        : t('sessions.recognition_seeks_floor', { name })}
                </p>
                {item ? (
                    <p
                        className={cn(
                            'text-[var(--color-live-ink)]/75',
                            hall ? 'mt-1 text-[clamp(0.875rem,1.2vw,1.125rem)]' : 'mt-0.5 text-xs',
                        )}
                    >
                        {item}
                    </p>
                ) : null}
            </div>
            {hall ? null : (
                <div className="flex shrink-0 flex-wrap justify-end gap-1.5">
                    {actions.recognize ? (
                        <Button size="sm" variant="primary" onClick={() => post('recognize')}>
                            {t('sessions.recognition_recognize')}
                        </Button>
                    ) : null}
                    {actions.dismiss ? (
                        <Button size="sm" variant="secondary" onClick={() => post('dismiss')}>
                            {t('sessions.recognition_dismiss')}
                        </Button>
                    ) : null}
                    {actions.cancel ? (
                        <Button size="sm" variant="ghost" onClick={() => post('cancel')}>
                            {t('sessions.recognition_cancel')}
                        </Button>
                    ) : null}
                </div>
            )}
        </div>
    );
}

export function FloorRecognitionDock({
    sessionId,
    recognition,
    hall = false,
    className,
}: {
    sessionId: string;
    recognition: RecognitionState | null | undefined;
    hall?: boolean;
    className?: string;
}) {
    const { t } = useTranslations();
    const pending = recognition?.pending ?? [];
    const recognized = recognition?.recognized ?? null;

    if (pending.length === 0 && recognized === null) {
        return null;
    }

    return (
        <section
            role="alert"
            aria-live="assertive"
            className={cn(
                'border border-[var(--color-live-line)] bg-live-soft text-[var(--color-live-ink)] shadow-[var(--shadow-sm)]',
                hall
                    ? 'rounded-none border-x-0 border-t-0 px-5 py-3 md:px-7'
                    : 'rounded-[var(--radius-lg)] px-4 py-3',
                className,
            )}
        >
            <div className="mb-2.5 flex items-center gap-2">
                <LiveDot className={hall ? 'size-2' : undefined} />
                <p
                    className={cn(
                        'font-semibold tracking-[0.08em] uppercase',
                        hall ? 'text-[clamp(0.75rem,1vw,1rem)]' : 'text-2xs',
                    )}
                >
                    {recognized
                        ? t('sessions.recognition_live_floor')
                        : t('sessions.recognition_live_queue', { count: pending.length })}
                </p>
            </div>
            <div className={cn('space-y-3', hall && 'space-y-4')}>
                {recognized ? (
                    <RecognitionRow sessionId={sessionId} row={recognized} recognized hall={hall} />
                ) : null}
                {pending.map((row) => (
                    <RecognitionRow key={row.id} sessionId={sessionId} row={row} recognized={false} hall={hall} />
                ))}
            </div>
        </section>
    );
}

export function MemberRaiseMotionButton({
    sessionId,
    canSeek,
    pendingRequest,
    className,
}: {
    sessionId: string;
    canSeek: boolean;
    pendingRequest: RecognitionRequest | null;
    className?: string;
}) {
    const { t } = useTranslations();

    if (!canSeek && pendingRequest === null) {
        return null;
    }

    function raise() {
        router.post(`/sessions/${sessionId}/recognition`, {}, { preserveScroll: true });
    }

    function cancel() {
        if (!pendingRequest) {
            return;
        }

        router.post(`/sessions/${sessionId}/recognition/${pendingRequest.id}/cancel`, {}, { preserveScroll: true });
    }

    if (pendingRequest) {
        return (
            <Button type="button" variant="secondary" size="sm" onClick={cancel} className={className}>
                <Gavel aria-hidden className="size-4" strokeWidth={1.75} />
                {t('sessions.recognition_waiting')}
            </Button>
        );
    }

    return (
        <Button type="button" variant="secondary" size="sm" onClick={raise} className={className} disabled={!canSeek}>
            <Gavel aria-hidden className="size-4" strokeWidth={1.75} />
            {t('sessions.action_raise_motion')}
        </Button>
    );
}
