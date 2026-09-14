import type { LucideIcon } from 'lucide-react';

export function PortalDetailRow({
    icon: Icon,
    label,
    value,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
}) {
    return (
        <div className="flex gap-3">
            <Icon aria-hidden="true" strokeWidth={1.75} className="mt-0.5 size-4 shrink-0 text-brand" />
            <div className="min-w-0">
                <p className="text-xs text-ink-muted">{label}</p>
                <p className="font-medium text-ink">{value}</p>
            </div>
        </div>
    );
}
