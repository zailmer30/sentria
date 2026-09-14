import { Button } from '@/components/ui/button';
import { Field, fieldAria } from '@/components/ui/field';
import { Checkbox, Input } from '@/components/ui/input';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelFoot, PanelSection } from '@/components/ui/panel';
import { SimpleSelect } from '@/components/ui/select';
import { UserAvatarField } from '@/components/users/UserAvatarField';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type RoleOption = { id: string; name: string; label: string };

type UserDetail = {
    id: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    name_suffix: string | null;
    honorific: string | null;
    display_name: string;
    email: string;
    employee_number: string | null;
    position_title: string | null;
    district: string | null;
    phone: string | null;
    locale: string;
    is_active: boolean;
    is_seated_member: boolean;
    avatar_url: string | null;
    role_ids: string[];
};

type Props = {
    user: UserDetail;
    roles: RoleOption[];
    can: { assign: boolean; delete: boolean };
};

export default function UsersEdit({ user, roles, can }: Props) {
    const { t } = useTranslations();
    const form = useForm({
        first_name: user.first_name,
        middle_name: user.middle_name ?? '',
        last_name: user.last_name,
        name_suffix: user.name_suffix ?? '',
        honorific: user.honorific ?? '',
        display_name: user.display_name ?? '',
        email: user.email,
        password: '',
        password_confirmation: '',
        employee_number: user.employee_number ?? '',
        position_title: user.position_title ?? '',
        district: user.district ?? '',
        phone: user.phone ?? '',
        locale: user.locale || 'en',
        is_active: user.is_active,
        is_seated_member: user.is_seated_member,
        avatar: null as File | null,
        remove_avatar: false,
        role_ids: user.role_ids,
    });

    function toggleRole(roleId: string, checked: boolean) {
        const next = checked
            ? [...form.data.role_ids, roleId]
            : form.data.role_ids.filter((id) => id !== roleId);
        form.setData('role_ids', next);
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            middle_name: data.middle_name || null,
            name_suffix: data.name_suffix || null,
            honorific: data.honorific || null,
            display_name: data.display_name || null,
            employee_number: data.employee_number || null,
            position_title: data.position_title || null,
            district: data.district || null,
            phone: data.phone || null,
            password: data.password || null,
            password_confirmation: data.password ? data.password_confirmation : null,
            role_ids: can.assign ? data.role_ids : undefined,
        }));

        form.put(`/users/${user.id}`, { forceFormData: true });
    }

    function destroy() {
        if (!window.confirm(t('users.delete_confirm'))) {
            return;
        }

        router.delete(`/users/${user.id}`);
    }

    return (
        <AppLayout title={t('users.edit')}>
            <div className="mx-auto flex max-w-3xl flex-col gap-5">
                <PageHeader title={t('users.edit')} description={t('users.edit_subtitle')} />

                <Panel>
                    <form onSubmit={submit}>
                        <PanelSection className="space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold text-ink">{t('users.form_identity')}</h2>
                                <p className="mt-1 text-sm text-ink-muted">{t('users.form_identity_hint')}</p>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field id="first_name" label={t('users.first_name')} error={form.errors.first_name} required>
                                    <Input
                                        {...fieldAria('first_name', { error: form.errors.first_name })}
                                        value={form.data.first_name}
                                        onChange={(event) => form.setData('first_name', event.target.value)}
                                        required
                                    />
                                </Field>
                                <Field id="last_name" label={t('users.last_name')} error={form.errors.last_name} required>
                                    <Input
                                        {...fieldAria('last_name', { error: form.errors.last_name })}
                                        value={form.data.last_name}
                                        onChange={(event) => form.setData('last_name', event.target.value)}
                                        required
                                    />
                                </Field>
                                <Field id="middle_name" label={t('users.middle_name')} error={form.errors.middle_name}>
                                    <Input
                                        {...fieldAria('middle_name', { error: form.errors.middle_name })}
                                        value={form.data.middle_name}
                                        onChange={(event) => form.setData('middle_name', event.target.value)}
                                    />
                                </Field>
                                <Field id="name_suffix" label={t('users.name_suffix')} error={form.errors.name_suffix}>
                                    <Input
                                        {...fieldAria('name_suffix', { error: form.errors.name_suffix })}
                                        value={form.data.name_suffix}
                                        onChange={(event) => form.setData('name_suffix', event.target.value)}
                                    />
                                </Field>
                                <Field id="honorific" label={t('users.honorific')} error={form.errors.honorific}>
                                    <Input
                                        {...fieldAria('honorific', { error: form.errors.honorific })}
                                        value={form.data.honorific}
                                        onChange={(event) => form.setData('honorific', event.target.value)}
                                    />
                                </Field>
                                <Field
                                    id="display_name"
                                    label={t('users.display_name')}
                                    hint={t('users.display_name_hint')}
                                    error={form.errors.display_name}
                                >
                                    <Input
                                        {...fieldAria('display_name', {
                                            hint: t('users.display_name_hint'),
                                            error: form.errors.display_name,
                                        })}
                                        value={form.data.display_name}
                                        onChange={(event) => form.setData('display_name', event.target.value)}
                                    />
                                </Field>
                            </div>

                            <UserAvatarField
                                file={form.data.avatar}
                                currentUrl={form.data.remove_avatar ? null : user.avatar_url}
                                displayName={form.data.display_name || user.display_name}
                                error={form.errors.avatar}
                                disabled={form.processing}
                                onFileChange={(file) => {
                                    form.setData((current) => ({
                                        ...current,
                                        avatar: file,
                                        remove_avatar: false,
                                    }));
                                }}
                                onRemoveCurrent={() => {
                                    form.setData((current) => ({
                                        ...current,
                                        avatar: null,
                                        remove_avatar: true,
                                    }));
                                }}
                            />
                        </PanelSection>

                        <PanelSection className="space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold text-ink">{t('users.form_account')}</h2>
                                <p className="mt-1 text-sm text-ink-muted">{t('users.form_account_hint')}</p>
                            </div>

                            <Field id="email" label={t('users.email')} error={form.errors.email} required>
                                <Input
                                    {...fieldAria('email', { error: form.errors.email })}
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) => form.setData('email', event.target.value)}
                                    required
                                />
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="password"
                                    label={t('users.password')}
                                    hint={t('users.password_hint')}
                                    error={form.errors.password}
                                >
                                    <Input
                                        {...fieldAria('password', {
                                            hint: t('users.password_hint'),
                                            error: form.errors.password,
                                        })}
                                        type="password"
                                        value={form.data.password}
                                        onChange={(event) => form.setData('password', event.target.value)}
                                        autoComplete="new-password"
                                    />
                                </Field>
                                <Field
                                    id="password_confirmation"
                                    label={t('users.password_confirmation')}
                                    error={form.errors.password_confirmation}
                                >
                                    <Input
                                        {...fieldAria('password_confirmation', {
                                            error: form.errors.password_confirmation,
                                        })}
                                        type="password"
                                        value={form.data.password_confirmation}
                                        onChange={(event) =>
                                            form.setData('password_confirmation', event.target.value)
                                        }
                                        autoComplete="new-password"
                                    />
                                </Field>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="employee_number"
                                    label={t('users.employee_number')}
                                    error={form.errors.employee_number}
                                >
                                    <Input
                                        {...fieldAria('employee_number', { error: form.errors.employee_number })}
                                        value={form.data.employee_number}
                                        onChange={(event) => form.setData('employee_number', event.target.value)}
                                    />
                                </Field>
                                <Field
                                    id="position_title"
                                    label={t('users.position_title')}
                                    error={form.errors.position_title}
                                >
                                    <Input
                                        {...fieldAria('position_title', { error: form.errors.position_title })}
                                        value={form.data.position_title}
                                        onChange={(event) => form.setData('position_title', event.target.value)}
                                    />
                                </Field>
                                <Field id="district" label={t('users.district')} error={form.errors.district}>
                                    <Input
                                        {...fieldAria('district', { error: form.errors.district })}
                                        value={form.data.district}
                                        onChange={(event) => form.setData('district', event.target.value)}
                                    />
                                </Field>
                                <Field id="phone" label={t('users.phone')} error={form.errors.phone}>
                                    <Input
                                        {...fieldAria('phone', { error: form.errors.phone })}
                                        value={form.data.phone}
                                        onChange={(event) => form.setData('phone', event.target.value)}
                                    />
                                </Field>
                            </div>

                            <Field id="locale" label={t('users.locale')} error={form.errors.locale} required>
                                <SimpleSelect
                                    value={form.data.locale}
                                    onValueChange={(value) => form.setData('locale', value)}
                                    items={[
                                        { value: 'en', label: t('users.locale_en') },
                                        { value: 'fil', label: t('users.locale_fil') },
                                    ]}
                                />
                            </Field>
                        </PanelSection>

                        <PanelSection className="space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold text-ink">{t('users.form_standing')}</h2>
                                <p className="mt-1 text-sm text-ink-muted">{t('users.form_standing_hint')}</p>
                            </div>

                            <label htmlFor="is_active" className="flex items-start gap-2.5">
                                <Checkbox
                                    id="is_active"
                                    className="mt-0.5"
                                    checked={form.data.is_active}
                                    onChange={(event) => form.setData('is_active', event.target.checked)}
                                />
                                <span>
                                    <span className="block text-sm font-medium text-ink">{t('users.is_active')}</span>
                                    <span className="block text-xs text-ink-muted">{t('users.is_active_hint')}</span>
                                </span>
                            </label>

                            <label htmlFor="is_seated_member" className="flex items-start gap-2.5">
                                <Checkbox
                                    id="is_seated_member"
                                    className="mt-0.5"
                                    checked={form.data.is_seated_member}
                                    onChange={(event) => form.setData('is_seated_member', event.target.checked)}
                                />
                                <span>
                                    <span className="block text-sm font-medium text-ink">
                                        {t('users.is_seated_member')}
                                    </span>
                                    <span className="block text-xs text-ink-muted">
                                        {t('users.is_seated_member_hint')}
                                    </span>
                                </span>
                            </label>
                        </PanelSection>

                        {can.assign ? (
                            <PanelSection className="space-y-4">
                                <div>
                                    <h2 className="text-sm font-semibold text-ink">{t('users.form_roles')}</h2>
                                    <p className="mt-1 text-sm text-ink-muted">{t('users.form_roles_hint')}</p>
                                </div>

                                <div className="space-y-2">
                                    {roles.map((role) => (
                                        <label key={role.id} htmlFor={`role-${role.id}`} className="flex items-start gap-2.5">
                                            <Checkbox
                                                id={`role-${role.id}`}
                                                className="mt-0.5"
                                                checked={form.data.role_ids.includes(role.id)}
                                                onChange={(event) => toggleRole(role.id, event.target.checked)}
                                            />
                                            <span className="text-sm text-ink">{role.label}</span>
                                        </label>
                                    ))}
                                </div>
                            </PanelSection>
                        ) : null}

                        <PanelFoot>
                            <Button type="submit" variant="primary" disabled={form.processing}>
                                {form.processing ? t('users.saving') : t('users.save')}
                            </Button>
                            <Button variant="ghost" asChild>
                                <Link href={`/users/${user.id}`}>{t('users.cancel')}</Link>
                            </Button>
                            {can.delete ? (
                                <Button type="button" variant="ghost" className="text-critical" onClick={destroy}>
                                    {t('users.delete')}
                                </Button>
                            ) : null}
                        </PanelFoot>
                    </form>
                </Panel>
            </div>
        </AppLayout>
    );
}
