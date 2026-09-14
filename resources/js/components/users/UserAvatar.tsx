import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { initials } from '@/lib/initials';

type UserAvatarProps = {
    name: string | null | undefined;
    src?: string | null;
    className?: string;
    fallbackClassName?: string;
    alt?: string;
};

export function UserAvatar({ name, src, className, fallbackClassName, alt }: UserAvatarProps) {
    return (
        <Avatar className={className}>
            {src ? <AvatarImage src={src} alt={alt ?? ''} /> : null}
            <AvatarFallback className={fallbackClassName}>{initials(name)}</AvatarFallback>
        </Avatar>
    );
}
