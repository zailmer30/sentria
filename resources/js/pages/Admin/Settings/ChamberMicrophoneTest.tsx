import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { useTranslations } from '@/lib/i18n';
import { useEffect, useState } from 'react';

type LevelsResponse = {
    online: boolean;
    channel_rms: Record<string, number>;
    speech_floor: number;
};

type Status = 'idle' | 'listening' | 'offline' | 'missing' | 'quiet' | 'heard';

export function ChamberMicrophoneTest({ channelIndex }: { channelIndex: string }) {
    const { t } = useTranslations();
    const [listening, setListening] = useState(false);
    const [status, setStatus] = useState<Status>('idle');
    const [level, setLevel] = useState(0);

    useEffect(() => {
        if (!listening) {
            return;
        }

        let cancelled = false;
        let peak = 0;
        const index = String(Number(channelIndex) || 0);

        async function tick() {
            try {
                const response = await fetch('/settings/chamber-channels/levels', {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok || cancelled) {
                    return;
                }

                const payload = (await response.json()) as LevelsResponse;

                if (cancelled) {
                    return;
                }

                if (!payload.online) {
                    setStatus('offline');
                    setLevel(0);
                    return;
                }

                if (index === '0' || !(index in (payload.channel_rms ?? {}))) {
                    setStatus('missing');
                    setLevel(0);
                    return;
                }

                const rms = Number(payload.channel_rms[index] ?? 0);
                peak = Math.max(peak, rms);
                const floor = payload.speech_floor > 0 ? payload.speech_floor : 0.012;
                setLevel(Math.min(1, peak / 0.08));
                setStatus(peak >= floor ? 'heard' : 'quiet');
            } catch {
                if (!cancelled) {
                    setStatus('offline');
                    setLevel(0);
                }
            }
        }

        setStatus('listening');
        setLevel(0);
        void tick();
        const poll = window.setInterval(() => {
            void tick();
        }, 750);
        const timeout = window.setTimeout(() => {
            setListening(false);
        }, 20_000);

        return () => {
            cancelled = true;
            window.clearInterval(poll);
            window.clearTimeout(timeout);
        };
    }, [channelIndex, listening]);

    const indexOk = Number(channelIndex) >= 1;

    return (
        <div className="flex min-w-36 flex-col items-start gap-1">
            <Button
                type="button"
                variant="ghost"
                size="sm"
                disabled={!indexOk}
                aria-pressed={listening}
                onClick={() => {
                    setListening((current) => !current);
                    if (listening) {
                        setStatus('idle');
                        setLevel(0);
                    }
                }}
            >
                {listening ? t('chamber.test.stop') : t('chamber.test')}
            </Button>
            {listening ? (
                <>
                    <span
                        className="block h-1.5 w-24 overflow-hidden rounded-full bg-canvas-sunk"
                        aria-hidden="true"
                    >
                        <span
                            className={cn(
                                'block h-full rounded-full transition-[width] duration-200',
                                status === 'heard' ? 'bg-live' : 'bg-accent',
                            )}
                            style={{ width: `${Math.round(level * 100)}%` }}
                        />
                    </span>
                    <p
                        className={cn(
                            'max-w-48 text-2xs leading-snug',
                            status === 'heard' ? 'text-live' : 'text-ink-muted',
                        )}
                    >
                        {t(`chamber.test.${status}`)}
                    </p>
                </>
            ) : null}
        </div>
    );
}
