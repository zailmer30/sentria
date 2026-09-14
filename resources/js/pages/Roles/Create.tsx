import { Button } from '@/components/ui/button';
import { Field, fieldAria } from '@/components/ui/field';
import { Checkbox, Input, Textarea } from '@/components/ui/input';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelFoot, PanelSection } from '@/components/ui/panel';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type PermissionOption = { name: string; description: string | null };
type PermissionGroup = { module: string; permissions: PermissionOption[] };

type Props = {
    permissionGroups: PermissionGroup[];
};

export default function RolesCreate({ permissionGroups }: Props) {
    const { t } = useTranslations();
    const form = useForm({
        name: '',
        label: '',
        description: '',
        precedence: 100,
        permissions: [] as string[],
    });

    function togglePermission(name: string, checked: boolean) {
        const next = checked
            ? [...form.data.permissions, name]
            : form.data.permissions.filter((item) => item !== name);
        form.setData('permissions', next);
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            description: data.description || null,
        }));

        form.post('/roles');
    }

    return (
        <AppLayout title={t('roles.create')}>
            <div className="mx-auto flex max-w-3xl flex-col gap-5">
                <PageHeader title={t('roles.create')} description={t('roles.create_subtitle')} />

                <Panel>
                    <form onSubmit={submit}>
                        <PanelSection className="space-y-4">
                            <h2 className="text-sm font-semibold text-ink">{t('roles.form_identity')}</h2>

                            <Field
                                id="name"
                                label={t('roles.name')}
                                hint={t('roles.name_hint')}
                                error={form.errors.name}
                                required
                            >
                                <Input
                                    {...fieldAria('name', { hint: t('roles.name_hint'), error: form.errors.name })}
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                    required
                                    className="font-mono"
                                    autoComplete="off"
                                />
                            </Field>

                            <Field id="label" label={t('roles.label')} error={form.errors.label} required>
                                <Input
                                    {...fieldAria('label', { error: form.errors.label })}
                                    value={form.data.label}
                                    onChange={(event) => form.setData('label', event.target.value)}
                                    required
                                />
                            </Field>

                            <Field id="description" label={t('roles.description')} error={form.errors.description}>
                                <Textarea
                                    {...fieldAria('description', { error: form.errors.description })}
                                    value={form.data.description}
                                    onChange={(event) => form.setData('description', event.target.value)}
                                    rows={3}
                                />
                            </Field>

                            <Field
                                id="precedence"
                                label={t('roles.precedence')}
                                hint={t('roles.precedence_hint')}
                                error={form.errors.precedence}
                                required
                            >
                                <Input
                                    {...fieldAria('precedence', {
                                        hint: t('roles.precedence_hint'),
                                        error: form.errors.precedence,
                                    })}
                                    type="number"
                                    min={0}
                                    max={1000}
                                    value={form.data.precedence}
                                    onChange={(event) => form.setData('precedence', Number(event.target.value))}
                                    required
                                />
                            </Field>
                        </PanelSection>

                        <PanelSection className="space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold text-ink">{t('roles.form_permissions')}</h2>
                                <p className="mt-1 text-sm text-ink-muted">{t('roles.permissions_hint')}</p>
                            </div>

                            <div className="space-y-5">
                                {permissionGroups.map((group) => (
                                    <fieldset key={group.module} className="space-y-2">
                                        <legend className="text-xs font-semibold tracking-wide text-ink-subtle uppercase">
                                            {group.module}
                                        </legend>
                                        <div className="space-y-2">
                                            {group.permissions.map((permission) => (
                                                <label
                                                    key={permission.name}
                                                    htmlFor={`perm-${permission.name}`}
                                                    className="flex items-start gap-2.5"
                                                >
                                                    <Checkbox
                                                        id={`perm-${permission.name}`}
                                                        className="mt-0.5"
                                                        checked={form.data.permissions.includes(permission.name)}
                                                        onChange={(event) =>
                                                            togglePermission(permission.name, event.target.checked)
                                                        }
                                                    />
                                                    <span>
                                                        <span className="block font-mono text-xs text-ink">
                                                            {permission.name}
                                                        </span>
                                                        {permission.description ? (
                                                            <span className="block text-xs text-ink-muted">
                                                                {permission.description}
                                                            </span>
                                                        ) : null}
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                    </fieldset>
                                ))}
                            </div>
                            {form.errors.permissions ? (
                                <p className="text-xs text-critical">{form.errors.permissions}</p>
                            ) : null}
                        </PanelSection>

                        <PanelFoot>
                            <Button type="submit" variant="primary" disabled={form.processing}>
                                {form.processing ? t('roles.saving') : t('roles.save')}
                            </Button>
                            <Button variant="ghost" asChild>
                                <Link href="/roles">{t('roles.cancel')}</Link>
                            </Button>
                        </PanelFoot>
                    </form>
                </Panel>
            </div>
        </AppLayout>
    );
}
