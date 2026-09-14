import { Button } from '@/components/ui/button';
import { Notice } from '@/components/ui/notice';
import { Panel, PanelBody, PanelHead, PanelTitle } from '@/components/ui/panel';
import { mediaErrorCode, toLocalDevices, type LocalDevice } from '@/lib/audio';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { AudioLines } from 'lucide-react';
import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { ChamberListenBack } from './ChamberListenBack';

type RemoteDevice = {
    index: number;
    name: string;
    input_count: number;
    is_default: boolean;
};

export type { LocalDevice, RemoteDevice };

type LevelsResponse = {
    online: boolean;
    device: string | null;
    listening_inputs: number | null;
    devices: RemoteDevice[];
};

type LocalError = 'denied' | 'insecure' | 'preview' | 'empty' | 'failed';

type StarterStatus = 'idle' | 'downloading' | 'done' | 'failed';

type Props = {
    canUpdate: boolean;
    captureBaseUrl: string;
    selectedIndex: string;
    selectedName: string;
    onDevicesListed?: (devices: RemoteDevice[]) => void;
    onLocalDevicesListed?: (devices: LocalDevice[]) => void;
    onDeviceChange?: (index: string, name: string) => void;
};

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export function ChamberComputerDevices({
    canUpdate,
    captureBaseUrl,
    selectedIndex,
    selectedName,
    onDevicesListed,
    onLocalDevicesListed,
    onDeviceChange,
}: Props) {
    const { t } = useTranslations();
    const [checking, setChecking] = useState(false);
    const [localError, setLocalError] = useState<LocalError | null>(null);
    const [localDevices, setLocalDevices] = useState<LocalDevice[] | null>(null);
    const [remote, setRemote] = useState<LevelsResponse | null>(null);
    const [starterStatus, setStarterStatus] = useState<StarterStatus>('idle');

    const loadRemote = useCallback(async () => {
        try {
            const response = await fetch('/settings/chamber-channels/levels', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const payload = (await response.json()) as LevelsResponse;
            setRemote(payload);
            onDevicesListed?.(payload.online ? (payload.devices ?? []) : []);
        } catch {
            setRemote(null);
            onDevicesListed?.([]);
        }
    }, [onDevicesListed]);

    useEffect(() => {
        void loadRemote();
        const timer = window.setInterval(() => {
            void loadRemote();
        }, 4000);

        return () => window.clearInterval(timer);
    }, [loadRemote]);

    useEffect(() => {
        onLocalDevicesListed?.(localDevices ?? []);
    }, [localDevices, onLocalDevicesListed]);

    useEffect(() => {
        let cancelled = false;

        async function peekLabeledInputs() {
            if (typeof window !== 'undefined' && window.isSecureContext === false) {
                return;
            }

            if (typeof navigator === 'undefined' || !navigator.mediaDevices?.enumerateDevices) {
                return;
            }

            try {
                const listed = await navigator.mediaDevices.enumerateDevices();
                const inputs = toLocalDevices(listed, '').filter((device) => device.name !== '');

                if (!cancelled && inputs.length > 0) {
                    setLocalDevices(inputs);
                }
            } catch {
                // Check this computer remains the explicit path when labels are hidden.
            }
        }

        void peekLabeledInputs();

        return () => {
            cancelled = true;
        };
    }, []);

    async function checkThisComputer() {
        setChecking(true);
        setLocalError(null);

        if (typeof window !== 'undefined' && window.isSecureContext === false) {
            setLocalDevices(null);
            setLocalError('insecure');
            setChecking(false);
            return;
        }

        if (typeof navigator === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
            setLocalDevices(null);
            setLocalError('preview');
            setChecking(false);
            return;
        }

        let stream: MediaStream | null = null;

        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const listed = await navigator.mediaDevices.enumerateDevices();
            const inputs = toLocalDevices(listed, t('chamber.devices.unnamed'));

            setLocalDevices(inputs);
            setLocalError(inputs.length === 0 ? 'empty' : null);
        } catch (error) {
            setLocalDevices(null);
            setLocalError(mediaErrorCode(error));
        } finally {
            stream?.getTracks().forEach((track) => track.stop());
            setChecking(false);
        }
    }

    async function downloadStarter() {
        if (!window.confirm(t('chamber.starter.confirm'))) {
            return;
        }

        setStarterStatus('downloading');

        try {
            const response = await fetch('/settings/chamber-channels/starter', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/zip',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                setStarterStatus('failed');
                return;
            }

            const blob = await response.blob();
            const href = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = href;
            link.download = 'sentria-chamber-recording.zip';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(href);
            setStarterStatus('done');
        } catch {
            setStarterStatus('failed');
        }
    }

    const remoteDevices = remote?.online ? (remote.devices ?? []) : [];
    const remoteInputs = remote?.listening_inputs ?? null;

    return (
        <Panel as="section">
            <PanelHead sunk>
                <div className="flex items-center gap-2">
                    <AudioLines aria-hidden="true" className="size-4 text-accent" strokeWidth={1.75} />
                    <PanelTitle>{t('chamber.devices.title')}</PanelTitle>
                </div>
                <div className="flex items-center gap-2">
                    <ChamberListenBack devices={localDevices ?? []} selectedName={selectedName} />
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        onClick={() => void checkThisComputer()}
                        disabled={checking}
                    >
                        {checking ? t('chamber.devices.checking') : t('chamber.devices.check')}
                    </Button>
                </div>
            </PanelHead>
            <PanelBody className="flex flex-col gap-4">
                <p className="text-sm text-ink-muted">{t('chamber.devices.intro')}</p>
                {localError ? (
                    <Notice tone={localError === 'empty' ? 'info' : 'caution'}>{t(`chamber.devices.${localError}`)}</Notice>
                ) : null}

                <div className="grid gap-4 md:grid-cols-2">
                    <DeviceGroup title={t('chamber.devices.this_pc')}>
                        {localDevices && localDevices.length > 0 ? (
                            <DeviceList
                                selectedIndex={selectedIndex}
                                selectedName={selectedName}
                                items={localDevices.map((device) => ({
                                    key: device.id,
                                    name: device.name,
                                    detail: null,
                                    recommended: /scarlett|focusrite|behringer|dante|umc|interface|mixer/i.test(device.name),
                                }))}
                                recommendedLabel={t('chamber.devices.recommended')}
                                chosenLabel={t('chamber.device_chosen')}
                                onSelect={canUpdate && onDeviceChange ? (item) => onDeviceChange('', item.name) : undefined}
                            />
                        ) : null}
                        {localDevices === null && localError === null ? (
                            <p className="text-sm text-ink-muted">{t('chamber.devices.this_pc_hint')}</p>
                        ) : null}
                    </DeviceGroup>

                    <DeviceGroup title={t('chamber.devices.recording')}>
                        {remote?.online ? (
                            <>
                                <p className="text-sm text-ink">
                                    {remote.device ?? t('chamber.devices.recording_box')}
                                    {remoteInputs ? ` · ${t('chamber.devices.inputs', { count: remoteInputs })}` : ''}
                                </p>
                                {remoteDevices.length > 0 ? (
                                    <DeviceList
                                        selectedIndex={selectedIndex}
                                        selectedName={selectedName}
                                        items={remoteDevices.map((device) => ({
                                            key: String(device.index),
                                            name: device.name,
                                            detail: t('chamber.devices.inputs', { count: device.input_count }),
                                            recommended: device.input_count > 2,
                                        }))}
                                        recommendedLabel={t('chamber.devices.recommended')}
                                        chosenLabel={t('chamber.device_chosen')}
                                        onSelect={
                                            canUpdate && onDeviceChange
                                                ? (item) => onDeviceChange(item.key, item.name)
                                                : undefined
                                        }
                                    />
                                ) : null}
                            </>
                        ) : (
                            <p className="text-sm text-ink-muted">{t('chamber.devices.recording_offline')}</p>
                        )}
                        {canUpdate ? (
                            <div className="mt-1 flex flex-col gap-2 border-t border-line pt-3">
                                <p className="text-sm text-ink-muted">{t('chamber.starter.hint', { url: captureBaseUrl })}</p>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    className="self-start"
                                    onClick={() => void downloadStarter()}
                                    disabled={starterStatus === 'downloading'}
                                >
                                    {starterStatus === 'downloading'
                                        ? t('chamber.starter.downloading')
                                        : t('chamber.starter.download')}
                                </Button>
                                {starterStatus === 'done' ? (
                                    <p className="text-sm text-ink">{t('chamber.starter.done')}</p>
                                ) : null}
                                {starterStatus === 'failed' ? (
                                    <p className="text-sm text-critical">{t('chamber.starter.failed')}</p>
                                ) : null}
                            </div>
                        ) : null}
                    </DeviceGroup>
                </div>

                <p className="text-xs text-ink-subtle">{t('chamber.devices.hint')}</p>
            </PanelBody>
        </Panel>
    );
}

function DeviceGroup({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="rounded-md border border-line bg-canvas-sunk px-4 py-3">
            <p className="text-xs font-medium tracking-wide text-ink-subtle uppercase">{title}</p>
            <div className="mt-2 flex flex-col gap-2">{children}</div>
        </div>
    );
}

type ListedDevice = {
    key: string;
    name: string;
    detail: string | null;
    recommended: boolean;
};

function DeviceList({
    items,
    recommendedLabel,
    chosenLabel,
    selectedIndex,
    selectedName,
    onSelect,
}: {
    items: ListedDevice[];
    recommendedLabel: string;
    chosenLabel?: string;
    selectedIndex?: string;
    selectedName?: string;
    onSelect?: (item: ListedDevice) => void;
}) {
    return (
        <ul className="flex flex-col gap-1.5">
            {items.map((item) => {
                const selected = item.key === selectedIndex || (selectedName !== '' && item.name === selectedName);
                const label = (
                    <>
                        <span className={cn((item.recommended || selected) && 'font-medium')}>{item.name}</span>
                        {item.detail ? <span className="ml-1.5 font-mono text-xs text-ink-muted">{item.detail}</span> : null}
                        {selected && chosenLabel ? <span className="ml-1.5 text-xs text-accent">{chosenLabel}</span> : null}
                        {!selected && item.recommended ? (
                            <span className="ml-1.5 text-xs text-accent">{recommendedLabel}</span>
                        ) : null}
                    </>
                );

                return (
                    <li key={item.key} className="text-sm text-ink">
                        {onSelect ? (
                            <button
                                type="button"
                                className={cn('w-full rounded-sm px-1 py-0.5 text-left hover:bg-canvas', selected && 'bg-canvas')}
                                onClick={() => onSelect(item)}
                            >
                                {label}
                            </button>
                        ) : (
                            label
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
