import { useCallback, useEffect, useState } from 'react';

/**
 * The chamber is dimmer than the office, so dark is a real theme rather than a
 * courtesy. The resolved theme is applied by an inline script in the root
 * layout before first paint; this hook only keeps React in step with it.
 */

export type Theme = 'light' | 'dark';

const STORAGE_KEY = 'sentria.theme';

function readStoredTheme(): Theme | null {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);

        return stored === 'dark' || stored === 'light' ? stored : null;
    } catch {
        return null;
    }
}

function systemTheme(): Theme {
    if (typeof window === 'undefined' || !window.matchMedia) {
        return 'light';
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme: Theme): void {
    const root = document.documentElement;
    root.classList.toggle('dark', theme === 'dark');
    root.dataset.theme = theme;
}

function initialTheme(): Theme {
    if (typeof window === 'undefined') {
        return 'light';
    }

    return readStoredTheme() ?? systemTheme();
}

export function useTheme() {
    // Read once on the client so React matches the pre-paint script without a
    // cascading effect. SSR stays on light; hydration corrects from storage.
    const [theme, setTheme] = useState<Theme>(initialTheme);

    useEffect(() => {
        if (readStoredTheme() !== null) {
            return;
        }

        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = (event: MediaQueryListEvent) => {
            const next: Theme = event.matches ? 'dark' : 'light';
            setTheme(next);
            applyTheme(next);
        };

        media.addEventListener('change', onChange);

        return () => media.removeEventListener('change', onChange);
    }, []);

    const toggle = useCallback(() => {
        setTheme((current) => {
            const next: Theme = current === 'dark' ? 'light' : 'dark';

            try {
                localStorage.setItem(STORAGE_KEY, next);
            } catch {
                /* Blocked storage: the choice lasts for this page only. */
            }

            applyTheme(next);

            return next;
        });
    }, []);

    return { theme, toggle };
}
