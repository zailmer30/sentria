import { AppSidebar, useCurrentSection } from '@/components/layout/AppSidebar';
import { NotificationBell } from '@/components/layout/NotificationBell';
import { UserMenu } from '@/components/layout/UserMenu';
import { CaptureIndicator } from '@/components/session/CaptureIndicator';
import { FlashRegion } from '@/components/ui/flash';
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
import { ThemeToggle } from '@/components/ui/theme-toggle';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * The staff shell. Everything the application contains sits on one sheet that
 * floats clear of the viewport edge, and the frame around it is what makes the
 * product read as a document under review rather than as a web page.
 *
 * The sheet is a fixed-height frame and the content column is the thing that
 * scrolls. That is what lets the rail, the top bar and a register's column
 * heads stay put while forty rows move underneath them — a sticky element is
 * positioned against its nearest scrolling ancestor, so the scroll has to
 * belong to the content rather than to the window. `scroll-region` hands that
 * scroll position to Inertia so it is restored across visits.
 *
 * The rail collapses to icons and back; below the large breakpoint it becomes a
 * sheet with a real focus trap. The page owns its own h1 via `PageHeader`, so
 * the top bar carries only the section it sits in and never stacks a second
 * label above a heading.
 */

type AppLayoutProps = {
    title?: string;
    children: ReactNode;
    /** Drops the content max-width for full-bleed surfaces like the AI console. */
    wide?: boolean;
};

export default function AppLayout({ title, children, wide = false }: AppLayoutProps) {
    const { organization } = usePage<PageProps>().props;
    const { t } = useTranslations();
    const section = useCurrentSection();
    const pageTitle = title ? `${title} · ${t('app.name')}` : t('app.name');

    return (
        <div className="min-h-screen bg-shell text-ink">
            <Head title={pageTitle} />

            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-[var(--radius-md)] focus:border focus:border-accent focus:bg-surface focus:px-3 focus:py-2 focus:text-sm focus:font-medium"
            >
                {t('a11y.skip')}
            </a>

            <div className="p-0 lg:p-3">
                <div className="app-sheet h-dvh overflow-hidden lg:h-[calc(100dvh-1.5rem)]">
                    <SidebarProvider className="relative h-full min-h-0">
                        <AppSidebar />

                        <SidebarInset className="min-h-0 overflow-hidden border-l border-line bg-canvas">
                            <header className="z-30 flex h-16 shrink-0 items-center gap-3 border-b border-line bg-canvas px-4 sm:px-6">
                                <SidebarTrigger aria-label={t('a11y.open_nav')} className="lg:hidden">
                                    <Menu aria-hidden="true" strokeWidth={1.75} className="size-4" />
                                </SidebarTrigger>

                                <p className="min-w-0 flex-1 truncate text-md font-semibold text-ink">
                                    <span className="lg:hidden">{organization.short_name}</span>
                                    <span className="hidden lg:inline">{section ?? organization.name}</span>
                                </p>

                                <div className="flex shrink-0 items-center gap-2">
                                    <ThemeToggle />
                                    <NotificationBell />
                                    <UserMenu />
                                </div>
                            </header>

                            <main id="main" scroll-region="" className="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                                <div
                                    className={cn('px-3 py-5 sm:px-5 sm:py-6', wide ? 'w-full' : 'mx-auto w-full max-w-[92rem]')}
                                >
                                    <CaptureIndicator className="mb-4" />
                                    <FlashRegion />
                                    {children}
                                </div>
                            </main>
                        </SidebarInset>
                    </SidebarProvider>
                </div>
            </div>

            <Toaster />
        </div>
    );
}
