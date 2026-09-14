import { Button } from '@/components/ui/button';
import { Pagination, type PaginationLink } from '@/components/ui/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { ReactNode, ThHTMLAttributes } from 'react';

/**
 * The register is this product's main event: a secretariat scanning forty rows
 * for the three that need them. So it stays dense while the rest of the shell
 * relaxes — 36px rows, hairline separation and no vertical rules, because a
 * ruled grid slows a horizontal read down instead of helping it.
 *
 * There is no zebra striping either. The hover tint marks the row you are on,
 * which is the only row highlight that carries information.
 *
 * Every list in the product is built from these parts and in this order:
 * identity on the left, state in the middle, figures and dates on the right,
 * actions last. A reader who has learned one register has learned all of them,
 * so the shape of the page stops being something they have to work out.
 *
 * The column heads stay visible while the rows move. `overflow-x-clip` is what
 * makes that possible from `xl` up: a scroll container would capture the sticky
 * head and pin it to the top of the table instead of the top of the page. Below
 * `xl` the wrapper scrolls horizontally instead, because a column the reader
 * cannot reach is worse than a head that does not follow.
 */

type RegisterProps = {
    children: ReactNode;
    /** Announced to screen readers; the visible heading lives on the page. */
    caption: string;
    className?: string;
    /** Minimum table width before horizontal scroll kicks in. */
    minWidth?: string;
    /** Drop the card chrome when the table already sits inside `RegisterFrame`. */
    flush?: boolean;
};

export function Register({ children, caption, className, minWidth = '52rem', flush = false }: RegisterProps) {
    return (
        <div
            className={cn(
                'overflow-x-auto overflow-y-visible xl:overflow-x-clip',
                flush
                    ? 'rounded-none border-0 shadow-none [&_tbody_tr:last-child>*]:rounded-none'
                    : [
                          'rounded-[var(--radius-lg)] border border-line bg-surface shadow-[var(--shadow-xs)]',
                          // The card can no longer clip its own children to the corner
                          // radius, so the row that meets the corner carries it instead.
                          '[&_tbody_tr:last-child>*:first-child]:rounded-bl-[calc(var(--radius-lg)-1px)]',
                          '[&_tbody_tr:last-child>*:last-child]:rounded-br-[calc(var(--radius-lg)-1px)]',
                      ],
                className,
            )}
        >
            <Table style={{ minWidth }}>
                <caption className="sr-only">{caption}</caption>
                {children}
            </Table>
        </div>
    );
}

/**
 * One card around the finding aid, the rows, and the pager — the documents
 * index shape that every other register follows.
 */
export function RegisterFrame({
    toolbar,
    filters,
    children,
    footer,
    className,
}: {
    toolbar?: ReactNode;
    filters?: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'overflow-hidden rounded-[8px] border border-line bg-surface shadow-[0_1px_2px_rgb(15_27_61/0.06)]',
                className,
            )}
        >
            {toolbar ? (
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
                    {toolbar}
                </div>
            ) : null}
            {filters}
            {children}
            {footer}
        </div>
    );
}

/**
 * The head sits on the sunk tone and carries its own bottom rule as an inset
 * shadow: a collapsed border would be painted by the cell underneath it and
 * disappear the moment the head starts overlapping rows.
 */
export function RegisterHead({ children }: { children: ReactNode }) {
    return (
        <TableHeader className="sticky top-0 z-20 [&_tr]:border-0">
            <TableRow
                className={cn(
                    'bg-surface-alt shadow-[inset_0_-1px_0_var(--color-line-strong)] hover:bg-surface-alt',
                    '[&>th:first-child]:rounded-tl-[calc(var(--radius-lg)-1px)]',
                    '[&>th:last-child]:rounded-tr-[calc(var(--radius-lg)-1px)]',
                )}
            >
                {children}
            </TableRow>
        </TableHeader>
    );
}

type RegisterHeadCellProps = ThHTMLAttributes<HTMLTableCellElement> & {
    children?: ReactNode;
    align?: 'left' | 'right' | 'center';
    /** Narrow fixed column, e.g. the confidentiality dot. */
    tight?: boolean;
};

export function RegisterHeadCell({
    children,
    align = 'left',
    tight = false,
    className,
    ...props
}: RegisterHeadCellProps) {
    return (
        <TableHead
            scope="col"
            className={cn(
                'bg-surface-alt whitespace-nowrap',
                align === 'right' && 'text-right',
                align === 'center' && 'text-center',
                tight && 'w-9 px-2',
                className,
            )}
            {...props}
        >
            {children}
        </TableHead>
    );
}

export function RegisterBody({ children }: { children: ReactNode }) {
    return <TableBody>{children}</TableBody>;
}

type RegisterRowProps = {
    children: ReactNode;
    className?: string;
    /** Marks the row currently under deliberation. */
    live?: boolean;
    onClick?: () => void;
};

export function RegisterRow({ children, className, live = false, onClick }: RegisterRowProps) {
    return (
        <TableRow
            className={cn(live && 'bg-live-soft hover:bg-live-soft', onClick && 'cursor-pointer', className)}
            onClick={onClick}
        >
            {children}
        </TableRow>
    );
}

type RegisterCellProps = {
    children: ReactNode;
    className?: string;
    align?: 'left' | 'right' | 'center';
    tight?: boolean;
    /** Mono tabular figures, for counts, sequences and reference numbers. */
    numeric?: boolean;
    /** Keeps a short value on one line: dates, codes, counts, chips. */
    nowrap?: boolean;
};

export function RegisterCell({
    children,
    className,
    align = 'left',
    tight = false,
    numeric = false,
    nowrap = false,
}: RegisterCellProps) {
    return (
        <TableCell
            className={cn(
                'text-sm',
                align === 'right' && 'text-right',
                align === 'center' && 'text-center',
                tight && 'w-9 px-2',
                numeric && 'font-mono text-xs text-ink',
                nowrap && 'whitespace-nowrap',
                className,
            )}
        >
            {children}
        </TableCell>
    );
}

type RegisterCellPrimaryProps = {
    children: ReactNode;
    className?: string;
    /** Turns the value into the row's opening link. */
    href?: string;
    /**
     * The qualifier under the title: a reference number, a committee, a date.
     * Only pass a value every row in the register will have — a line that
     * appears on some rows and not others makes the row height ragged and
     * breaks the vertical rhythm a reader scans down.
     */
    secondary?: ReactNode;
};

/**
 * The cell that carries the record's identity: inked, heavier, never muted, and
 * always the row's link when the record can be opened. Keeping the link in one
 * predictable column means a reader running down a list of forty knows where to
 * aim without reading first.
 */
export function RegisterCellPrimary({
    children,
    className,
    href,
    secondary,
}: RegisterCellPrimaryProps) {
    const title = href ? (
        <Link href={href} className="rounded-[var(--radius-xs)]">
            <RegisterLink>{children}</RegisterLink>
        </Link>
    ) : (
        children
    );

    return (
        <TableCell className={cn('text-sm font-medium text-ink', className)}>
            {title}
            {secondary ? (
                <span className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-normal text-ink-subtle">
                    {secondary}
                </span>
            ) : null}
        </TableCell>
    );
}

/**
 * A moment in the record, rendered in the reader's locale and zone but written
 * to the DOM in ISO so it stays machine-readable. Mono and tabular so the
 * column aligns on the digits.
 */
export function RegisterCellDate({
    value,
    withTime = false,
    align = 'left',
    fallback,
}: {
    value: string | null | undefined;
    /** Include the clock. Sessions need it; a series year does not. */
    withTime?: boolean;
    align?: 'left' | 'right';
    /** Shown instead of the em dash when absence has a specific meaning. */
    fallback?: string;
}) {
    const { formatDate, formatDateTime, toDateTimeAttribute } = useFormatters();
    const machine = toDateTimeAttribute(value);
    const formatted = withTime ? formatDateTime(value) : formatDate(value);

    return (
        <RegisterCell numeric nowrap align={align}>
            {machine ? (
                <time dateTime={machine}>{formatted}</time>
            ) : (
                <span className="text-ink-subtle">{fallback ?? formatted}</span>
            )}
        </RegisterCell>
    );
}

/**
 * The last column: what you can do to this row. Right-aligned and held on one
 * line so the controls stack into a single vertical edge down the register.
 */
export function RegisterCellActions({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <TableCell className={cn('w-px py-1.5 text-right whitespace-nowrap', className)}>
            <div className="flex items-center justify-end gap-1">{children}</div>
        </TableCell>
    );
}

export function RegisterEmpty({ colSpan, children }: { colSpan: number; children: ReactNode }) {
    return (
        <tr>
            <td colSpan={colSpan} className="px-3 py-14 text-center">
                {children}
            </td>
        </tr>
    );
}

/**
 * The link that opens a record from its register row. Underlined only on hover,
 * because forty permanently underlined titles is noise, but the whole row is
 * not a link target — a row click would fight text selection and middle-click.
 */
export function RegisterLink({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <span
            className={cn(
                'text-ink underline-offset-2 hover:text-accent hover:underline',
                className,
            )}
        >
            {children}
        </span>
    );
}

/** Trailing "Open" control used on register rows. */
export function RegisterOpenLink({ href, children }: { href: string; children: ReactNode }) {
    return (
        <Button variant="ghost" size="sm" asChild>
            <Link href={href} className="gap-0.5 text-ink-muted">
                {children}
                <ChevronRight aria-hidden="true" strokeWidth={1.75} className="size-3.5" />
            </Link>
        </Button>
    );
}

type RegisterFooterProps = {
    /** Laravel's paginator: `from`, `to` and `total` describe the whole set. */
    from?: number | null;
    to?: number | null;
    total?: number | null;
    links?: PaginationLink[];
    /** Accessible name for the pagination landmark, e.g. the page title. */
    label: string;
    className?: string;
    /** Sit inside `RegisterFrame` with previous/next controls. */
    inset?: boolean;
};

function pageButtonClass(disabled: boolean): string {
    return cn(
        'inline-flex h-8 items-center rounded-[8px] border px-3 text-xs font-medium',
        disabled
            ? 'cursor-default border-line bg-canvas-sunk text-ink-faint'
            : 'border-line bg-surface text-ink-muted hover:border-line-strong hover:text-ink',
    );
}

/**
 * What the reader is looking at, and how to reach the rest of it.
 *
 * The count is the honest half: a register shows twenty rows of a set that may
 * hold four hundred, and without saying so the page quietly implies the twenty
 * are all there is. Inside a framed register it shares the card with the rows;
 * elsewhere it sits just below them.
 */
export function RegisterFooter({
    from,
    to,
    total,
    links,
    label,
    className,
    inset = false,
}: RegisterFooterProps) {
    const { t } = useTranslations();
    const { formatNumber } = useFormatters();
    const hasCount = typeof total === 'number';
    const prevLink = links?.[0];
    const nextLink = links && links.length > 0 ? links[links.length - 1] : undefined;
    const hasCompactPager = inset && Boolean(links && links.length > 0);
    const hasNumberedPager = !inset && (links?.length ?? 0) > 3;

    if (!hasCount && !hasCompactPager && !hasNumberedPager) {
        return null;
    }

    return (
        <div
            className={cn(
                'flex flex-wrap items-center justify-between gap-3',
                inset && 'border-t border-line px-5 py-3',
                !inset && 'gap-x-4 gap-y-3',
                className,
            )}
        >
            {hasCount ? (
                <p aria-live="polite" className="text-xs text-ink-subtle">
                    {total === 0
                        ? t('register.showing_none')
                        : t('register.showing', {
                              from: formatNumber(from ?? 0),
                              to: formatNumber(to ?? 0),
                              total: formatNumber(total),
                          })}
                </p>
            ) : (
                <span />
            )}

            {hasCompactPager ? (
                <nav aria-label={label} className="flex items-center gap-2">
                    {prevLink?.url ? (
                        <Link href={prevLink.url} preserveScroll className={pageButtonClass(false)}>
                            {t('register.previous')}
                        </Link>
                    ) : (
                        <span className={pageButtonClass(true)}>{t('register.previous')}</span>
                    )}
                    {nextLink?.url ? (
                        <Link href={nextLink.url} preserveScroll className={pageButtonClass(false)}>
                            {t('register.next')}
                        </Link>
                    ) : (
                        <span className={pageButtonClass(true)}>{t('register.next')}</span>
                    )}
                </nav>
            ) : hasNumberedPager && links ? (
                <Pagination links={links} label={label} />
            ) : null}
        </div>
    );
}

/** The paginator shape every index page receives from Laravel. */
export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};
