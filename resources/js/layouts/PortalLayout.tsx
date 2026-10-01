/* eslint-disable react/no-unknown-property -- Inertia Head meta tags use head-key for SSR deduplication */
import { BrandMark } from '@/components/branding/BrandMark';
import { Button } from '@/components/ui/button';
import { PortalContainer } from '@/components/portal/PortalContainer';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * The public portal. Wider measure, quieter density than the staff shell —
 * citizens read here, they do not operate. Unpublished records are absent,
 * never teased.
 */

type PortalLayoutProps = {
    title?: string;
    description?: string;
    ogType?: string;
    ogUrl?: string;
    children: ReactNode;
};

export default function PortalLayout({ title, description, ogType = 'website', ogUrl, children }: PortalLayoutProps) {
    const { organization } = usePage<PageProps>().props;
    const { url } = usePage();
    const { t } = useTranslations();
    const pageTitle = title ? `${title} · ${t('portal.site_name')}` : t('portal.site_name');
    const path = url.split('?')[0] ?? '';

    const links = [
        { href: '/portal', label: t('portal.nav.search'), match: (current: string) => current === '/portal' || current.startsWith('/portal/search') },
        { href: '/portal/sessions', label: t('portal.nav.sessions'), match: (current: string) => current.startsWith('/portal/sessions') },
    ];

    return (
        <div className="portal-shell flex min-h-screen flex-col">
            <Head title={pageTitle}>
                {description ? <meta head-key="description" name="description" content={description} /> : null}
                {title ? <meta head-key="og:title" property="og:title" content={pageTitle} /> : null}
                {description ? <meta head-key="og:description" property="og:description" content={description} /> : null}
                <meta head-key="og:type" property="og:type" content={ogType} />
                {ogUrl ? <meta head-key="og:url" property="og:url" content={ogUrl} /> : null}
            </Head>

            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-[var(--radius-md)] focus:border focus:border-accent focus:bg-surface focus:px-3 focus:py-2 focus:text-sm focus:font-medium"
            >
                {t('a11y.skip')}
            </a>

            <header
                data-print-hide
                className="sticky top-0 z-40 h-16 border-b border-line bg-canvas/85 backdrop-blur"
            >
                <PortalContainer className="flex h-full items-center justify-between gap-6">
                    <Link href="/portal" className="flex min-w-0 items-center gap-3">
                        <BrandMark fallbackClassName="bg-plate text-accent-on shadow-plate" />
                        <span className="min-w-0 leading-tight">
                            <span className="font-display block text-lg font-bold tracking-tight text-ink">
                                {t('app.name')}
                            </span>
                            <span className="block truncate text-xs text-ink-muted">
                                {organization.name}
                                <span> · {organization.locality}</span>
                            </span>
                        </span>
                    </Link>

                    <div className="flex items-center gap-2">
                        <nav aria-label={t('portal.nav_label')} className="hidden items-center gap-1 md:flex">
                            {links.map((link) => {
                                const active = link.match(path);

                                return (
                                    <Link
                                        key={link.href}
                                        href={link.href}
                                        aria-current={active ? 'page' : undefined}
                                        data-status={active ? 'active' : undefined}
                                        className={cn(
                                            'rounded-[var(--radius-md)] px-3 py-1.5 text-sm font-medium transition-colors',
                                            'text-ink-muted hover:bg-canvas-sunk hover:text-ink',
                                            'data-[status=active]:bg-accent-soft data-[status=active]:text-ink',
                                        )}
                                    >
                                        {link.label}
                                    </Link>
                                );
                            })}
                        </nav>

                        <Button variant="primary" size="sm" asChild>
                            <Link href="/login">
                                {t('portal.nav.staff_login')}
                                <LogIn aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
                            </Link>
                        </Button>
                    </div>
                </PortalContainer>
            </header>

            <div id="main" className="flex flex-1 flex-col">
                {children}
            </div>

            <footer data-print-hide className="mt-auto border-t border-line bg-surface-alt">
                <PortalContainer className="py-6">
                    <p className="max-w-3xl text-xs text-ink-subtle">{t('portal.footer_notice')}</p>
                    <p className="mt-1 text-xs text-ink-subtle">{t('portal.footer_address')}</p>
                </PortalContainer>
            </footer>
        </div>
    );
}
