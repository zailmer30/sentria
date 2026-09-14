import type { AuthUser } from '@/types';

export type FloorView = 'member' | 'secretariat' | 'minutes' | 'dashboard';

const HONORIFIC = /^(Hon\.|Atty\.|Engr\.|Dr\.|Fr\.|Ms\.|Mr\.|Mrs\.)\s/i;

/**
 * Sitting titles arrive from the register in whatever case they were typed.
 * The chamber cites them in title case.
 */
export function sessionDisplayTitle(title: string | null | undefined): string | null {
    if (!title) {
        return null;
    }

    return title.replace(/\w\S*/g, (word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase());
}

/** Officers are named with their honorific on the floor, never bare. */
export function withHonorific(name: string | null | undefined): string | null {
    const trimmed = name?.trim();

    if (!trimmed) {
        return null;
    }

    return HONORIFIC.test(trimmed) ? trimmed : `Hon. ${trimmed}`;
}

/**
 * Default floor surface for "Open floor": the clerk's console, or the member
 * workstation everyone else — including the chair — reads from.
 */
export function preferredFloorView(user: AuthUser | null | undefined): FloorView {
    const roles = user?.roles ?? [];

    if (roles.includes('secretariat') || roles.includes('system-administrator')) {
        return 'secretariat';
    }

    return 'member';
}

export function floorPath(sessionId: string, view: FloorView = 'member'): string {
    return `/sessions/${sessionId}/floor/${view}`;
}

export function preferredFloorPath(sessionId: string, user: AuthUser | null | undefined): string {
    return floorPath(sessionId, preferredFloorView(user));
}

export function canUseSecretariatFloor(user: AuthUser | null | undefined): boolean {
    if (!user) {
        return false;
    }

    return (
        user.roles.includes('secretariat') ||
        user.roles.includes('system-administrator') ||
        user.permissions.includes('agenda.manage') ||
        user.permissions.includes('attendance.record')
    );
}
