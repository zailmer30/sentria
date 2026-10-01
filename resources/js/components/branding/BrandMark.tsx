import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';

function initialMark(shortName: string, name: string): string {
    const source = (shortName || name || 'S').trim();
    const chars = Array.from(source);

    return (chars[0] ?? 'S').toUpperCase();
}

type BrandMarkProps = {
    className?: string;
    fallbackClassName?: string;
    logoUrl?: string | null;
    shortName?: string;
    name?: string;
};

export function BrandMark({ className, fallbackClassName, logoUrl, shortName, name }: BrandMarkProps) {
    const page = usePage<PageProps>().props;
    const resolvedLogo = logoUrl === undefined ? page.branding.logo_url : logoUrl;
    const resolvedShort = shortName ?? page.organization.short_name;
    const resolvedName = name ?? page.organization.name;
    const hasLogo = Boolean(resolvedLogo);

    return (
        <span
            aria-hidden="true"
            className={cn(
                'flex size-10 shrink-0 items-center justify-center',
                hasLogo
                    ? 'overflow-visible bg-transparent'
                    : cn(
                          'overflow-hidden rounded-[var(--radius-md)] bg-accent font-mono text-base font-semibold text-[var(--color-accent-on)]',
                          fallbackClassName,
                      ),
                className,
            )}
        >
            {hasLogo ? (
                <img src={resolvedLogo ?? undefined} alt="" className="size-full object-contain p-0.5" />
            ) : (
                initialMark(resolvedShort, resolvedName)
            )}
        </span>
    );
}
