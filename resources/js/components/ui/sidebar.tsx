import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useIsMobile } from '@/hooks/useIsMobile';
import { cn } from '@/lib/utils';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import { PanelLeft } from 'lucide-react';
import * as React from 'react';

/**
 * The nav rail. Two shapes, one component: on desktop it collapses to a strip
 * of icons and back, and below the large breakpoint it becomes a sheet, so the
 * focus trap and scroll lock come from Radix rather than from a hand-rolled
 * drawer.
 *
 * The collapsed/expanded choice is a workspace preference, not a page state, so
 * it persists in a cookie and survives navigation.
 */

const SIDEBAR_COOKIE_NAME = 'sentria.sidebar';
const SIDEBAR_COOKIE_MAX_AGE = 60 * 60 * 24 * 365;
const SIDEBAR_WIDTH = '18rem';
const SIDEBAR_WIDTH_MOBILE = '18.5rem';
const SIDEBAR_WIDTH_ICON = '4rem';
const SIDEBAR_KEYBOARD_SHORTCUT = 'b';

type SidebarContextValue = {
    state: 'expanded' | 'collapsed';
    open: boolean;
    setOpen: (open: boolean | ((current: boolean) => boolean)) => void;
    openMobile: boolean;
    setOpenMobile: (open: boolean) => void;
    isMobile: boolean;
    toggleSidebar: () => void;
};

const SidebarContext = React.createContext<SidebarContextValue | null>(null);

export function useSidebar(): SidebarContextValue {
    const context = React.useContext(SidebarContext);

    if (!context) {
        throw new Error('useSidebar must be used within a <SidebarProvider />');
    }

    return context;
}

function SidebarProvider({
    defaultOpen = true,
    open: openProp,
    onOpenChange: setOpenProp,
    className,
    style,
    children,
    ...props
}: React.ComponentProps<'div'> & {
    defaultOpen?: boolean;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    const isMobile = useIsMobile();
    const [openMobile, setOpenMobile] = React.useState(false);
    const [internalOpen, setInternalOpen] = React.useState(defaultOpen);
    const open = openProp ?? internalOpen;

    const setOpen = React.useCallback(
        (value: boolean | ((current: boolean) => boolean)) => {
            const resolve = (current: boolean) => (typeof value === 'function' ? value(current) : value);

            if (setOpenProp) {
                const next = resolve(openProp ?? internalOpen);
                setOpenProp(next);
                document.cookie = `${SIDEBAR_COOKIE_NAME}=${next}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}; SameSite=Lax`;

                return;
            }

            setInternalOpen((current) => {
                const next = resolve(current);
                document.cookie = `${SIDEBAR_COOKIE_NAME}=${next}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}; SameSite=Lax`;

                return next;
            });
        },
        [internalOpen, openProp, setOpenProp],
    );

    const toggleSidebar = React.useCallback(() => {
        if (isMobile) {
            setOpenMobile((current) => !current);

            return;
        }

        setOpen((current) => !current);
    }, [isMobile, setOpen]);

    React.useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (event.key === SIDEBAR_KEYBOARD_SHORTCUT && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                toggleSidebar();
            }
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [toggleSidebar]);

    const value = React.useMemo<SidebarContextValue>(
        () => ({
            state: open ? 'expanded' : 'collapsed',
            open,
            setOpen,
            openMobile,
            setOpenMobile,
            isMobile,
            toggleSidebar,
        }),
        [open, setOpen, openMobile, isMobile, toggleSidebar],
    );

    return (
        <SidebarContext.Provider value={value}>
            <TooltipProvider>
                <div
                    data-slot="sidebar-wrapper"
                    style={
                        {
                            '--sidebar-width': SIDEBAR_WIDTH,
                            '--sidebar-width-icon': SIDEBAR_WIDTH_ICON,
                            ...style,
                        } as React.CSSProperties
                    }
                    className={cn('flex min-h-svh w-full', className)}
                    {...props}
                >
                    {children}
                </div>
            </TooltipProvider>
        </SidebarContext.Provider>
    );
}

function Sidebar({
    collapsible = 'icon',
    className,
    children,
    mobileTitle = 'Navigation',
    ...props
}: React.ComponentProps<'div'> & {
    collapsible?: 'icon' | 'offcanvas' | 'none';
    mobileTitle?: string;
}) {
    const { isMobile, state, openMobile, setOpenMobile } = useSidebar();

    if (collapsible === 'none') {
        return (
            <div
                data-slot="sidebar"
                className={cn('flex h-full w-(--sidebar-width) flex-col bg-sidebar', className)}
                {...props}
            >
                {children}
            </div>
        );
    }

    if (isMobile) {
        return (
            <Sheet open={openMobile} onOpenChange={setOpenMobile}>
                <SheetContent
                    side="left"
                    data-slot="sidebar"
                    data-mobile="true"
                    className="w-(--sidebar-width-mobile) bg-sidebar p-0"
                    style={{ '--sidebar-width-mobile': SIDEBAR_WIDTH_MOBILE } as React.CSSProperties}
                    showClose={false}
                >
                    <SheetTitle className="sr-only">{mobileTitle}</SheetTitle>
                    <SheetDescription className="sr-only">{mobileTitle}</SheetDescription>
                    <div className="flex h-full w-full flex-col">{children}</div>
                </SheetContent>
            </Sheet>
        );
    }

    const collapsedToIcons = state === 'collapsed' && collapsible === 'icon';
    const collapsedOffcanvas = state === 'collapsed' && collapsible === 'offcanvas';

    return (
        <div
            className="group peer hidden text-sidebar-foreground lg:block"
            data-state={state}
            data-collapsible={state === 'collapsed' ? collapsible : undefined}
            data-slot="sidebar"
        >
            {/* Holds the gap in the layout so the content column reflows rather than sliding under the rail. */}
            <div
                className={cn(
                    'relative bg-transparent transition-[width] duration-[var(--duration-base)] ease-[var(--ease-standard)]',
                    collapsedOffcanvas ? 'w-0' : collapsedToIcons ? 'w-(--sidebar-width-icon)' : 'w-(--sidebar-width)',
                )}
            />
            <div
                className={cn(
                    'absolute inset-y-0 left-0 z-10 hidden h-full transition-[left,width] duration-[var(--duration-base)] ease-[var(--ease-standard)] lg:flex',
                    collapsedOffcanvas ? 'left-[calc(var(--sidebar-width)*-1)] w-(--sidebar-width)' : collapsedToIcons ? 'w-(--sidebar-width-icon)' : 'w-(--sidebar-width)',
                    className,
                )}
                {...props}
            >
                <div
                    data-sidebar="sidebar"
                    className="flex h-full w-full flex-col overflow-hidden bg-sidebar"
                >
                    {children}
                </div>
            </div>
        </div>
    );
}

function SidebarTrigger({
    className,
    onClick,
    children,
    ...props
}: React.ComponentProps<'button'>) {
    const { toggleSidebar } = useSidebar();

    return (
        <button
            type="button"
            data-slot="sidebar-trigger"
            data-sidebar="trigger"
            onClick={(event) => {
                onClick?.(event);
                toggleSidebar();
            }}
            className={cn(
                'inline-flex size-9 shrink-0 items-center justify-center rounded-[var(--radius-sm)] text-ink-muted',
                'transition-colors duration-[var(--duration-fast)] hover:bg-canvas-sunk hover:text-ink',
                className,
            )}
            {...props}
        >
            {children ?? <PanelLeft aria-hidden="true" strokeWidth={1.75} className="size-4" />}
        </button>
    );
}

function SidebarHeader({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="sidebar-header"
            data-sidebar="header"
            className={cn(
                'flex min-h-16 flex-col justify-center gap-2 px-3.5 py-3',
                'group-data-[collapsible=icon]:min-h-0 group-data-[collapsible=icon]:px-2 group-data-[collapsible=icon]:py-3',
                className,
            )}
            {...props}
        />
    );
}

function SidebarContent({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="sidebar-content"
            data-sidebar="content"
            className={cn(
                'flex min-h-0 flex-1 flex-col gap-4 overflow-x-hidden overflow-y-auto px-2 py-1',
                'group-data-[collapsible=icon]:overflow-hidden',
                className,
            )}
            {...props}
        />
    );
}

function SidebarFooter({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="sidebar-footer"
            data-sidebar="footer"
            className={cn('flex flex-col gap-2 p-3', className)}
            {...props}
        />
    );
}

function SidebarGroup({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="sidebar-group"
            data-sidebar="group"
            className={cn('relative flex w-full min-w-0 flex-col', className)}
            {...props}
        />
    );
}

function SidebarGroupLabel({
    className,
    asChild = false,
    ...props
}: React.ComponentProps<'div'> & { asChild?: boolean }) {
    const Comp = asChild ? Slot : 'div';

    return (
        <Comp
            data-slot="sidebar-group-label"
            data-sidebar="group-label"
            className={cn(
                'label-eyebrow flex h-7 shrink-0 items-center px-2',
                'transition-[margin,opacity] duration-[var(--duration-base)] ease-[var(--ease-standard)]',
                'group-data-[collapsible=icon]:pointer-events-none group-data-[collapsible=icon]:-mt-7 group-data-[collapsible=icon]:opacity-0',
                className,
            )}
            {...props}
        />
    );
}

function SidebarMenu({ className, ...props }: React.ComponentProps<'ul'>) {
    return (
        <ul
            data-slot="sidebar-menu"
            data-sidebar="menu"
            className={cn('flex w-full min-w-0 flex-col gap-0.5', className)}
            {...props}
        />
    );
}

function SidebarMenuItem({ className, ...props }: React.ComponentProps<'li'>) {
    return (
        <li
            data-slot="sidebar-menu-item"
            data-sidebar="menu-item"
            className={cn('group/menu-item relative', className)}
            {...props}
        />
    );
}

function SidebarMenuSub({ className, ...props }: React.ComponentProps<'ul'>) {
    return (
        <ul
            data-slot="sidebar-menu-sub"
            data-sidebar="menu-sub"
            className={cn(
                'mx-3.5 flex min-w-0 translate-x-px flex-col gap-0.5 border-l border-line px-2.5 py-1',
                'group-data-[collapsible=icon]:hidden',
                className,
            )}
            {...props}
        />
    );
}

function SidebarMenuSubItem({ className, ...props }: React.ComponentProps<'li'>) {
    return (
        <li
            data-slot="sidebar-menu-sub-item"
            data-sidebar="menu-sub-item"
            className={cn('relative', className)}
            {...props}
        />
    );
}

function SidebarMenuSubButton({
    asChild = false,
    isActive = false,
    className,
    ...props
}: React.ComponentProps<'a'> & { asChild?: boolean; isActive?: boolean }) {
    const Comp = asChild ? Slot : 'a';

    return (
        <Comp
            data-slot="sidebar-menu-sub-button"
            data-sidebar="menu-sub-button"
            data-active={isActive}
            className={cn(
                'flex h-8 min-w-0 items-center overflow-hidden rounded-[var(--radius-sm)] px-2 text-sm text-ink-muted outline-none',
                'transition-colors duration-[var(--duration-fast)]',
                'hover:bg-canvas-sunk hover:text-ink',
                'data-[active=true]:bg-accent-soft data-[active=true]:font-medium data-[active=true]:text-accent',
                className,
            )}
            {...props}
        />
    );
}

const sidebarMenuButtonVariants = cva(
    [
        'peer/menu-button group/menu-button flex w-full items-center gap-2.5 overflow-hidden rounded-[var(--radius-md)] px-2 text-left text-sm outline-none',
        'transition-colors duration-[var(--duration-fast)]',
        'disabled:pointer-events-none disabled:opacity-45',
        '[&>svg]:size-4 [&>svg]:shrink-0',
        '[&>span:last-child]:truncate',
        'group-data-[collapsible=icon]:size-9 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:p-0 group-data-[collapsible=icon]:[&>span]:hidden',
    ],
    {
        variants: {
            variant: {
                default:
                    'text-ink-muted hover:bg-canvas-sunk hover:text-ink data-[active=true]:bg-accent-soft data-[active=true]:font-medium data-[active=true]:text-accent',
            },
            size: {
                default: 'h-9',
                lg: 'h-11 text-md',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

function SidebarMenuButton({
    asChild = false,
    isActive = false,
    variant,
    size,
    tooltip,
    className,
    ...props
}: React.ComponentProps<'button'> &
    VariantProps<typeof sidebarMenuButtonVariants> & {
        asChild?: boolean;
        isActive?: boolean;
        tooltip?: string;
    }) {
    const Comp = asChild ? Slot : 'button';
    const { isMobile, state } = useSidebar();

    const button = (
        <Comp
            data-slot="sidebar-menu-button"
            data-sidebar="menu-button"
            data-active={isActive}
            className={cn(
                sidebarMenuButtonVariants({ variant, size }),
                state === 'collapsed' && !isMobile && 'size-9 justify-center p-0 [&>span]:hidden',
                className,
            )}
            {...props}
        />
    );

    if (!tooltip || state !== 'collapsed' || isMobile) {
        return button;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>{button}</TooltipTrigger>
            <TooltipContent side="right" align="center">
                {tooltip}
            </TooltipContent>
        </Tooltip>
    );
}

function SidebarSeparator({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="sidebar-separator"
            data-sidebar="separator"
            className={cn('mx-2 h-px shrink-0 bg-line', className)}
            {...props}
        />
    );
}

/** The content column beside the rail. */
function SidebarInset({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="sidebar-inset"
            className={cn('relative flex min-h-svh min-w-0 flex-1 flex-col', className)}
            {...props}
        />
    );
}

export {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarProvider,
    SidebarSeparator,
    SidebarTrigger,
};
