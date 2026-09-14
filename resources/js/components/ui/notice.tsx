import { cn } from '@/lib/utils';
import { AlertTriangle, Info, Lock, Radio, ShieldAlert } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * The constraints this domain has to state out loud: electronic voting is not
 * automatically binding, this document is restricted, unpublished records are
 * not shown. These are facts about the record, so they are ruled into the page
 * rather than floated over it, and they never dismiss themselves.
 */

type NoticeTone = 'info' | 'caution' | 'danger' | 'restricted' | 'live';

const TONE: Record<NoticeTone, { frame: string; icon: typeof Info }> = {
    info: { frame: 'border-[var(--color-info-line)] bg-info-soft text-info', icon: Info },
    caution: { frame: 'border-[var(--color-warning-line)] bg-warning-soft text-warning', icon: AlertTriangle },
    danger: { frame: 'border-[var(--color-critical-line)] bg-critical-soft text-critical', icon: ShieldAlert },
    restricted: { frame: 'border-[var(--color-warning-line)] bg-warning-soft text-warning', icon: Lock },
    live: { frame: 'border-[var(--color-live-line)] bg-live-soft text-[var(--color-live-ink)]', icon: Radio },
};

type NoticeProps = {
    children: ReactNode;
    tone?: NoticeTone;
    className?: string;
    /** Renders compactly for a floor surface where space is scarce. */
    compact?: boolean;
};

export function Notice({ children, tone = 'info', className, compact = false }: NoticeProps) {
    const { frame, icon: Icon } = TONE[tone];
    const role = tone === 'danger' || tone === 'live' ? 'alert' : 'status';

    return (
        <div
            role={role}
            className={cn(
                'flex items-start gap-2 rounded-[var(--radius-md)] border',
                compact ? 'px-2.5 py-1.5' : 'px-3 py-2.5',
                frame,
                className,
            )}
        >
            <Icon aria-hidden="true" className={cn('mt-px shrink-0', compact ? 'size-3.5' : 'size-4')} strokeWidth={2} />
            <div className={cn('flex-1', compact ? 'text-xs' : 'text-sm')}>{children}</div>
        </div>
    );
}
