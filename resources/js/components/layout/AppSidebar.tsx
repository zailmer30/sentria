import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarTrigger,
    useSidebar,
} from '@/components/ui/sidebar';
import { useTranslations } from '@/lib/i18n';
import {
    canAccess,
    childIsCurrent,
    currentNavEntry,
    groupedNavigation,
    isCurrent,
    NAV_GROUPS,
    navigationFor,
    ungroupedNavigation,
    type NavEntry,
} from '@/lib/navigation';
import { cn } from '@/lib/utils';
import type { AuthUser, PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { PanelLeftClose, PanelLeftOpen } from 'lucide-react';

/**
 * The rail carries the organisation's identity and exactly one lit destination
 * at a time — where you are, and where you can go next. Nested registers sit
 * under their section; the accent stays on the child that matches the page.
 *
 * Collapsed, the rail keeps its icons and hands the labels to tooltips, so the
 * shape of the navigation survives even when its words do not.
 */

export function AppSidebar() {
    const { auth, organization } = usePage<PageProps>().props;
    const { url } = usePage();
    const { t } = useTranslations();
    const { state, isMobile } = useSidebar();

    const items = navigationFor(auth.user);
    const loose = ungroupedNavigation(items);
    const collapsed = state === 'collapsed' && !isMobile;

    return (
        <Sidebar mobileTitle={t('a11y.primary_nav')}>
            <SidebarHeader>
                <div className={cn('flex items-center gap-2.5', collapsed && 'flex-col gap-2')}>
                    <Link
                        href="/dashboard"
                        className={cn(
                            'flex min-w-0 flex-1 items-center gap-3 rounded-[var(--radius-md)] py-0.5',
                            collapsed ? 'justify-center px-0' : 'px-0.5',
                        )}
                    >
                        <span
                            aria-hidden="true"
                            className="flex size-10 shrink-0 items-center justify-center rounded-[var(--radius-md)] bg-accent font-mono text-base font-semibold text-[var(--color-accent-on)]"
                        >
                            S
                        </span>
                        <span className={cn('min-w-0', collapsed && 'hidden')}>
                            <span className="block truncate text-md font-semibold tracking-[-0.01em] text-ink">
                                {t('app.name')}
                            </span>
                            <span className="block truncate text-xs text-ink-faint">{organization.short_name}</span>
                        </span>
                    </Link>

                    {!isMobile ? (
                        <SidebarTrigger
                            aria-label={collapsed ? t('a11y.expand_nav') : t('a11y.collapse_nav')}
                            className="shrink-0"
                            title={collapsed ? t('a11y.expand_nav') : t('a11y.collapse_nav')}
                        >
                            {collapsed ? (
                                <PanelLeftOpen aria-hidden="true" strokeWidth={1.75} className="size-4" />
                            ) : (
                                <PanelLeftClose aria-hidden="true" strokeWidth={1.75} className="size-4" />
                            )}
                        </SidebarTrigger>
                    ) : null}
                </div>
            </SidebarHeader>

            <SidebarContent>
                <nav aria-label={t('a11y.primary_nav')} className="flex flex-col gap-4">
                    {loose.length > 0 ? (
                        <SidebarGroup>
                            <SidebarMenu>
                                {loose.map((item) => (
                                    <NavItem key={item.key} item={item} url={url} user={auth.user} />
                                ))}
                            </SidebarMenu>
                        </SidebarGroup>
                    ) : null}

                    {NAV_GROUPS.map((group) => {
                        const groupItems = groupedNavigation(items, group.key);

                        if (groupItems.length === 0) {
                            return null;
                        }

                        return (
                            <SidebarGroup key={group.key}>
                                <SidebarGroupLabel>{t(group.labelKey)}</SidebarGroupLabel>
                                <SidebarMenu>
                                    {groupItems.map((item) => (
                                        <NavItem key={item.key} item={item} url={url} user={auth.user} />
                                    ))}
                                </SidebarMenu>
                            </SidebarGroup>
                        );
                    })}
                </nav>
            </SidebarContent>

            <SidebarFooter>
                {collapsed ? null : (
                    <p className="px-1 text-2xs leading-relaxed text-ink-faint">{organization.locality}</p>
                )}
            </SidebarFooter>
        </Sidebar>
    );
}

function NavItem({ item, url, user }: { item: NavEntry; url: string; user: AuthUser | null }) {
    const { t } = useTranslations();
    const { isMobile, setOpenMobile, state } = useSidebar();
    const children = (item.children ?? []).filter((child) => canAccess(user, child));
    const showChildren = children.length > 0 && (isMobile || state !== 'collapsed');
    const sectionActive = isCurrent(url, item);
    const active = showChildren ? false : sectionActive;
    const Icon = item.icon;
    const label = t(item.labelKey);

    function closeMobile(): void {
        if (isMobile) {
            setOpenMobile(false);
        }
    }

    return (
        <SidebarMenuItem>
            <SidebarMenuButton asChild isActive={active} tooltip={label}>
                <Link
                    href={item.href}
                    aria-current={!showChildren && active ? 'page' : undefined}
                    onClick={closeMobile}
                >
                    <Icon aria-hidden="true" strokeWidth={1.75} />
                    <span>{label}</span>
                </Link>
            </SidebarMenuButton>
            {showChildren ? (
                <SidebarMenuSub aria-label={label}>
                    {children.map((child) => {
                        const childActive = childIsCurrent(url, child);

                        return (
                            <SidebarMenuSubItem key={child.key}>
                                <SidebarMenuSubButton asChild isActive={childActive}>
                                    <Link
                                        href={child.href}
                                        aria-current={childActive ? 'page' : undefined}
                                        onClick={closeMobile}
                                    >
                                        {t(child.labelKey)}
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        );
                    })}
                </SidebarMenuSub>
            ) : null}
        </SidebarMenuItem>
    );
}

/** The section name for the shell's top bar. */
export function useCurrentSection(): string | null {
    const { auth } = usePage<PageProps>().props;
    const { url } = usePage();
    const { t } = useTranslations();
    const entry = currentNavEntry(url, navigationFor(auth.user));

    return entry ? t(entry.labelKey) : null;
}
