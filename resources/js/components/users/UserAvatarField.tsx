import { UserAvatar } from '@/components/users/UserAvatar';
import { Button } from '@/components/ui/button';
import { Field, fieldAria } from '@/components/ui/field';
import { FileDrop } from '@/components/ui/file-drop';
import { useTranslations } from '@/lib/i18n';
import { useEffect, useState } from 'react';

type UserAvatarFieldProps = {
    file: File | null;
    currentUrl?: string | null;
    displayName: string;
    error?: string;
    disabled?: boolean;
    onFileChange: (file: File | null) => void;
    onRemoveCurrent?: () => void;
};

export function UserAvatarField({
    file,
    currentUrl,
    displayName,
    error,
    disabled = false,
    onFileChange,
    onRemoveCurrent,
}: UserAvatarFieldProps) {
    const { t } = useTranslations();
    const [objectUrl, setObjectUrl] = useState<string | null>(null);

    useEffect(() => {
        if (!file) {
            setObjectUrl(null);

            return;
        }

        const url = URL.createObjectURL(file);
        setObjectUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const preview = objectUrl ?? currentUrl ?? null;
    const describedBy = fieldAria('avatar', {
        hint: t('users.avatar_hint'),
        error,
    })['aria-describedby'];

    return (
        <Field
            id="avatar"
            label={t('users.avatar')}
            hint={t('users.avatar_hint')}
            error={error}
            className="sm:col-span-2"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start">
                <UserAvatar
                    name={displayName}
                    src={preview}
                    alt={displayName}
                    className="size-20"
                    fallbackClassName="text-lg"
                />
                <div className="min-w-0 flex-1 space-y-2">
                    <FileDrop
                        id="avatar"
                        file={file}
                        onFileChange={onFileChange}
                        accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                        disabled={disabled}
                        invalid={Boolean(error)}
                        dropLabel={t('users.avatar_drop')}
                        browseLabel={t('users.avatar_browse')}
                        replaceLabel={t('users.avatar_replace')}
                        removeLabel={t('users.avatar_remove')}
                        emptyHint={t('users.avatar_empty_hint')}
                        aria-describedby={describedBy}
                    />
                    {currentUrl && !file && onRemoveCurrent ? (
                        <Button type="button" variant="ghost" size="sm" onClick={onRemoveCurrent} disabled={disabled}>
                            {t('users.remove_photo')}
                        </Button>
                    ) : null}
                </div>
            </div>
        </Field>
    );
}
