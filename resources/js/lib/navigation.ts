import type { AuthUser, NavItem } from '@/types';
import {
    Archive,
    BookMarked,
    Bot,
    Building2,
    FileText,
    Gauge,
    Gavel,
    Globe,
    LayoutPanelTop,
    ListChecks,
    Mic,
    ScrollText,
    Settings,
    Shield,
    ShieldCheck,
    Users,
    type LucideIcon,
} from 'lucide-react';

/**
 * Navigation is permission-driven: items without a matching permission (or
 * role, when specified) are omitted.
 *
 * Grouping follows how the work actually divides — what happens in the chamber,
 * what is held in the record, what assists the work, and what oversees it —
 * rather than one flat list of fifteen links.
 */

export type NavGroupKey = 'chamber' | 'record' | 'assist' | 'oversight';

export type NavEntry = NavItem & {
    icon: LucideIcon;
    group?: NavGroupKey;
};

export const LEGISLATION_REGISTERS: NavItem[] = [
    {
        key: 'ordinances',
        href: '/ordinances',
        labelKey: 'nav.ordinances',
        permission: 'legislation.viewAny',
    },
    {
        key: 'resolutions',
        href: '/resolutions',
        labelKey: 'nav.resolutions',
        permission: 'legislation.viewAny',
    },
];

export const NAV_ITEMS: NavEntry[] = [
    { key: 'dashboard', href: '/dashboard', labelKey: 'nav.dashboard', icon: LayoutPanelTop },

    {
        key: 'sessions',
        href: '/sessions',
        labelKey: 'nav.sessions',
        permission: 'sessions.viewAny',
        icon: Gavel,
        group: 'chamber',
    },
    {
        key: 'minutes',
        href: '/minutes',
        labelKey: 'nav.minutes',
        permission: 'minutes.viewAny',
        icon: ListChecks,
        group: 'chamber',
    },
    {
        key: 'chamber-channels',
        href: '/settings/chamber-channels',
        labelKey: 'nav.chamber_microphones',
        permission: 'settings.chamber',
        icon: Mic,
        group: 'chamber',
    },

    {
        key: 'documents',
        href: '/documents',
        labelKey: 'nav.documents',
        permission: 'documents.viewAny',
        icon: FileText,
        group: 'record',
    },
    {
        key: 'legislation',
        href: '/legislation',
        labelKey: 'nav.legislation',
        permission: 'legislation.viewAny',
        icon: ScrollText,
        group: 'record',
        children: LEGISLATION_REGISTERS,
    },
    {
        key: 'committees',
        href: '/committees',
        labelKey: 'nav.committees',
        permission: 'committees.viewAny',
        icon: Building2,
        group: 'record',
    },
    {
        key: 'publications',
        href: '/publications',
        labelKey: 'nav.publications',
        permission: 'publications.viewAny',
        icon: BookMarked,
        group: 'record',
    },

    { key: 'ai', href: '/ai', labelKey: 'nav.ai', permission: 'ai.use', icon: Bot, group: 'assist' },

    { key: 'audit', href: '/audit', labelKey: 'nav.audit', permission: 'audit.viewAny', icon: ShieldCheck, group: 'oversight' },
    {
        key: 'users',
        href: '/users',
        labelKey: 'nav.users',
        permission: 'users.viewAny',
        icon: Users,
        group: 'oversight',
    },
    {
        key: 'roles',
        href: '/roles',
        labelKey: 'nav.roles',
        permission: 'roles.viewAny',
        icon: Shield,
        group: 'oversight',
    },
    {
        key: 'monitoring',
        href: '/admin/monitoring',
        labelKey: 'nav.monitoring',
        permission: 'settings.viewAny',
        icon: Gauge,
        group: 'oversight',
    },
    {
        key: 'horizon',
        href: '/horizon',
        labelKey: 'nav.horizon',
        roles: ['system-administrator', 'secretariat'],
        icon: Archive,
        group: 'oversight',
    },
    {
        key: 'settings',
        href: '/settings',
        labelKey: 'nav.settings',
        permission: 'settings.viewAny',
        icon: Settings,
        group: 'oversight',
    },

    { key: 'portal', href: '/portal', labelKey: 'nav.portal', permission: 'portal.view', roles: ['public-user'], icon: Globe },
];

/** Group order and their labels, rendered as rail section dividers. */
export const NAV_GROUPS: { key: NavGroupKey; labelKey: string }[] = [
    { key: 'chamber', labelKey: 'nav.group_chamber' },
    { key: 'record', labelKey: 'nav.group_record' },
    { key: 'assist', labelKey: 'nav.group_assist' },
    { key: 'oversight', labelKey: 'nav.group_oversight' },
];

export function canAccess(user: AuthUser | null, item: NavItem): boolean {
    if (!user) {
        return false;
    }

    if (item.roles && item.roles.length > 0) {
        const hasRole = item.roles.some((role) => user.roles.includes(role));
        if (!hasRole && item.permission && !user.permissions.includes(item.permission)) {
            return false;
        }
        if (hasRole) {
            return true;
        }
    }

    if (item.permission) {
        return user.permissions.includes(item.permission);
    }

    return true;
}

export function navigationFor(user: AuthUser | null): NavEntry[] {
    return NAV_ITEMS.filter((item) => canAccess(user, item));
}

/** Items with no group sit above the dividers. */
export function ungroupedNavigation(items: NavEntry[]): NavEntry[] {
    return items.filter((item) => !item.group);
}

export function groupedNavigation(items: NavEntry[], group: NavGroupKey): NavEntry[] {
    return items.filter((item) => item.group === group);
}

/**
 * `/legislation` redirects to `/ordinances`, and detail routes nest under their
 * index, so a prefix match keeps the correct tab lit on a child page.
 */
export function isCurrent(url: string, item: NavItem): boolean {
    const path = url.split('?')[0] ?? '';

    if (item.key === 'legislation') {
        return /^\/(legislation|ordinances|resolutions)(\/|$)/.test(path);
    }

    if (path === item.href) {
        return true;
    }

    return path.startsWith(`${item.href}/`);
}

export function childIsCurrent(url: string, child: NavItem): boolean {
    const path = url.split('?')[0] ?? '';

    return path === child.href || path.startsWith(`${child.href}/`);
}

/** The section label shown in the top strip. */
export function currentNavEntry(url: string, items: NavEntry[]): NavEntry | null {
    return items.find((item) => item.group && isCurrent(url, item)) ?? items.find((item) => isCurrent(url, item)) ?? null;
}
