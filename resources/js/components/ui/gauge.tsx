import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

/**
 * A quantity against a threshold, drawn as an arc: quorum against the number
 * required, minutes finalised against minutes owed.
 *
 * Plain SVG rather than a chart library, because this renders on the SSR pass
 * and on a chamber tablet that may be offline, and because the arc is one
 * `stroke-dasharray` — a dependency would buy nothing.
 *
 * The number in the middle is the answer; the arc only makes it faster to read.
 * Both are always present, so nothing here depends on the drawing.
 */

type GaugeProps = {
    value: number;
    max: number;
    /** Draws the threshold tick, e.g. the seats needed for quorum. */
    threshold?: number;
    /** Centre content. Defaults to the value over its maximum. */
    children?: ReactNode;
    /** Accessible description of what is being measured. */
    label: string;
    tone?: 'accent' | 'success' | 'warning' | 'live';
    size?: number;
    className?: string;
};

const TONE: Record<NonNullable<GaugeProps['tone']>, string> = {
    accent: 'text-accent',
    success: 'text-success',
    warning: 'text-warning',
    live: 'text-live',
};

/** Three-quarter sweep, opening at the bottom so the gap reads as deliberate. */
const SWEEP = 270;
const START = 135;

export function Gauge({
    value,
    max,
    threshold,
    children,
    label,
    tone = 'accent',
    size = 132,
    className,
}: GaugeProps) {
    const safeMax = max > 0 ? max : 1;
    const ratio = Math.min(Math.max(value / safeMax, 0), 1);

    const radius = 46;
    const circumference = 2 * Math.PI * radius;
    const arc = (circumference * SWEEP) / 360;

    const thresholdAngle =
        threshold !== undefined
            ? START + (Math.min(Math.max(threshold / safeMax, 0), 1) * SWEEP)
            : null;

    return (
        <div
            className={cn('relative shrink-0', className)}
            style={{ width: size, height: size }}
            role="img"
            aria-label={`${label}: ${value} of ${max}`}
        >
            <svg viewBox="0 0 120 120" className="size-full -rotate-[135deg]" aria-hidden="true">
                <circle
                    cx="60"
                    cy="60"
                    r={radius}
                    fill="none"
                    strokeWidth="9"
                    strokeLinecap="round"
                    className="stroke-[var(--color-chart-track)]"
                    strokeDasharray={`${arc} ${circumference}`}
                />
                <circle
                    cx="60"
                    cy="60"
                    r={radius}
                    fill="none"
                    strokeWidth="9"
                    strokeLinecap="round"
                    className={cn('stroke-current transition-[stroke-dasharray] duration-[var(--duration-slow)] ease-[var(--ease-out-quint)]', TONE[tone])}
                    strokeDasharray={`${arc * ratio} ${circumference}`}
                />
            </svg>

            {thresholdAngle !== null ? (
                <span
                    aria-hidden="true"
                    className="absolute top-1/2 left-1/2 h-3 w-0.5 origin-[50%_0] rounded-full bg-line-control"
                    style={{
                        transform: `rotate(${thresholdAngle - 180}deg) translateY(${radius * (size / 120) - 6}px)`,
                    }}
                />
            ) : null}

            <div className="absolute inset-0 flex flex-col items-center justify-center gap-0.5 text-center">
                {children ?? (
                    <>
                        <span className="font-mono text-figure-sm font-medium tracking-[-0.02em] text-ink">
                            {value}
                        </span>
                        <span className="font-mono text-2xs text-ink-faint">/ {max}</span>
                    </>
                )}
            </div>
        </div>
    );
}
