import { cn } from '@/lib/utils';
import { createContext, useContext, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';

const SessionFloorToolsContext = createContext<HTMLElement | null>(null);

/**
 * Shared bottom-right stack for floor FABs. Chat portals in after assistant,
 * so the message button sits on the bench and Assistant rides above it.
 */
export function SessionFloorToolsProvider({ children }: { children: ReactNode }) {
    const [host, setHost] = useState<HTMLElement | null>(null);

    return (
        <SessionFloorToolsContext.Provider value={host}>
            {children}
            <div ref={setHost} className="pointer-events-none fixed right-5 bottom-5 z-40 flex flex-col items-end gap-3" />
        </SessionFloorToolsContext.Provider>
    );
}

export function SessionFloorFab({ children, className }: { children: ReactNode; className?: string }) {
    const host = useContext(SessionFloorToolsContext);

    if (!host) {
        return null;
    }

    return createPortal(<div className={cn('pointer-events-auto', className)}>{children}</div>, host);
}
