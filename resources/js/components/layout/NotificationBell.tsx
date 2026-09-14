import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { getEcho } from '@/lib/echo';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { AppNotification, PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function notificationCopy(
    t: (key: string, replacements?: Record<string, string | number>) => string,
    item: AppNotification,
): { title: string; body: string } {
    const titleParams = Object.fromEntries(
        Object.entries(item.title_params ?? {}).map(([k, v]) => [k, v ?? '']),
    ) as Record<string, string | number>;
    const bodyParams = Object.fromEntries(
        Object.entries(item.body_params ?? {}).map(([k, v]) => [k, v ?? '']),
    ) as Record<string, string | number>;

    return {
        title: item.title_key ? t(item.title_key, titleParams) : t('notifications.untitled'),
        body: item.body_key ? t(item.body_key, bodyParams) : '',
    };
}

/**
 * Staff in-app notification bell. Used in AppLayout and the session floor
 * chrome — not the public portal. Unread count is shared over Inertia; opening
 * the popover loads the latest rows, and Echo bumps the badge live.
 */
export function NotificationBell() {
    const { auth, notifications: shared } = usePage<PageProps>().props;
    const { t } = useTranslations();
    const { formatDateTime } = useFormatters();
    const user = auth.user;

    const [open, setOpen] = useState(false);
    const [unreadCount, setUnreadCount] = useState(shared?.unread_count ?? 0);
    const [items, setItems] = useState<AppNotification[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        setUnreadCount(shared?.unread_count ?? 0);
    }, [shared?.unread_count]);

    const canView = Boolean(user?.permissions.includes('notifications.viewAny'));

    const loadRecent = useCallback(async () => {
        setLoading(true);

        try {
            const response = await fetch('/notifications/recent', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const payload = (await response.json()) as {
                notifications: AppNotification[];
                unread_count: number;
            };

            setItems(payload.notifications);
            setUnreadCount(payload.unread_count);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (open) {
            void loadRecent();
        }
    }, [open, loadRecent]);

    useEffect(() => {
        if (!user || !canView) {
            return;
        }

        const echo = getEcho();

        if (!echo) {
            return;
        }

        const channel = echo.private(`App.Models.User.${user.id}`);

        channel.notification((payload: Record<string, unknown>) => {
            const incoming: AppNotification = {
                id: String(payload.id ?? ''),
                type: String(payload.type ?? ''),
                category: (payload.category as string | null) ?? null,
                priority: String(payload.priority ?? 'normal'),
                action_url: (payload.action_url as string | null) ?? null,
                title_key: (payload.title_key as string | null) ?? null,
                title_params: (payload.title_params as Record<string, string | number | null>) ?? {},
                body_key: (payload.body_key as string | null) ?? null,
                body_params: (payload.body_params as Record<string, string | number | null>) ?? {},
                read_at: null,
                created_at: (payload.created_at as string | null) ?? new Date().toISOString(),
            };

            setUnreadCount((count) => count + 1);
            setItems((current) => [incoming, ...current].slice(0, 10));

            if (!open && incoming.priority === 'high') {
                const copy = notificationCopy(t, incoming);
                toast.message(copy.title, { description: copy.body || undefined });
            }
        });

        return () => {
            echo.leave(`App.Models.User.${user.id}`);
        };
    }, [user, canView, open, t]);

    if (!user || !canView) {
        return null;
    }

    async function markRead(item: AppNotification): Promise<void> {
        if (item.read_at) {
            if (item.action_url) {
                router.visit(item.action_url);
            }

            return;
        }

        await fetch(`/notifications/${item.id}`, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });

        setItems((current) =>
            current.map((row) =>
                row.id === item.id ? { ...row, read_at: new Date().toISOString() } : row,
            ),
        );
        setUnreadCount((count) => Math.max(0, count - 1));

        if (item.action_url) {
            setOpen(false);
            router.visit(item.action_url);
        }
    }

    async function markAllRead(): Promise<void> {
        await fetch('/notifications/read-all', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });

        setItems((current) =>
            current.map((row) => ({ ...row, read_at: row.read_at ?? new Date().toISOString() })),
        );
        setUnreadCount(0);
    }

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    aria-label={t('a11y.notifications')}
                    title={t('a11y.notifications')}
                    className="relative"
                >
                    <Bell aria-hidden="true" strokeWidth={1.75} />
                    {unreadCount > 0 ? (
                        <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-live px-1 text-[0.625rem] font-semibold text-[var(--color-live-on)]">
                            {unreadCount > 99 ? '99+' : unreadCount}
                        </span>
                    ) : null}
                </Button>
            </PopoverTrigger>

            <PopoverContent align="end" className="w-96 p-0">
                <div className="flex items-center justify-between gap-2 border-b border-line px-3 py-2.5">
                    <p className="text-sm font-semibold text-ink">{t('notifications.title')}</p>
                    {unreadCount > 0 ? (
                        <Button variant="link" size="sm" className="h-auto px-0 text-xs" onClick={() => void markAllRead()}>
                            {t('notifications.mark_all_read')}
                        </Button>
                    ) : null}
                </div>

                <div className="max-h-80 overflow-y-auto">
                    {loading && items.length === 0 ? (
                        <p className="px-3 py-6 text-center text-sm text-ink-muted">{t('notifications.loading')}</p>
                    ) : items.length === 0 ? (
                        <p className="px-3 py-6 text-center text-sm text-ink-muted">{t('notifications.empty')}</p>
                    ) : (
                        <ul className="divide-y divide-line">
                            {items.map((item) => {
                                const copy = notificationCopy(t, item);
                                const unread = item.read_at === null;

                                return (
                                    <li key={item.id}>
                                        <button
                                            type="button"
                                            onClick={() => void markRead(item)}
                                            className={cn(
                                                'flex w-full flex-col gap-1 px-3 py-2.5 text-left transition-colors hover:bg-canvas-sunk',
                                                unread && 'bg-accent/5',
                                            )}
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <p className={cn('text-sm text-ink', unread ? 'font-semibold' : 'font-medium')}>
                                                    {copy.title}
                                                </p>
                                                {item.category ? (
                                                    <Badge variant="outline">{t(`notifications.category.${item.category}`)}</Badge>
                                                ) : null}
                                            </div>
                                            {copy.body ? <p className="text-xs text-ink-muted">{copy.body}</p> : null}
                                            <p className="text-2xs text-ink-faint">{formatDateTime(item.created_at)}</p>
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>

                <div className="border-t border-line px-3 py-2">
                    <Link
                        href="/notifications"
                        className="text-xs font-medium text-accent hover:underline"
                        onClick={() => setOpen(false)}
                    >
                        {t('notifications.see_all')}
                    </Link>
                </div>
            </PopoverContent>
        </Popover>
    );
}
