import { SimpleSelect } from '@/components/ui/select';
import { useTranslations } from '@/lib/i18n';

export type ChamberDeviceOption = {
    index: number | null;
    name: string;
    input_count?: number;
};

const SAVED = '__saved';
const NAME_PREFIX = 'name:';

function optionValue(device: ChamberDeviceOption): string {
    return device.index === null || device.index === undefined ? `${NAME_PREFIX}${device.name}` : String(device.index);
}

type Props = {
    index: string;
    name: string;
    devices: ChamberDeviceOption[];
    disabled?: boolean;
    onChange: (index: string, name: string) => void;
};

export function ChamberDeviceSelect({ index, name, devices, disabled, onChange }: Props) {
    const { t } = useTranslations();
    const listed = devices.some(
        (device) => (device.index !== null && String(device.index) === index) || (name !== '' && device.name === name),
    );
    const savedOrphan = name !== '' && !listed;
    const matched = devices.find(
        (device) => (index !== '' && device.index !== null && String(device.index) === index) || (name !== '' && device.name === name),
    );
    const value = listed && matched ? optionValue(matched) : savedOrphan ? SAVED : '';

    const items = [
        ...(savedOrphan ? [{ value: SAVED, label: name }] : []),
        ...devices.map((device) => ({
            value: optionValue(device),
            label:
                device.input_count && device.input_count > 0
                    ? `${device.name} · ${t('chamber.devices.inputs', { count: device.input_count })}`
                    : device.name,
        })),
    ];

    return (
        <SimpleSelect
            value={value}
            onValueChange={(next) => {
                if (next === SAVED) {
                    onChange(index, name);
                    return;
                }

                if (next === '') {
                    onChange('', '');
                    return;
                }

                if (next.startsWith(NAME_PREFIX)) {
                    onChange('', next.slice(NAME_PREFIX.length));
                    return;
                }

                const match = devices.find((device) => optionValue(device) === next);
                onChange(next, match?.name ?? name);
            }}
            noneLabel={t('chamber.device_default')}
            placeholder={t('chamber.device_default')}
            disabled={disabled}
            aria-label={t('chamber.device')}
            items={items}
            className="min-w-52"
        />
    );
}
