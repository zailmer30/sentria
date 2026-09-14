import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Panel, PanelBody } from '@/components/ui/panel';
import GuestLayout from '@/layouts/GuestLayout';
import { useTranslations } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

type ResetPasswordProps = {
    email: string;
    token: string;
};

export default function ResetPassword({ email, token }: ResetPasswordProps) {
    const { t } = useTranslations();
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/reset-password');
    }

    return (
        <GuestLayout title={t('auth.reset_password')}>
            <Panel raised>
                <PanelBody>
                    <form onSubmit={submit} className="space-y-5">
                        <h2 className="text-xl font-semibold text-ink">{t('auth.reset_password')}</h2>
                        <Field id="email" label={t('auth.email')} required>
                            <Input
                                id="email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                                required
                            />
                        </Field>
                        <Field id="password" label={t('auth.password')} error={form.errors.password} required>
                            <Input
                                id="password"
                                type="password"
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.target.value)}
                                aria-invalid={Boolean(form.errors.password)}
                                required
                            />
                        </Field>
                        <Field id="password_confirmation" label={t('auth.password')} required>
                            <Input
                                id="password_confirmation"
                                type="password"
                                value={form.data.password_confirmation}
                                onChange={(event) => form.setData('password_confirmation', event.target.value)}
                                required
                            />
                        </Field>
                        <Button type="submit" variant="primary" disabled={form.processing}>
                            {t('auth.reset_password')}
                        </Button>
                    </form>
                </PanelBody>
            </Panel>
        </GuestLayout>
    );
}
