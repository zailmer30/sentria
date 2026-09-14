import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Field, fieldAria } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Notice } from '@/components/ui/notice';
import { SimpleSelect } from '@/components/ui/select';
import { useTranslations } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type AppointableUser = {
    id: string;
    display_name: string;
};

type Props = {
    committeeSlug: string;
    users: AppointableUser[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const POSITIONS = ['chair', 'vice-chair', 'member'] as const;

export function AppointMemberDialog({ committeeSlug, users, open, onOpenChange }: Props) {
    const { t } = useTranslations();
    const form = useForm({
        user_id: users[0]?.id ?? '',
        position: 'member',
        appointed_on: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            appointed_on: data.appointed_on || null,
        }));

        form.post(`/committees/${committeeSlug}/members`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent title={t('committees.appoint_title')} description={t('committees.appoint_hint')}>
                {users.length === 0 ? (
                    <>
                        <Notice tone="info">{t('committees.member_empty')}</Notice>
                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                                {t('committees.cancel')}
                            </Button>
                        </DialogFooter>
                    </>
                ) : (
                    <form onSubmit={submit} className="space-y-4">
                        <Field
                            id="user_id"
                            label={t('committees.member')}
                            hint={t('committees.member_hint')}
                            error={form.errors.user_id}
                            required
                        >
                            <SimpleSelect
                                id="user_id"
                                value={form.data.user_id}
                                onValueChange={(value) => form.setData('user_id', value)}
                                aria-invalid={Boolean(form.errors.user_id)}
                                items={users.map((user) => ({
                                    value: user.id,
                                    label: user.display_name,
                                }))}
                            />
                        </Field>

                        <Field
                            id="position"
                            label={t('committees.position')}
                            hint={t('committees.position_hint')}
                            error={form.errors.position}
                            required
                        >
                            <SimpleSelect
                                id="position"
                                value={form.data.position}
                                onValueChange={(value) => form.setData('position', value)}
                                aria-invalid={Boolean(form.errors.position)}
                                items={POSITIONS.map((position) => ({
                                    value: position,
                                    label: t(`committees.position_${position.replaceAll('-', '_')}`),
                                }))}
                            />
                        </Field>

                        <Field
                            id="appointed_on"
                            label={t('committees.appointed_on')}
                            hint={t('committees.appointed_on_field_hint')}
                            error={form.errors.appointed_on}
                        >
                            <Input
                                {...fieldAria('appointed_on', {
                                    hint: t('committees.appointed_on_field_hint'),
                                    error: form.errors.appointed_on,
                                })}
                                type="date"
                                value={form.data.appointed_on}
                                onChange={(event) => form.setData('appointed_on', event.target.value)}
                            />
                        </Field>

                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                                {t('committees.cancel')}
                            </Button>
                            <Button type="submit" variant="primary" disabled={form.processing}>
                                {form.processing ? t('committees.saving') : t('committees.appoint_save')}
                            </Button>
                        </DialogFooter>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
