import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Sparkles } from 'lucide-react';
import type { ReactNode } from 'react';

type AiContentProps = {
    children: ReactNode;
    className?: string;
    showBadge?: boolean;
    /** One-line banner: badge and copy share a single row. */
    compact?: boolean;
};

/**
 * Machine output is separated from the record by material, not by a brand
 * colour: a cooler surface carrying a faint diagonal hatch that nothing in the
 * record ever wears, plus a label that does not go away. A reader must never
 * have to work out whether they are looking at a draft or an approved fact.
 */
export function AiContent({ children, className, showBadge = true, compact = false }: AiContentProps) {
    const { t } = useTranslations();

    if (compact) {
        return (
            <aside
                aria-label={t('ai.container_label')}
                className={cn(
                    'machine-surface flex items-start gap-2.5 overflow-hidden rounded-[var(--radius-md)] border border-[var(--color-machine-line)] px-3.5 py-2.5',
                    className,
                )}
            >
                <Sparkles
                    aria-hidden="true"
                    strokeWidth={2}
                    className="mt-0.5 size-3.5 shrink-0 text-[var(--color-machine-ink)]"
                />
                <p className="text-sm text-[var(--color-machine-ink)]">{children}</p>
            </aside>
        );
    }

    return (
        <aside
            aria-label={t('ai.container_label')}
            className={cn(
                'machine-surface overflow-hidden rounded-[var(--radius-md)] border border-[var(--color-machine-line)]',
                className,
            )}
        >
            {showBadge ? (
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1 border-b border-[var(--color-machine-line)] bg-surface/60 px-3 py-2">
                    <span className="inline-flex items-center gap-1.5 font-mono text-2xs font-medium tracking-[0.04em] text-[var(--color-machine-ink)] uppercase">
                        <Sparkles aria-hidden="true" strokeWidth={2} className="size-3" />
                        {t('ai.badge')}
                    </span>
                    <p className="text-xs text-[var(--color-machine-ink)]">{t('ai.verify')}</p>
                </div>
            ) : null}

            <div className="px-3 py-3 text-sm text-ink">{children}</div>
        </aside>
    );
}
