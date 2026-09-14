import { useTranslations } from '@/lib/i18n';
import { childIsCurrent, LEGISLATION_REGISTERS } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';

/**
 * Page-level twin of the Legislation rail children, so the two registers stay
 * reachable when the sidebar is collapsed to icons.
 */
export function LegislationRegisterNav() {
    const { t } = useTranslations();
    const { url } = usePage();

    return (
        <nav
            aria-label={t('a11y.legislation_nav')}
            className="flex flex-wrap items-center gap-5 border-b border-line"
        >
            {LEGISLATION_REGISTERS.map((item) => {
                const active = childIsCurrent(url, item);

                return (
                    <Link
                        key={item.key}
                        href={item.href}
                        aria-current={active ? 'page' : undefined}
                        className={cn(
                            '-mb-px inline-flex items-center border-b-2 px-1 pb-2.5 text-sm transition-colors',
                            active
                                ? 'border-accent font-medium text-accent'
                                : 'border-transparent text-ink-muted hover:text-ink',
                        )}
                    >
                        {t(item.labelKey)}
                    </Link>
                );
            })}
        </nav>
    );
}
