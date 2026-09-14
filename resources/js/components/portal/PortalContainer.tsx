import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

export function PortalContainer({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('mx-auto w-full max-w-[1400px] px-5 lg:px-10', className)}>{children}</div>;
}
