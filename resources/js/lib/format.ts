import { useTranslations } from '@/lib/i18n';
import { useMemo } from 'react';

/**
 * One place that turns a stored value into something a reader sees.
 *
 * Dates arrive from the server as ISO 8601 in UTC and are rendered in the
 * reader's own zone, in their own locale. Registers previously each called
 * `toLocaleString()` with no locale and no options, which produced a different
 * format on every page and a different one again per machine — unusable in a
 * column you are meant to scan down.
 *
 * Everything here fails soft: an unparseable or absent value returns the em
 * dash rather than "Invalid Date", because a blank in the record is a fact and
 * should look like one.
 */

/** What a cell shows when the record holds no value. */
export const EMPTY_VALUE = '—';

const LOCALES: Record<string, string> = {
    en: 'en-PH',
    fil: 'fil-PH',
};

function toDate(value: string | null | undefined): Date | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

export function useFormatters() {
    const { locale } = useTranslations();
    const tag = LOCALES[locale] ?? locale ?? 'en-PH';

    return useMemo(() => {
        const date = new Intl.DateTimeFormat(tag, {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
        });

        const dateLong = new Intl.DateTimeFormat(tag, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });

        const dateTime = new Intl.DateTimeFormat(tag, {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });

        const time = new Intl.DateTimeFormat(tag, {
            hour: '2-digit',
            minute: '2-digit',
        });

        const number = new Intl.NumberFormat(tag);

        return {
            /** 04 Aug 2026 */
            formatDate: (value: string | null | undefined): string => {
                const parsed = toDate(value);

                return parsed ? date.format(parsed) : EMPTY_VALUE;
            },
            /** August 4, 2026 */
            formatDateLong: (value: string | null | undefined): string => {
                const parsed = toDate(value);

                return parsed ? dateLong.format(parsed) : EMPTY_VALUE;
            },
            /** 04 Aug 2026, 09:00 */
            formatDateTime: (value: string | null | undefined): string => {
                const parsed = toDate(value);

                return parsed ? dateTime.format(parsed) : EMPTY_VALUE;
            },
            /** 09:00 */
            formatTime: (value: string | null | undefined): string => {
                const parsed = toDate(value);

                return parsed ? time.format(parsed) : EMPTY_VALUE;
            },
            formatNumber: (value: number | null | undefined): string =>
                typeof value === 'number' ? number.format(value) : EMPTY_VALUE,
            /** 12 minutes ago — falls back to a short date past a week. */
            formatRelative: (value: string | null | undefined): string => {
                const parsed = toDate(value);

                if (!parsed) {
                    return EMPTY_VALUE;
                }

                const diffSeconds = Math.round((parsed.getTime() - Date.now()) / 1000);
                const abs = Math.abs(diffSeconds);
                const relative = new Intl.RelativeTimeFormat(tag, { numeric: 'auto' });

                if (abs < 60) {
                    return relative.format(diffSeconds, 'second');
                }

                if (abs < 3600) {
                    return relative.format(Math.round(diffSeconds / 60), 'minute');
                }

                if (abs < 86_400) {
                    return relative.format(Math.round(diffSeconds / 3600), 'hour');
                }

                if (abs < 86_400 * 7) {
                    return relative.format(Math.round(diffSeconds / 86_400), 'day');
                }

                return date.format(parsed);
            },
            /** The machine-readable half of a `<time>` element. */
            toDateTimeAttribute: (value: string | null | undefined): string | undefined =>
                toDate(value) ? (value ?? undefined) : undefined,
        };
    }, [tag]);
}
