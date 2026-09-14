import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Field, fieldAria } from '@/components/ui/field';
import { Textarea } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslations } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEvent, useEffect } from 'react';

type UserOption = { id: string; display_name: string };
type RoleOption = { id: string; name: string };
type CommitteeOption = { id: string; name: string };

type Props = {
    documentSlug: string;
    users: UserOption[];
    roles: RoleOption[];
    committees: CommitteeOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const NONE = '__none';

export function GrantAccessDialog({ documentSlug, users, roles, committees, open, onOpenChange }: Props) {
    const { t } = useTranslations();
    const form = useForm({
        grantee_type: 'user' as 'user' | 'role' | 'committee',
        user_id: NONE,
        role_id: NONE,
        committee_id: NONE,
        ability: 'view',
        reason: '',
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({
            grantee_type: 'user',
            user_id: NONE,
            role_id: NONE,
            committee_id: NONE,
            ability: 'view',
            reason: '',
        });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, documentSlug]);

    function submit(event: FormEvent) {
        event.preventDefault();

        const payload = {
            user_id: form.data.grantee_type === 'user' && form.data.user_id !== NONE ? form.data.user_id : null,
            role_id: form.data.grantee_type === 'role' && form.data.role_id !== NONE ? form.data.role_id : null,
            committee_id:
                form.data.grantee_type === 'committee' && form.data.committee_id !== NONE
                    ? form.data.committee_id
                    : null,
            ability: form.data.ability,
            reason: form.data.reason || null,
        };

        if (!payload.user_id && !payload.role_id && !payload.committee_id) {
            form.setError('user_id', t('documents.grant_grantee_required'));

            return;
        }

        form.transform(() => payload);
        form.post(`/documents/${documentSlug}/grants`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent title={t('documents.grant_title')} description={t('documents.grant_hint')}>
                <form onSubmit={submit} className="space-y-4">
                    <Field id="grantee_type" label={t('documents.grant_grantee_type')} required>
                        <Select
                            value={form.data.grantee_type}
                            onValueChange={(value) =>
                                form.setData('grantee_type', value as 'user' | 'role' | 'committee')
                            }
                        >
                            <SelectTrigger id="grantee_type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="user">{t('documents.grant_type_user')}</SelectItem>
                                <SelectItem value="role">{t('documents.grant_type_role')}</SelectItem>
                                <SelectItem value="committee">{t('documents.grant_type_committee')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>

                    {form.data.grantee_type === 'user' ? (
                        <Field id="grant_user" label={t('documents.grant_user')} error={form.errors.user_id} required>
                            <Select value={form.data.user_id} onValueChange={(value) => form.setData('user_id', value)}>
                                <SelectTrigger id="grant_user" aria-invalid={Boolean(form.errors.user_id)}>
                                    <SelectValue placeholder={t('documents.grant_user_placeholder')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {users.map((user) => (
                                        <SelectItem key={user.id} value={user.id}>
                                            {user.display_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    ) : null}

                    {form.data.grantee_type === 'role' ? (
                        <Field id="grant_role" label={t('documents.grant_role')} error={form.errors.role_id} required>
                            <Select value={form.data.role_id} onValueChange={(value) => form.setData('role_id', value)}>
                                <SelectTrigger id="grant_role" aria-invalid={Boolean(form.errors.role_id)}>
                                    <SelectValue placeholder={t('documents.grant_role_placeholder')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((role) => (
                                        <SelectItem key={role.id} value={role.id}>
                                            {role.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    ) : null}

                    {form.data.grantee_type === 'committee' ? (
                        <Field
                            id="grant_committee"
                            label={t('documents.grant_committee')}
                            error={form.errors.committee_id}
                            required
                        >
                            <Select
                                value={form.data.committee_id}
                                onValueChange={(value) => form.setData('committee_id', value)}
                            >
                                <SelectTrigger id="grant_committee" aria-invalid={Boolean(form.errors.committee_id)}>
                                    <SelectValue placeholder={t('documents.grant_committee_placeholder')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {committees.map((committee) => (
                                        <SelectItem key={committee.id} value={committee.id}>
                                            {committee.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    ) : null}

                    <Field id="grant_ability" label={t('documents.grant_ability')} required>
                        <Select value={form.data.ability} onValueChange={(value) => form.setData('ability', value)}>
                            <SelectTrigger id="grant_ability">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="view">{t('documents.grant_ability_view')}</SelectItem>
                                <SelectItem value="download">{t('documents.grant_ability_download')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field id="grant_reason" label={t('documents.grant_reason')} hint={t('documents.grant_reason_hint')}>
                        <Textarea
                            {...fieldAria('grant_reason', { hint: t('documents.grant_reason_hint') })}
                            value={form.data.reason}
                            onChange={(event) => form.setData('reason', event.target.value)}
                            rows={3}
                        />
                    </Field>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            {t('documents.cancel')}
                        </Button>
                        <Button type="submit" variant="primary" disabled={form.processing}>
                            {form.processing ? t('documents.saving') : t('documents.grant_save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
