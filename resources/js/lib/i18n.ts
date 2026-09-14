import type { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Shared translation layer: Laravel lang JSON files are shared over Inertia
 * as `translations`. Components must not hard-code user-facing copy.
 */
export function useTranslations() {
    const { translations, locale } = usePage<PageProps>().props;

    function t(key: string, replacements: Record<string, string | number> = {}): string {
        let value = translations[key] ?? key;

        // Longest token first: with `:to` and `:total` in the same string,
        // substituting the shorter one first would eat the longer one's name
        // and leave its tail behind ("of 6tal").
        Object.entries(replacements)
            .sort(([a], [b]) => b.length - a.length)
            .forEach(([token, replacement]) => {
                value = value.replaceAll(`:${token}`, String(replacement));
            });

        return value;
    }

    return { t, locale };
}
