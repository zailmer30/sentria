import { cn } from '@/lib/utils';
import * as React from 'react';
import * as RechartsPrimitive from 'recharts';

/**
 * Charts in this product state quantity, never state. A bar is a count of
 * records, not an alarm; the flag colours keep their single meanings elsewhere
 * and are not spent here.
 *
 * `ChartContainer` renders nothing until it has mounted in a browser. Recharts
 * measures the DOM to lay itself out, so on the SSR pass there is no honest
 * answer — it reserves the height and lets the client fill it. Every chart in
 * the product is therefore decoration on top of a figure that is already
 * legible in text.
 */

export type ChartConfig = Record<
    string,
    {
        label?: React.ReactNode;
        icon?: React.ComponentType;
        color?: string;
    }
>;

type ChartContextValue = { config: ChartConfig };

const ChartContext = React.createContext<ChartContextValue | null>(null);

function useChart(): ChartContextValue {
    const context = React.useContext(ChartContext);

    if (!context) {
        throw new Error('useChart must be used within a <ChartContainer />');
    }

    return context;
}

function ChartContainer({
    id,
    className,
    children,
    config,
    ...props
}: React.ComponentProps<'div'> & {
    config: ChartConfig;
    children: React.ComponentProps<typeof RechartsPrimitive.ResponsiveContainer>['children'];
}) {
    const uniqueId = React.useId();
    const chartId = `chart-${id ?? uniqueId.replace(/:/g, '')}`;
    const [mounted, setMounted] = React.useState(false);

    React.useEffect(() => setMounted(true), []);

    return (
        <ChartContext.Provider value={{ config }}>
            <div
                data-slot="chart"
                data-chart={chartId}
                className={cn(
                    'flex aspect-video justify-center text-xs',
                    '[&_.recharts-cartesian-axis-tick_text]:fill-[var(--color-ink-faint)]',
                    '[&_.recharts-cartesian-grid_line]:stroke-[var(--color-chart-grid)]',
                    '[&_.recharts-curve.recharts-tooltip-cursor]:stroke-[var(--color-line-strong)]',
                    '[&_.recharts-rectangle.recharts-tooltip-cursor]:fill-[var(--color-chart-track)]',
                    '[&_.recharts-radial-bar-background-sector]:fill-[var(--color-chart-track)]',
                    '[&_.recharts-sector]:outline-none [&_.recharts-surface]:outline-none',
                    className,
                )}
                {...props}
            >
                <ChartStyle id={chartId} config={config} />
                {mounted ? (
                    <RechartsPrimitive.ResponsiveContainer>
                        {children}
                    </RechartsPrimitive.ResponsiveContainer>
                ) : null}
            </div>
        </ChartContext.Provider>
    );
}

/**
 * Publishes each series colour as a scoped custom property so series can be
 * referenced as `var(--color-yes)` in the chart markup, keeping raw colours out
 * of the calling component.
 */
function ChartStyle({ id, config }: { id: string; config: ChartConfig }) {
    const colored = Object.entries(config).filter(([, item]) => item.color);

    if (colored.length === 0) {
        return null;
    }

    return (
        <style
            dangerouslySetInnerHTML={{
                __html: `[data-chart=${id}] {\n${colored
                    .map(([key, item]) => `  --color-${key}: ${item.color};`)
                    .join('\n')}\n}`,
            }}
        />
    );
}

const ChartTooltip = RechartsPrimitive.Tooltip;

type TooltipPayloadItem = {
    dataKey?: string | number;
    name?: string | number;
    value?: number | string;
    color?: string;
    payload?: Record<string, unknown>;
};

function ChartTooltipContent({
    active,
    payload,
    label,
    labelFormatter,
    hideLabel = false,
    hideIndicator = false,
    className,
}: {
    active?: boolean;
    payload?: TooltipPayloadItem[];
    label?: React.ReactNode;
    labelFormatter?: (label: React.ReactNode) => React.ReactNode;
    hideLabel?: boolean;
    hideIndicator?: boolean;
    className?: string;
}) {
    const { config } = useChart();

    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div
            className={cn(
                'min-w-36 rounded-[var(--radius-sm)] border border-line bg-surface-raised px-2.5 py-2 text-xs shadow-[var(--shadow-md)]',
                className,
            )}
        >
            {!hideLabel && label !== undefined ? (
                <p className="mb-1.5 font-medium text-ink">
                    {labelFormatter ? labelFormatter(label) : label}
                </p>
            ) : null}
            <ul className="flex flex-col gap-1">
                {payload.map((item, index) => {
                    const key = String(item.dataKey ?? item.name ?? index);
                    const itemConfig = config[key];

                    return (
                        <li key={key} className="flex items-center gap-2">
                            {!hideIndicator ? (
                                <span
                                    aria-hidden="true"
                                    className="size-2 shrink-0 rounded-[2px]"
                                    style={{ backgroundColor: item.color }}
                                />
                            ) : null}
                            <span className="text-ink-muted">{itemConfig?.label ?? key}</span>
                            <span className="ml-auto font-mono font-medium text-ink">
                                {item.value}
                            </span>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

const ChartLegend = RechartsPrimitive.Legend;

function ChartLegendContent({
    payload,
    className,
}: {
    payload?: { value?: string; dataKey?: string | number; color?: string }[];
    className?: string;
}) {
    const { config } = useChart();

    if (!payload?.length) {
        return null;
    }

    return (
        <ul className={cn('flex flex-wrap items-center justify-center gap-x-4 gap-y-1', className)}>
            {payload.map((item, index) => {
                const key = String(item.dataKey ?? item.value ?? index);

                return (
                    <li key={key} className="flex items-center gap-1.5 text-xs text-ink-muted">
                        <span
                            aria-hidden="true"
                            className="size-2 shrink-0 rounded-[2px]"
                            style={{ backgroundColor: item.color }}
                        />
                        {config[key]?.label ?? item.value ?? key}
                    </li>
                );
            })}
        </ul>
    );
}

export {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartStyle,
    ChartTooltip,
    ChartTooltipContent,
};
