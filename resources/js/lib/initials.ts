export function initials(name: string | null | undefined): string {
    if (!name) {
        return '?';
    }

    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0] ?? '')
        .join('')
        .toUpperCase();
}
