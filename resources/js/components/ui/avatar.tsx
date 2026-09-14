import { cn } from '@/lib/utils';
import * as AvatarPrimitive from '@radix-ui/react-avatar';
import * as React from 'react';

function Avatar({ className, ...props }: React.ComponentProps<typeof AvatarPrimitive.Root>) {
    return (
        <AvatarPrimitive.Root
            data-slot="avatar"
            className={cn(
                'relative flex size-8 shrink-0 overflow-hidden rounded-full border border-line',
                className,
            )}
            {...props}
        />
    );
}

function AvatarImage({ className, ...props }: React.ComponentProps<typeof AvatarPrimitive.Image>) {
    return (
        <AvatarPrimitive.Image
            data-slot="avatar-image"
            className={cn('aspect-square size-full object-cover', className)}
            {...props}
        />
    );
}

function AvatarFallback({
    className,
    ...props
}: React.ComponentProps<typeof AvatarPrimitive.Fallback>) {
    return (
        <AvatarPrimitive.Fallback
            data-slot="avatar-fallback"
            className={cn(
                'flex size-full items-center justify-center bg-canvas-sunk text-2xs font-semibold text-ink-muted uppercase',
                className,
            )}
            {...props}
        />
    );
}

export { Avatar, AvatarFallback, AvatarImage };
