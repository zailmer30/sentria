import { EmptyState } from '@/components/ui/empty-state';
import { IndexHeader } from '@/components/ui/index-header';
import {
    Register,
    RegisterBody,
    RegisterCell,
    RegisterCellActions,
    RegisterCellPrimary,
    RegisterEmpty,
    RegisterFrame,
    RegisterHead,
    RegisterHeadCell,
    RegisterOpenLink,
    RegisterRow,
} from '@/components/ui/register';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Settings } from 'lucide-react';

type SettingArea = {
    key: string;
    label: string;
    description: string;
    href: string;
    available: boolean;
};

type Props = {
    areas: SettingArea[];
};

export default function SettingsIndex({ areas }: Props) {
    const { t } = useTranslations();

    return (
        <AppLayout title={t('settings.title')}>
            <div className="flex flex-col gap-5">
                <IndexHeader
                    eyebrow={t('index.eyebrow.administration')}
                    title={t('settings.title')}
                    description={t('settings.intro')}
                />

                <RegisterFrame>
                    <Register flush caption={t('settings.title')} minWidth="36rem">
                        <RegisterHead>
                            <RegisterHeadCell>{t('settings.area')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('settings.area_description')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">
                                <span className="sr-only">{t('settings.open_area')}</span>
                            </RegisterHeadCell>
                        </RegisterHead>
                        <RegisterBody>
                            {areas.length === 0 ? (
                                <RegisterEmpty colSpan={3}>
                                    <EmptyState bare icon={Settings} title={t('settings.empty')} />
                                </RegisterEmpty>
                            ) : (
                                areas.map((area) => (
                                    <RegisterRow key={area.key}>
                                        <RegisterCellPrimary href={area.available ? area.href : undefined}>
                                            {t(area.label)}
                                        </RegisterCellPrimary>
                                        <RegisterCell className="text-ink-muted">{t(area.description)}</RegisterCell>
                                        <RegisterCellActions>
                                            {area.available ? (
                                                <RegisterOpenLink href={area.href}>{t('settings.open_area')}</RegisterOpenLink>
                                            ) : (
                                                <span className="text-xs text-ink-faint">{t('settings.unavailable')}</span>
                                            )}
                                        </RegisterCellActions>
                                    </RegisterRow>
                                ))
                            )}
                        </RegisterBody>
                    </Register>
                </RegisterFrame>
            </div>
        </AppLayout>
    );
}
