import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { IndexHeader } from '@/components/ui/index-header';
import { Checkbox, Input } from '@/components/ui/input';
import { Notice } from '@/components/ui/notice';
import {
    Register,
    RegisterBody,
    RegisterCell,
    RegisterCellActions,
    RegisterEmpty,
    RegisterFrame,
    RegisterHead,
    RegisterHeadCell,
    RegisterRow,
} from '@/components/ui/register';
import { SimpleSelect } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { ChamberComputerDevices, type LocalDevice, type RemoteDevice } from './ChamberComputerDevices';
import { ChamberDeviceSelect, type ChamberDeviceOption } from './ChamberDeviceSelect';
import { ChamberMicrophoneManual } from './ChamberMicrophoneManual';
import { ChamberMicrophoneTest } from './ChamberMicrophoneTest';
import { Mic, Plus } from 'lucide-react';
import { FormEvent, useState } from 'react';

type ChannelRow = {
    id: string;
    channel_index: number;
    user_id: string | null;
    label: string | null;
    is_active: boolean;
};

type MemberOption = {
    id: string;
    display_name: string;
};

type FormChannel = {
    id: string;
    channel_index: string;
    user_id: string;
    label: string;
    is_active: boolean;
};

type RecordingDevice = {
    index: number | null;
    name: string;
};

type Props = {
    channels: ChannelRow[];
    members: MemberOption[];
    capture_base_url: string;
    default_capture_mode: 'mixer_mix' | 'per_seat';
    recording_device: RecordingDevice | null;
    can: { update: boolean };
};

function toFormRow(channel: ChannelRow): FormChannel {
    return {
        id: channel.id,
        channel_index: String(channel.channel_index),
        user_id: channel.user_id ?? '',
        label: channel.label ?? '',
        is_active: channel.is_active,
    };
}

function roughDeviceName(name: string): string {
    return name
        .toLowerCase()
        .replace(/^default - /, '')
        .replace(/^communications - /, '')
        .replace(/ \((bluetooth|usb|analog)\)$/, '')
        .trim();
}

function devicesForSelect(remote: RemoteDevice[], local: LocalDevice[]): ChamberDeviceOption[] {
    const options: ChamberDeviceOption[] = remote.map((device) => ({
        index: device.index,
        name: device.name,
        input_count: device.input_count,
    }));
    const remoteKeys = new Set(options.map((device) => roughDeviceName(device.name)).filter(Boolean));
    const seenNames = new Set(options.map((device) => device.name.toLowerCase()));

    for (const device of local) {
        const name = device.name.trim();
        const key = roughDeviceName(name);

        if (name === '' || seenNames.has(name.toLowerCase())) {
            continue;
        }

        if (key !== '' && remoteKeys.has(key)) {
            continue;
        }

        seenNames.add(name.toLowerCase());
        options.push({ index: null, name });
    }

    return options;
}

export default function ChamberChannelsSettings({
    channels,
    members,
    capture_base_url,
    default_capture_mode,
    recording_device,
    can,
}: Props) {
    const { t } = useTranslations();
    const [remoteDevices, setRemoteDevices] = useState<RemoteDevice[]>([]);
    const [localDevices, setLocalDevices] = useState<LocalDevice[]>([]);
    const selectableDevices = devicesForSelect(remoteDevices, localDevices);
    const form = useForm<{ channels: FormChannel[] }>({
        channels: channels.map(toFormRow),
    });
    const feedForm = useForm<{ default_capture_mode: 'mixer_mix' | 'per_seat' }>({
        default_capture_mode,
    });
    const deviceForm = useForm<{ device_index: string; device_name: string }>({
        device_index:
            recording_device?.index !== null && recording_device?.index !== undefined
                ? String(recording_device.index)
                : '',
        device_name: recording_device?.name ?? '',
    });

    function saveDevice(index: string, name: string) {
        deviceForm.setData({ device_index: index, device_name: name });
        deviceForm.transform(() => ({
            device_index: index === '' ? null : Number(index),
            device_name: name || null,
        }));
        deviceForm.put('/settings/chamber-channels/device', { preserveScroll: true });
    }

    function addRow() {
        const nextIndex = form.data.channels.reduce((max, row) => Math.max(max, Number(row.channel_index) || 0), 0) + 1;

        form.setData('channels', [
            ...form.data.channels,
            {
                id: '',
                channel_index: String(nextIndex),
                user_id: '',
                label: '',
                is_active: true,
            },
        ]);
    }

    function updateRow(index: number, patch: Partial<FormChannel>) {
        form.setData(
            'channels',
            form.data.channels.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)),
        );
    }

    function removeRow(index: number) {
        form.setData(
            'channels',
            form.data.channels.filter((_, rowIndex) => rowIndex !== index),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            channels: data.channels.map((row) => ({
                id: row.id || null,
                channel_index: Number(row.channel_index),
                user_id: row.user_id || null,
                label: row.label || null,
                is_active: row.is_active,
            })),
        }));

        form.put('/settings/chamber-channels');
    }

    return (
        <AppLayout title={t('chamber.title')}>
            <div className="flex flex-col gap-5">
                <IndexHeader
                    eyebrow={t('index.eyebrow.administration')}
                    title={t('chamber.title')}
                    description={t('chamber.intro')}
                />

                <Notice tone="info">{t('chamber.hardware_notice')}</Notice>

                <form
                    className="flex flex-col gap-3 rounded-[var(--radius-md)] border border-line bg-surface p-4 sm:flex-row sm:items-end"
                    onSubmit={(event) => {
                        event.preventDefault();
                        feedForm.put('/settings/chamber-channels/feed');
                    }}
                >
                    <label className="min-w-0 flex-1 text-sm">
                        <span className="mb-1.5 block text-xs text-ink-subtle">{t('chamber.feed.label')}</span>
                        <SimpleSelect
                            value={feedForm.data.default_capture_mode}
                            onValueChange={(value) => {
                                if (value === 'mixer_mix' || value === 'per_seat') {
                                    feedForm.setData('default_capture_mode', value);
                                }
                            }}
                            items={[
                                { value: 'mixer_mix', label: t('chamber.feed.mixer_mix') },
                                { value: 'per_seat', label: t('chamber.feed.per_seat') },
                            ]}
                            disabled={!can.update || feedForm.processing}
                        />
                        <span className="mt-1.5 block text-xs text-ink-faint">{t('chamber.feed.help')}</span>
                    </label>
                    <label className="min-w-0 flex-1 text-sm">
                        <span className="mb-1.5 block text-xs text-ink-subtle">{t('chamber.device')}</span>
                        <ChamberDeviceSelect
                            index={deviceForm.data.device_index}
                            name={deviceForm.data.device_name}
                            devices={selectableDevices}
                            disabled={!can.update || deviceForm.processing}
                            onChange={saveDevice}
                        />
                        <span className="mt-1.5 block text-xs text-ink-faint">{t('chamber.device_help')}</span>
                    </label>
                    {can.update ? (
                        <Button type="submit" size="sm" disabled={feedForm.processing}>
                            {t('chamber.feed.save')}
                        </Button>
                    ) : null}
                </form>

                <ChamberComputerDevices
                    canUpdate={can.update}
                    captureBaseUrl={capture_base_url}
                    selectedIndex={deviceForm.data.device_index}
                    selectedName={deviceForm.data.device_name}
                    onDevicesListed={setRemoteDevices}
                    onLocalDevicesListed={setLocalDevices}
                    onDeviceChange={can.update ? saveDevice : undefined}
                />

                <ChamberMicrophoneManual />

                <form onSubmit={submit}>
                    <RegisterFrame
                        footer={
                            <div className="flex flex-wrap items-center gap-2 border-t border-line px-5 py-3">
                                {can.update ? (
                                    <>
                                        <Button type="button" variant="secondary" onClick={addRow}>
                                            <Plus aria-hidden="true" strokeWidth={1.75} />
                                            {t('chamber.add')}
                                        </Button>
                                        <Button type="submit" variant="plate" disabled={form.processing}>
                                            {t('chamber.save')}
                                        </Button>
                                    </>
                                ) : null}
                                <Button type="button" variant="ghost" asChild>
                                    <Link href="/settings">{t('chamber.back')}</Link>
                                </Button>
                            </div>
                        }
                    >
                        <Register flush caption={t('chamber.title')} minWidth="72rem">
                            <RegisterHead>
                                <RegisterHeadCell>{t('chamber.channel_index')}</RegisterHeadCell>
                                <RegisterHeadCell>{t('chamber.device')}</RegisterHeadCell>
                                <RegisterHeadCell>{t('chamber.member')}</RegisterHeadCell>
                                <RegisterHeadCell>{t('chamber.label')}</RegisterHeadCell>
                                <RegisterHeadCell>{t('chamber.active')}</RegisterHeadCell>
                                <RegisterHeadCell>{t('chamber.test')}</RegisterHeadCell>
                                <RegisterHeadCell align="right">
                                    <span className="sr-only">{t('chamber.remove')}</span>
                                </RegisterHeadCell>
                            </RegisterHead>
                            <RegisterBody>
                                {form.data.channels.length === 0 ? (
                                    <RegisterEmpty colSpan={7}>
                                        <EmptyState bare icon={Mic} title={t('chamber.empty')} />
                                    </RegisterEmpty>
                                ) : (
                                    form.data.channels.map((row, index) => (
                                        <RegisterRow key={row.id || `new-${index}`}>
                                            <RegisterCell>
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    value={row.channel_index}
                                                    onChange={(event) =>
                                                        updateRow(index, { channel_index: event.target.value })
                                                    }
                                                    disabled={!can.update}
                                                    aria-label={t('chamber.channel_index')}
                                                />
                                            </RegisterCell>
                                            <RegisterCell>
                                                <ChamberDeviceSelect
                                                    index={deviceForm.data.device_index}
                                                    name={deviceForm.data.device_name}
                                                    devices={selectableDevices}
                                                    disabled={!can.update || deviceForm.processing}
                                                    onChange={saveDevice}
                                                />
                                            </RegisterCell>
                                            <RegisterCell>
                                                <SimpleSelect
                                                    value={row.user_id}
                                                    onValueChange={(value) => updateRow(index, { user_id: value })}
                                                    noneLabel={t('chamber.gallery')}
                                                    disabled={!can.update}
                                                    placeholder={t('chamber.gallery')}
                                                    items={members.map((member) => ({
                                                        value: member.id,
                                                        label: member.display_name,
                                                    }))}
                                                />
                                            </RegisterCell>
                                            <RegisterCell>
                                                <Input
                                                    value={row.label}
                                                    onChange={(event) => updateRow(index, { label: event.target.value })}
                                                    disabled={!can.update}
                                                    placeholder={t('chamber.label_placeholder')}
                                                    aria-label={t('chamber.label')}
                                                />
                                            </RegisterCell>
                                            <RegisterCell>
                                                <Checkbox
                                                    checked={row.is_active}
                                                    onChange={(event) =>
                                                        updateRow(index, { is_active: event.target.checked })
                                                    }
                                                    disabled={!can.update}
                                                    aria-label={t('chamber.active')}
                                                />
                                            </RegisterCell>
                                            <RegisterCell>
                                                <ChamberMicrophoneTest channelIndex={row.channel_index} />
                                            </RegisterCell>
                                            <RegisterCellActions>
                                                {can.update ? (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => removeRow(index)}
                                                    >
                                                        {t('chamber.remove')}
                                                    </Button>
                                                ) : null}
                                            </RegisterCellActions>
                                        </RegisterRow>
                                    ))
                                )}
                            </RegisterBody>
                        </Register>
                    </RegisterFrame>

                    {form.errors.channels ? (
                        <p className="mt-3 text-sm text-critical">{form.errors.channels}</p>
                    ) : null}
                </form>
            </div>
        </AppLayout>
    );
}
