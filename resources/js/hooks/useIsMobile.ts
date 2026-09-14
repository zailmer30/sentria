import { useEffect, useState } from 'react';

/** The breakpoint at which the nav rail becomes a sheet. Matches Tailwind `lg`. */
const MOBILE_BREAKPOINT = 1024;

/**
 * Starts `false` on the server and on the first client render so SSR markup and
 * hydration agree; the real measurement lands in the effect immediately after.
 */
export function useIsMobile(): boolean {
    const [isMobile, setIsMobile] = useState(false);

    useEffect(() => {
        const query = window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`);
        const update = () => setIsMobile(query.matches);

        update();
        query.addEventListener('change', update);

        return () => query.removeEventListener('change', update);
    }, []);

    return isMobile;
}
