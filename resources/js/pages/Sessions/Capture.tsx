import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Notice } from '@/components/ui/notice';
import { Panel, PanelBody, PanelHead, PanelTitle } from '@/components/ui/panel';
import { SimpleSelect } from '@/components/ui/select';
import { LiveDot } from '@/components/ui/status';
import { useCaptureLevel, useChamberCapture, type CaptureState, type CaptureTuning } from '@/hooks/useChamberCapture';
import SessionLayout from '@/layouts/SessionLayout';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { AudioLines, Mic, Square } from 'lucide-react';
import { useEffect, useState } from 'react';

type Props = {
    session: {
        id: string;
        session_number: string;
        title: string;
        status: string;
        status_label: string;
        venue: string | null;
        recording_enabled: boolean;
    };
    capture: CaptureState;
    tuning: CaptureTuning;
    preferred_device: { index: number | null; name: string | null } | null;
};

/**
 * The recording console. This tab *is* the recorder: leave it open on the
 * chamber PC for the whole sitting. Everything the old capture daemon did —
 * pick the device, detect speech, cut chunks, upload them — happens here.
 */
export default function Capture({ session, capture, tuning, preferred_device }: Props) {
    const { t } = useTranslations();

    const { phase, state, error, stats, devices, deviceId, deviceLabel, foreign, start, stop, refreshDevices } =
        useChamberCapture({
            sessionId: session.id,
            sessionLabel: session.title,
            tuning,
            initialState: capture,
            preferredDeviceName: preferred_device?.name ?? '',
        });

    // The engine outlives this page, so the chosen device is its state, not the
    // page's — coming back to the console mid-sitting must show what is running.
    const [choice, setChoice] = useState(deviceId);
    const selected = phase === 'stopped' ? choice : deviceId;

    const listening = phase === 'listening';
    const recording = listening && state?.capture === 'record';
    const level = useCaptureLevel(listening);

    // Device labels stay blank until the browser has granted microphone access
    // at least once, so this only fills in after the operator has hit Start.
    const unnamedDevice = t('chamber.devices.unnamed');

    useEffect(() => {
        void refreshDevices(unnamedDevice);
    }, [phase, refreshDevices, unnamedDevice]);

    const meter = Math.min(1, level / Math.max(tuning.vad_rms, 0.0001) / 3);
    const speaking = level >= tuning.vad_rms;

    const statusKey = !listening
        ? 'capture.status.stopped'
        : state?.capture === 'record'
          ? 'capture.status.recording'
          : state?.capture === 'pause'
            ? 'capture.status.suspended'
            : state?.capture === 'disabled'
              ? 'capture.status.disabled'
              : 'capture.status.waiting';

    return (
        <SessionLayout
            title={t('capture.title')}
            sessionTitle={session.title}
            sessionId={session.id}
            sessionStatus={session.status}
            venue={session.venue}
            headerActions={
                listening ? (
                    <Button type="button" variant="secondary" size="default" onClick={stop}>
                        <Square aria-hidden="true" strokeWidth={1.75} />
                        {t('capture.stop')}
                    </Button>
                ) : null
            }
        >
            <div className="flex flex-col gap-4">
                <Panel as="section">
                    <PanelHead sunk>
                        <div className="flex items-center gap-2">
                            <AudioLines aria-hidden="true" className="size-4 text-accent" strokeWidth={1.75} />
                            <PanelTitle>{t('capture.title')}</PanelTitle>
                        </div>
                        {recording ? (
                            <Badge className="border-[var(--color-live-line)] bg-live-soft text-[var(--color-live-ink)]">
                                <LiveDot className="size-1.5" />
                                {t('capture.badge_recording')}
                            </Badge>
                        ) : null}
                    </PanelHead>

                    <PanelBody className="flex flex-col gap-4">
                        <p className="text-sm text-ink-muted">{t('capture.intro')}</p>

                        {error ? (
                            <Notice tone="caution">
                                {error === 'unsupported' || error === 'worklet'
                                    ? t(`capture.error.${error}`)
                                    : t(`chamber.devices.${error}`)}
                            </Notice>
                        ) : null}

                        {listening && !session.recording_enabled ? (
                            <Notice tone="caution">{t('capture.recording_off')}</Notice>
                        ) : null}

                        {foreign ? <Notice tone="caution">{t('capture.other_sitting')}</Notice> : null}

                        <div className="flex flex-wrap items-end gap-3">
                            <div className="min-w-64 flex-1">
                                <label htmlFor="capture-device" className="mb-1.5 block text-sm font-medium text-ink">
                                    {t('chamber.device')}
                                </label>
                                <SimpleSelect
                                    id="capture-device"
                                    value={selected}
                                    onValueChange={setChoice}
                                    disabled={phase !== 'stopped'}
                                    noneLabel={t('chamber.device_default')}
                                    placeholder={t('chamber.device_default')}
                                    items={devices.map((device) => ({ value: device.id, label: device.name }))}
                                />
                            </div>

                            {listening ? (
                                <Button type="button" variant="secondary" size="lg" onClick={stop}>
                                    <Square aria-hidden="true" strokeWidth={1.75} />
                                    {t('capture.stop')}
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    variant="live"
                                    size="lg"
                                    onClick={() => void start(choice || undefined)}
                                    disabled={phase === 'starting' || foreign}
                                >
                                    <Mic aria-hidden="true" strokeWidth={1.75} />
                                    {phase === 'starting' ? t('capture.starting') : t('capture.start')}
                                </Button>
                            )}
                        </div>

                        <div className="flex items-center gap-3">
                            <span className="block h-2 w-full overflow-hidden rounded-full bg-canvas-sunk" aria-hidden="true">
                                <span
                                    className={cn(
                                        'block h-full rounded-full transition-[width] duration-75',
                                        speaking ? 'bg-live' : 'bg-accent',
                                    )}
                                    style={{ width: `${Math.round(meter * 100)}%` }}
                                />
                            </span>
                            <span className="shrink-0 text-xs text-ink-muted">
                                {listening
                                    ? speaking
                                        ? t('capture.meter.speech')
                                        : t('capture.meter.quiet')
                                    : t('capture.meter.idle')}
                            </span>
                        </div>

                        <p className="text-sm text-ink">{t(statusKey)}</p>
                        {listening && deviceLabel ? (
                            <p className="text-xs text-ink-subtle">{t('chamber.listen.using', { name: deviceLabel })}</p>
                        ) : null}
                    </PanelBody>
                </Panel>

                <Panel as="section">
                    <PanelHead sunk>
                        <PanelTitle>{t('capture.stats.title')}</PanelTitle>
                    </PanelHead>
                    <PanelBody className="flex flex-col gap-4">
                        <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <Stat label={t('capture.stats.uploaded')} value={stats.uploaded} />
                            <Stat label={t('capture.stats.queued')} value={stats.queued} />
                            <Stat label={t('capture.stats.skipped')} value={stats.skipped} />
                            <Stat
                                label={t('capture.stats.failed')}
                                value={stats.failed}
                                tone={stats.failed > 0 ? 'critical' : undefined}
                            />
                        </dl>

                        <p className="text-sm text-ink-muted">{t('capture.stats.hint')}</p>

                        <Link
                            href={`/sessions/${session.id}/transcript`}
                            className="text-sm font-medium text-accent hover:underline"
                        >
                            {t('capture.open_transcript')}
                        </Link>
                    </PanelBody>
                </Panel>

                <Notice tone="info">{t('capture.keep_running')}</Notice>
            </div>
        </SessionLayout>
    );
}

function Stat({ label, value, tone }: { label: string; value: number; tone?: 'critical' }) {
    return (
        <div className="rounded-md border border-line bg-canvas-sunk px-3 py-2.5">
            <dt className="text-xs font-medium tracking-wide text-ink-subtle uppercase">{label}</dt>
            <dd className={cn('mt-1 font-mono text-xl', tone === 'critical' ? 'text-critical' : 'text-ink')}>{value}</dd>
        </div>
    );
}
