import { UserAvatar } from '@/components/users/UserAvatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/lib/i18n';
import { withHonorific } from '@/lib/sessionFloor';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, LogOut, UserRound } from 'lucide-react';

/**
 * Who is signed in, and under which role — which matters here, because every
 * page a user sees is a consequence of their permissions. The role is stated on
 * the trigger, not hidden in the menu, so nobody has to open anything to find
 * out which capacity they are acting in.
 */
export function UserMenu({
    caption,
    honorific = false,
}: {
    /** Overrides the role line under the name (used on the floor for district). */
    caption?: string | null;
    honorific?: boolean;
}) {
    const { auth } = usePage<PageProps>().props;
    const { t } = useTranslations();
    const user = auth.user;

    if (!user) {
        return null;
    }

    // Role slugs arrive as `presiding-officer`; the catalogue keys match.
    const primaryRole = user.roles[0] ?? null;
    const name = honorific ? withHonorific(user.display_name) ?? user.display_name : user.display_name;
    const secondary = caption || (primaryRole ? t(`roles.${primaryRole}`) : null);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="flex max-w-72 items-center gap-2.5 rounded-[var(--radius-md)] py-1.5 pr-2.5 pl-1.5 text-left transition-colors duration-[var(--duration-fast)] hover:bg-canvas-sunk">
                <UserAvatar name={user.display_name} src={user.avatar_url} className="size-9" fallbackClassName="text-xs" />
                <span className="hidden min-w-0 sm:block">
                    <span className="block truncate text-sm font-medium text-ink">{name}</span>
                    {secondary ? (
                        <span className="block truncate text-xs text-ink-faint">{secondary}</span>
                    ) : null}
                </span>
                <ChevronDown aria-hidden="true" strokeWidth={2} className="size-4 shrink-0 text-ink-faint" />
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="min-w-60">
                <div className="px-2 py-1.5">
                    <p className="truncate text-sm font-medium text-ink">{name}</p>
                    <p className="truncate text-xs text-ink-subtle">{user.email}</p>
                    {primaryRole ? <p className="label-eyebrow mt-1.5">{t(`roles.${primaryRole}`)}</p> : null}
                </div>

                <DropdownMenuSeparator />

                <DropdownMenuItem asChild>
                    <Link href="/profile" className="w-full">
                        <UserRound aria-hidden="true" strokeWidth={1.75} />
                        {t('profile.menu')}
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuItem asChild>
                    <Link href="/logout" method="post" as="button" className="w-full">
                        <LogOut aria-hidden="true" strokeWidth={1.75} />
                        {t('auth.sign_out')}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
