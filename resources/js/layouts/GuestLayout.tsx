import { BrandMark } from '@/components/branding/BrandMark';
import { FlashRegion } from '@/components/ui/flash';
import { Toaster } from '@/components/ui/sonner';
import { ThemeToggle } from '@/components/ui/theme-toggle';
import { useTranslations } from '@/lib/i18n';
import type { PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

/**
 * Auth surfaces: one centred panel, the wordmark, and the organisation under it.
 * Quiet on purpose — the sign-in form is the only job on this page.
 */

type GuestLayoutProps = {
    title?: string;
    children: ReactNode;
};

export default function GuestLayout({ title, children }: GuestLayoutProps) {
    const { organization } = usePage<PageProps>().props;
    const { t } = useTranslations();
    const pageTitle = title ? `${title} · ${t('app.name')}` : t('app.name');

    return (
        <div className="relative min-h-screen bg-shell text-ink">
            <Head title={pageTitle} />

            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-[var(--radius-md)] focus:border focus:border-accent focus:bg-surface focus:px-3 focus:py-2 focus:text-sm focus:font-medium"
            >
                {t('a11y.skip')}
            </a>

            <div aria-hidden="true" className="pointer-events-none fixed inset-x-0 top-0 h-0.5 bg-accent" />

            <div className="absolute top-4 right-4">
                <ThemeToggle />
            </div>

            <main id="main" className="mx-auto flex min-h-screen w-full max-w-lg flex-col justify-center px-5 py-14">
                <div className="app-sheet px-7 py-8 sm:px-9 sm:py-10">
                    <div className="mb-7">
                        <BrandMark className="mb-4" />
                        <p className="text-2xl font-semibold tracking-[-0.02em] text-ink">{t('app.name')}</p>
                        <p className="mt-2 max-w-sm text-sm text-ink-muted">{t('app.tagline')}</p>
                        <div className="mt-4 border-t border-line pt-3">
                            <p className="text-xs font-medium text-ink-muted">{organization.name}</p>
                            <p className="text-2xs text-ink-faint">{organization.locality}</p>
                        </div>
                    </div>

                    <FlashRegion />

                    {children}
                </div>
            </main>

            <Toaster />
        </div>
    );
}
