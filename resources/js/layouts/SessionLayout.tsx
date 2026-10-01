import { NotificationBell } from '@/components/layout/NotificationBell';
import { UserMenu } from '@/components/layout/UserMenu';
import { CaptureIndicator } from '@/components/session/CaptureIndicator';
import { OfflineIndicator } from '@/components/session/OfflineIndicator';
import { SessionChatDock } from '@/components/session/SessionChatDock';
import { SessionFloorToolsProvider } from '@/components/session/SessionFloorTools';
import { Button } from '@/components/ui/button';
import { FlashRegion } from '@/components/ui/flash';
import { Input } from '@/components/ui/input';
import { Toaster } from '@/components/ui/sonner';
import { LiveDot } from '@/components/ui/status';
import { ThemeToggle } from '@/components/ui/theme-toggle';
import { useOfflineVoteQueue } from '@/hooks/useOfflineVoteQueue';
import { useOnlineStatus } from '@/hooks/useOnlineStatus';
import { useTranslations } from '@/lib/i18n';
import { canUseSecretariatFloor, sessionDisplayTitle, withHonorific } from '@/lib/sessionFloor';
import { registerSessionFloorPwa } from '@/lib/sessionFloorPwa';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Menu, Search } from 'lucide-react';
import { FormEvent, useEffect, useState, type ReactNode } from 'react';

/**
 * The floor. Tablet-first, because this is read from a bench across a chamber
 * under time pressure, sometimes with no network. Everything here is larger,
 * calmer and more literal than the desk surfaces: one panel at a time, one
 * available action, and connectivity stated rather than implied.
 *
 * `workstation` fills the viewport below the chrome so an embedded PDF has a
 * bounded height instead of scrolling the whole page away from the vote bar.
 *
 * `display` drops the chrome entirely. It is for the board projected to the
 * whole hall, where a search field and an account menu are not just useless
 * but misleading — nobody in the gallery can act on them, and every pixel they
 * take is a pixel off the agenda item the room is being asked to follow.
 */

type SessionLayoutProps = {
    title?: string;
    sessionTitle?: string;
    sessionId?: string;
    sessionStatus?: string;
    venue?: string | null;
    presidingOfficer?: string | null;
    headerActions?: ReactNode;
    /** Overrides the session title in the floor chrome. */
    heading?: string;
    /** Overrides the organisation / venue line under the heading. */
    description?: string;
    /** Label for the in-session badge. Defaults to “Live”. */
    liveLabel?: string;
    variant?: 'default' | 'workstation' | 'display';
    children: ReactNode;
};

export default function SessionLayout({
    title,
    sessionTitle,
    sessionId,
    sessionStatus,
    venue,
    presidingOfficer,
    headerActions,
    heading,
    description,
    liveLabel,
    variant = 'default',
    children,
}: SessionLayoutProps) {
    const { auth, organization } = usePage<PageProps>().props;
    const { url } = usePage();
    const { t } = useTranslations();
    const pageTitle = title ? `${title} · ${t('app.name')}` : t('app.name');
    const online = useOnlineStatus();
    const { pending, flushing } = useOfflineVoteQueue();
    const display = variant === 'display';
    const workstation = variant === 'workstation';
    const filled = workstation || display;
    const live = sessionStatus === 'in-session';
    const [quickFind, setQuickFind] = useState('');

    useEffect(() => {
        if (!sessionId) {
            return;
        }

        const cacheUrl = `/sessions/${sessionId}/floor/cache`;

        void fetch(cacheUrl, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                void registerSessionFloorPwa({
                    sessionId,
                    cacheUrl,
                    documentUrls: Array.isArray(data?.document_urls) ? data.document_urls : [],
                });
            })
            .catch(() => {
                void registerSessionFloorPwa({ sessionId, cacheUrl, documentUrls: [] });
            });
    }, [sessionId]);

    const views = sessionId
        ? [
              { href: `/sessions/${sessionId}/floor/member`, label: t('sessions.floor.member') },
              ...(canUseSecretariatFloor(auth.user)
                  ? [
                        { href: `/sessions/${sessionId}/floor/secretariat`, label: t('sessions.floor.secretariat') },
                        { href: `/sessions/${sessionId}/floor/minutes`, label: t('sessions.floor.tab_minutes') },
                        { href: `/sessions/${sessionId}/floor/recording`, label: t('sessions.floor.tab_recording') },
                        { href: `/sessions/${sessionId}/capture`, label: t('capture.nav') },
                    ]
                  : []),
              { href: `/sessions/${sessionId}/floor/dashboard`, label: t('sessions.floor.dashboard') },
              { href: `/sessions/${sessionId}/attendance`, label: t('sessions.attendance') },
              { href: `/sessions/${sessionId}/transcript`, label: t('transcripts.live') },
          ]
        : [];

    const path = url.split('?')[0];

    const displayTitle = heading ?? sessionDisplayTitle(sessionTitle);
    const officer = withHonorific(presidingOfficer);

    const metaBits = [organization.name, venue, officer ? t('sessions.presided_by', { name: officer }) : null].filter(Boolean);
    const subtitle =
        description ??
        (metaBits.length > 0 ? metaBits.join(' · ') : [organization.short_name, title].filter(Boolean).join(' · '));

    function submitQuickFind(event: FormEvent) {
        event.preventDefault();
        const q = quickFind.trim();
        router.get('/documents', q ? { search: q } : {}, { preserveState: false });
    }

    if (display) {
        return (
            <div className="flex h-dvh flex-col overflow-hidden bg-canvas text-ink">
                <Head title={pageTitle} />
                <main id="main" className="flex min-h-0 flex-1 flex-col overflow-hidden">
                    {children}
                </main>
            </div>
        );
    }

    return (
        <SessionFloorToolsProvider>
            <div className={cn('bg-canvas text-ink', filled ? 'flex h-dvh flex-col overflow-hidden' : 'min-h-screen')}>
                <Head title={pageTitle} />

                <a
                    href="#main"
                    className="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-[var(--radius-md)] focus:border focus:border-accent focus:bg-surface focus:px-3 focus:py-2 focus:text-sm focus:font-medium"
                >
                    {t('a11y.skip')}
                </a>

                <div className="sticky top-0 z-30 shrink-0 border-b border-line bg-surface/95 backdrop-blur-sm">
                    {!workstation ? (
                        <div className="mx-auto flex h-14 w-full max-w-7xl items-center gap-3 border-b border-line px-4 md:px-6">
                            <Button variant="ghost" size="icon-sm" asChild>
                                <Link href="/dashboard" aria-label={t('a11y.open_nav')}>
                                    <Menu aria-hidden="true" strokeWidth={1.75} className="size-4" />
                                </Link>
                            </Button>

                            <form onSubmit={submitQuickFind} className="relative max-w-xl min-w-0 flex-1">
                                <Search
                                    aria-hidden="true"
                                    strokeWidth={1.75}
                                    className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-ink-faint"
                                />
                                <Input
                                    type="search"
                                    name="search"
                                    value={quickFind}
                                    onChange={(event) => setQuickFind(event.target.value)}
                                    placeholder={t('sessions.floor.quick_find')}
                                    aria-label={t('sessions.floor.quick_find')}
                                    className="h-9 border-line bg-canvas-sunk pl-9"
                                />
                            </form>

                            <div className="ml-auto flex shrink-0 items-center gap-1.5 sm:gap-2">
                                <ThemeToggle />
                                <NotificationBell />
                                <UserMenu />
                            </div>
                        </div>
                    ) : null}

                    <div className={cn('mx-auto w-full px-4 py-4 md:px-6', workstation ? 'max-w-none' : 'max-w-7xl')}>
                        <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="label-eyebrow text-ink-subtle">{t('sessions.floor.label')}</span>
                                    {live ? (
                                        <span className="inline-flex items-center gap-1.5 rounded-full border border-[var(--color-live-line)] bg-live-soft px-2 py-0.5 text-2xs font-semibold tracking-wide text-[var(--color-live-ink)] uppercase">
                                            <LiveDot className="size-1.5" />
                                            {liveLabel ?? t('sessions.floor.live')}
                                        </span>
                                    ) : null}
                                </div>

                                {displayTitle ? (
                                    <h1 className="mt-1.5 text-2xl font-semibold tracking-[-0.02em] text-ink md:text-[1.75rem]">
                                        {displayTitle}
                                    </h1>
                                ) : null}

                                <p className="mt-1 truncate text-sm text-ink-muted">{subtitle}</p>
                            </div>

                            <div className="flex shrink-0 flex-wrap items-start justify-end gap-3">
                                <div className="flex flex-col items-stretch gap-2">
                                    {workstation ? (
                                        <div className="flex items-center justify-end gap-2">
                                            <UserMenu caption={auth.user?.district} honorific />
                                        </div>
                                    ) : null}
                                    {sessionId ? (
                                        <Button variant="secondary" size="default" className="w-full" asChild>
                                            <Link href={`/sessions/${sessionId}`}>
                                                <ArrowLeft aria-hidden="true" strokeWidth={1.75} className="size-4" />
                                                <span className="hidden sm:inline">{t('sessions.back')}</span>
                                            </Link>
                                        </Button>
                                    ) : null}
                                </div>
                                {headerActions}
                            </div>
                        </div>

                        {views.length > 0 && !workstation ? (
                            <nav
                                aria-label={t('sessions.floor.views')}
                                className="mt-4 inline-flex max-w-full flex-wrap gap-1 rounded-full border border-line bg-canvas-sunk p-1"
                            >
                                {views.map((view) => {
                                    const active = path === view.href;

                                    return (
                                        <Link
                                            key={view.href}
                                            href={view.href}
                                            aria-current={active ? 'page' : undefined}
                                            className={cn(
                                                'min-h-10 rounded-full px-3.5 py-2 text-sm font-medium transition-colors',
                                                active
                                                    ? 'bg-surface text-ink shadow-[var(--shadow-xs)]'
                                                    : 'text-ink-muted hover:bg-surface/70 hover:text-ink',
                                            )}
                                        >
                                            {view.label}
                                        </Link>
                                    );
                                })}
                            </nav>
                        ) : null}
                    </div>
                </div>

                <main
                    id="main"
                    className={cn(
                        'w-full',
                        workstation
                            ? 'mx-0 flex min-h-0 flex-1 flex-col overflow-hidden px-0 py-0'
                            : 'mx-auto max-w-7xl px-4 py-5 md:px-6 md:py-6',
                    )}
                >
                    <OfflineIndicator
                        online={online}
                        pendingVotes={pending.length}
                        flushing={flushing}
                        className={workstation ? 'mx-5 mt-4 shrink-0' : 'mb-4'}
                    />
                    <CaptureIndicator className={workstation ? 'mx-5 mt-4 shrink-0' : 'mb-4'} />
                    <FlashRegion />
                    {workstation ? <div className="flex min-h-0 flex-1 flex-col overflow-hidden">{children}</div> : children}
                </main>

                {sessionId ? (
                    <SessionChatDock
                        sessionId={sessionId}
                        sessionStatus={sessionStatus}
                        size={workstation ? 'floor' : 'default'}
                    />
                ) : null}

                {/* Larger and centred: a receipt on the floor is read from a bench, not a desk. */}
                <Toaster position="bottom-center" className="text-md" />
            </div>
        </SessionFloorToolsProvider>
    );
}
