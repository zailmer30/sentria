import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { IndexHeader } from '@/components/ui/index-header';
import {
    Register,
    RegisterBody,
    RegisterCell,
    RegisterCellDate,
    RegisterCellPrimary,
    RegisterEmpty,
    RegisterFooter,
    RegisterFrame,
    RegisterHead,
    RegisterHeadCell,
    RegisterRow,
    type Paginated,
} from '@/components/ui/register';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { AppNotification } from '@/types';
import { router } from '@inertiajs/react';
import { Bell } from 'lucide-react';

type Props = {
    notifications: Paginated<AppNotification>;
    filters: {
        unread: boolean;
    };
};

export default function NotificationsIndex({ notifications, filters }: Props) {
    const { t } = useTranslations();

    function setUnreadFilter(unread: boolean): void {
        router.get('/notifications', unread ? { unread: 1 } : {}, { preserveState: true, replace: true });
    }

    function markAllRead(): void {
        router.post('/notifications/read-all', {}, { preserveScroll: true });
    }

    function openNotification(item: AppNotification): void {
        if (!item.read_at) {
            router.patch(
                `/notifications/${item.id}`,
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        if (item.action_url) {
                            router.visit(item.action_url);
                        }
                    },
                },
            );

            return;
        }

        if (item.action_url) {
            router.visit(item.action_url);
        }
    }

    return (
        <AppLayout title={t('notifications.title')}>
            <div className="flex flex-col gap-5">
                <IndexHeader
                    eyebrow={t('index.eyebrow.administration')}
                    title={t('notifications.title')}
                    description={t('notifications.intro')}
                    actions={
                        <Button variant="secondary" onClick={markAllRead}>
                            {t('notifications.mark_all_read')}
                        </Button>
                    }
                />

                <RegisterFrame
                    toolbar={
                        <ToggleGroup
                            type="single"
                            size="default"
                            value={filters.unread ? 'unread' : 'all'}
                            aria-label={t('notifications.title')}
                            onValueChange={(next) => {
                                if (next === 'unread') {
                                    setUnreadFilter(true);
                                } else if (next === 'all') {
                                    setUnreadFilter(false);
                                }
                            }}
                        >
                            <ToggleGroupItem value="all">{t('notifications.filter_all')}</ToggleGroupItem>
                            <ToggleGroupItem value="unread">{t('notifications.filter_unread')}</ToggleGroupItem>
                        </ToggleGroup>
                    }
                    footer={
                        <RegisterFooter
                            inset
                            from={notifications.from}
                            to={notifications.to}
                            total={notifications.total}
                            links={notifications.links}
                            label={t('notifications.title')}
                        />
                    }
                >
                    <Register flush caption={t('notifications.title')}>
                        <RegisterHead>
                            <RegisterHeadCell>{t('notifications.column_message')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('notifications.column_category')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('notifications.column_when')}</RegisterHeadCell>
                        </RegisterHead>
                        <RegisterBody>
                            {notifications.data.length === 0 ? (
                                <RegisterEmpty colSpan={3}>
                                    <EmptyState bare icon={Bell} title={t('notifications.empty')} />
                                </RegisterEmpty>
                            ) : (
                                notifications.data.map((item) => {
                                    const titleParams = Object.fromEntries(
                                        Object.entries(item.title_params ?? {}).map(([k, v]) => [k, v ?? '']),
                                    ) as Record<string, string | number>;
                                    const bodyParams = Object.fromEntries(
                                        Object.entries(item.body_params ?? {}).map(([k, v]) => [k, v ?? '']),
                                    ) as Record<string, string | number>;
                                    const title = item.title_key
                                        ? t(item.title_key, titleParams)
                                        : t('notifications.untitled');
                                    const body = item.body_key ? t(item.body_key, bodyParams) : '';
                                    const unread = item.read_at === null;

                                    return (
                                        <RegisterRow
                                            key={item.id}
                                            className={cn(unread && 'bg-accent/5')}
                                            onClick={() => openNotification(item)}
                                        >
                                            <RegisterCellPrimary secondary={body || undefined}>
                                                <span className={unread ? 'font-semibold' : undefined}>{title}</span>
                                            </RegisterCellPrimary>
                                            <RegisterCell>
                                                {item.category ? (
                                                    <Badge variant="outline">
                                                        {t(`notifications.category.${item.category}`)}
                                                    </Badge>
                                                ) : (
                                                    '—'
                                                )}
                                            </RegisterCell>
                                            <RegisterCellDate value={item.created_at} withTime />
                                        </RegisterRow>
                                    );
                                })
                            )}
                        </RegisterBody>
                    </Register>
                </RegisterFrame>
            </div>
        </AppLayout>
    );
}
