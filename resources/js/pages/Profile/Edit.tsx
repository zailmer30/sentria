import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Definition, DefinitionList } from '@/components/ui/definition-list';
import { Field, fieldAria } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelBody, PanelFoot, PanelHead, PanelSection, PanelTitle } from '@/components/ui/panel';
import { SimpleSelect } from '@/components/ui/select';
import { UserAvatarField } from '@/components/users/UserAvatarField';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type RoleSummary = { id: string; name: string; label: string };

type ProfileUser = {
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
    avatar_url: string | null;
    roles: RoleSummary[];
};

type Props = {
    user: ProfileUser;
};

export default function ProfileEdit({ user }: Props) {
    const { t } = useTranslations();

    const details = useForm({
        first_name: user.first_name,
        middle_name: user.middle_name ?? '',
        last_name: user.last_name,
        name_suffix: user.name_suffix ?? '',
        honorific: user.honorific ?? '',
        display_name: user.display_name ?? '',
        email: user.email,
        phone: user.phone ?? '',
        locale: user.locale || 'en',
        avatar: null as File | null,
        remove_avatar: false,
    });

    const password = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submitDetails(event: FormEvent) {
        event.preventDefault();

        details.transform((data) => ({
            ...data,
            middle_name: data.middle_name || null,
            name_suffix: data.name_suffix || null,
            honorific: data.honorific || null,
            display_name: data.display_name || null,
            phone: data.phone || null,
        }));

        details.put('/profile', { forceFormData: true });
    }

    function submitPassword(event: FormEvent) {
        event.preventDefault();

        password.put('/profile/password', {
            onSuccess: () => password.reset(),
        });
    }

    return (
        <AppLayout title={t('profile.title')}>
            <div className="mx-auto flex max-w-3xl flex-col gap-5">
                <PageHeader title={t('profile.title')} description={t('profile.subtitle')} />

                <Panel>
                    <form onSubmit={submitDetails}>
                        <PanelSection className="space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold text-ink">{t('profile.form_identity')}</h2>
                                <p className="mt-1 text-sm text-ink-muted">{t('profile.form_identity_hint')}</p>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field id="first_name" label={t('users.first_name')} error={details.errors.first_name} required>
                                    <Input
                                        {...fieldAria('first_name', { error: details.errors.first_name })}
                                        value={details.data.first_name}
                                        onChange={(event) => details.setData('first_name', event.target.value)}
                                        required
                                    />
                                </Field>
                                <Field id="last_name" label={t('users.last_name')} error={details.errors.last_name} required>
                                    <Input
                                        {...fieldAria('last_name', { error: details.errors.last_name })}
                                        value={details.data.last_name}
                                        onChange={(event) => details.setData('last_name', event.target.value)}
                                        required
                                    />
                                </Field>
                                <Field id="middle_name" label={t('users.middle_name')} error={details.errors.middle_name}>
                                    <Input
                                        {...fieldAria('middle_name', { error: details.errors.middle_name })}
                                        value={details.data.middle_name}
                                        onChange={(event) => details.setData('middle_name', event.target.value)}
                                    />
                                </Field>
                                <Field id="name_suffix" label={t('users.name_suffix')} error={details.errors.name_suffix}>
                                    <Input
                                        {...fieldAria('name_suffix', { error: details.errors.name_suffix })}
                                        value={details.data.name_suffix}
                                        onChange={(event) => details.setData('name_suffix', event.target.value)}
                                    />
                                </Field>
                                <Field id="honorific" label={t('users.honorific')} error={details.errors.honorific}>
                                    <Input
                                        {...fieldAria('honorific', { error: details.errors.honorific })}
                                        value={details.data.honorific}
                                        onChange={(event) => details.setData('honorific', event.target.value)}
                                    />
                                </Field>
                                <Field
                                    id="display_name"
                                    label={t('users.display_name')}
                                    hint={t('users.display_name_hint')}
                                    error={details.errors.display_name}
                                >
                                    <Input
                                        {...fieldAria('display_name', {
                                            hint: t('users.display_name_hint'),
                                            error: details.errors.display_name,
                                        })}
                                        value={details.data.display_name}
                                        onChange={(event) => details.setData('display_name', event.target.value)}
                                    />
                                </Field>
                            </div>

                            <UserAvatarField
                                file={details.data.avatar}
                                currentUrl={details.data.remove_avatar ? null : user.avatar_url}
                                displayName={details.data.display_name || user.display_name}
                                error={details.errors.avatar}
                                disabled={details.processing}
                                onFileChange={(file) => {
                                    details.setData((current) => ({
                                        ...current,
                                        avatar: file,
                                        remove_avatar: false,
                                    }));
                                }}
                                onRemoveCurrent={() => {
                                    details.setData((current) => ({
                                        ...current,
                                        avatar: null,
                                        remove_avatar: true,
                                    }));
                                }}
                            />
                        </PanelSection>

                        <PanelSection className="space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold text-ink">{t('profile.form_account')}</h2>
                                <p className="mt-1 text-sm text-ink-muted">{t('profile.form_account_hint')}</p>
                            </div>

                            <Field id="email" label={t('users.email')} error={details.errors.email} required>
                                <Input
                                    {...fieldAria('email', { error: details.errors.email })}
                                    type="email"
                                    value={details.data.email}
                                    onChange={(event) => details.setData('email', event.target.value)}
                                    required
                                />
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field id="phone" label={t('users.phone')} error={details.errors.phone}>
                                    <Input
                                        {...fieldAria('phone', { error: details.errors.phone })}
                                        value={details.data.phone}
                                        onChange={(event) => details.setData('phone', event.target.value)}
                                    />
                                </Field>
                                <Field id="locale" label={t('users.locale')} error={details.errors.locale} required>
                                    <SimpleSelect
                                        value={details.data.locale}
                                        onValueChange={(value) => details.setData('locale', value)}
                                        items={[
                                            { value: 'en', label: t('users.locale_en') },
                                            { value: 'fil', label: t('users.locale_fil') },
                                        ]}
                                    />
                                </Field>
                            </div>
                        </PanelSection>

                        <PanelFoot>
                            <Button type="submit" variant="primary" disabled={details.processing}>
                                {details.processing ? t('profile.saving') : t('profile.save')}
                            </Button>
                        </PanelFoot>
                    </form>
                </Panel>

                <Panel>
                    <PanelHead>
                        <PanelTitle>{t('profile.form_standing')}</PanelTitle>
                    </PanelHead>
                    <PanelBody>
                        <p className="mb-4 text-sm text-ink-muted">{t('profile.form_standing_hint')}</p>
                        <DefinitionList>
                            <Definition label={t('users.position_title')}>
                                {user.position_title ?? '—'}
                            </Definition>
                            <Definition label={t('users.district')}>{user.district ?? '—'}</Definition>
                            <Definition label={t('users.employee_number')}>
                                {user.employee_number ?? '—'}
                            </Definition>
                            <Definition label={t('users.roles')}>
                                {user.roles.length === 0 ? (
                                    <span className="text-ink-muted">{t('users.no_roles')}</span>
                                ) : (
                                    <div className="flex flex-wrap gap-2">
                                        {user.roles.map((role) => (
                                            <Badge key={role.id}>{role.label}</Badge>
                                        ))}
                                    </div>
                                )}
                            </Definition>
                        </DefinitionList>
                    </PanelBody>
                </Panel>

                <Panel>
                    <form onSubmit={submitPassword}>
                        <PanelSection className="space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold text-ink">{t('profile.form_password')}</h2>
                                <p className="mt-1 text-sm text-ink-muted">{t('profile.form_password_hint')}</p>
                            </div>

                            <Field
                                id="current_password"
                                label={t('profile.current_password')}
                                error={password.errors.current_password}
                                required
                            >
                                <Input
                                    {...fieldAria('current_password', { error: password.errors.current_password })}
                                    type="password"
                                    value={password.data.current_password}
                                    onChange={(event) => password.setData('current_password', event.target.value)}
                                    autoComplete="current-password"
                                    required
                                />
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="password"
                                    label={t('profile.new_password')}
                                    error={password.errors.password}
                                    required
                                >
                                    <Input
                                        {...fieldAria('password', { error: password.errors.password })}
                                        type="password"
                                        value={password.data.password}
                                        onChange={(event) => password.setData('password', event.target.value)}
                                        autoComplete="new-password"
                                        required
                                    />
                                </Field>
                                <Field
                                    id="password_confirmation"
                                    label={t('users.password_confirmation')}
                                    error={password.errors.password_confirmation}
                                    required
                                >
                                    <Input
                                        {...fieldAria('password_confirmation', {
                                            error: password.errors.password_confirmation,
                                        })}
                                        type="password"
                                        value={password.data.password_confirmation}
                                        onChange={(event) =>
                                            password.setData('password_confirmation', event.target.value)
                                        }
                                        autoComplete="new-password"
                                        required
                                    />
                                </Field>
                            </div>
                        </PanelSection>

                        <PanelFoot>
                            <Button type="submit" variant="primary" disabled={password.processing}>
                                {password.processing ? t('profile.saving_password') : t('profile.save_password')}
                            </Button>
                        </PanelFoot>
                    </form>
                </Panel>
            </div>
        </AppLayout>
    );
}
