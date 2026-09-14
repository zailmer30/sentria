import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Notice } from '@/components/ui/notice';
import { Panel, PanelBody } from '@/components/ui/panel';
import GuestLayout from '@/layouts/GuestLayout';
import { useTranslations } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { t } = useTranslations();
    const form = useForm({ email: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/forgot-password');
    }

    return (
        <GuestLayout title={t('auth.reset_password')}>
            <Panel raised>
                <PanelBody>
                    <form onSubmit={submit} className="space-y-5">
                        <h2 className="text-xl font-semibold text-ink">{t('auth.reset_password')}</h2>
                        {status ? <Notice tone="info">{status}</Notice> : null}
                        <Field id="email" label={t('auth.email')} error={form.errors.email} required>
                            <Input
                                id="email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                                aria-invalid={Boolean(form.errors.email)}
                                required
                            />
                        </Field>
                        <Button type="submit" variant="primary" disabled={form.processing}>
                            {t('auth.send_reset_link')}
                        </Button>
                    </form>
                </PanelBody>
            </Panel>
        </GuestLayout>
    );
}
