import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

/**
 * Every page states what it is once, in its own h1, with its actions on the
 * same line. No eyebrow above the heading: the heading carries its own weight,
 * and the organisation's identity lives permanently in the nav rail.
 */

type PageHeaderProps = {
    title: string;
    description?: string;
    /** Primary and secondary actions, right-aligned on wide viewports. */
    actions?: ReactNode;
    /** State of the record this page is about. */
    status?: ReactNode;
    /** Who did what, when. */
    provenance?: ReactNode;
    /** Identity mark beside the title, such as a profile photo. */
    leading?: ReactNode;
    className?: string;
};

export function PageHeader({ title, description, actions, status, provenance, leading, className }: PageHeaderProps) {
    return (
        <header className={cn('flex flex-wrap items-start justify-between gap-x-6 gap-y-3', className)}>
            <div className="flex min-w-0 max-w-2xl items-start gap-4">
                {leading}
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2.5">
                        <h1 className="text-xl font-semibold text-ink">{title}</h1>
                        {status}
                    </div>
                    {description ? <p className="mt-1.5 text-sm text-ink-muted">{description}</p> : null}
                    {provenance ? <div className="mt-3">{provenance}</div> : null}
                </div>
            </div>

            {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
        </header>
    );
}
