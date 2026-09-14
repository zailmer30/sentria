import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Field } from '@/components/ui/field';
import { Textarea } from '@/components/ui/input';
import { Notice } from '@/components/ui/notice';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelFoot, PanelSection } from '@/components/ui/panel';
import { SimpleSelect } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import { FormEvent } from 'react';

type SessionOption = { id: string; session_number: string; title: string };

type Props = {
    sessions: SessionOption[];
};

export default function MinutesCreate({ sessions }: Props) {
    const { t } = useTranslations();
    const form = useForm({ session_id: sessions[0]?.id ?? '', content: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/minutes');
    }

    return (
        <AppLayout title={t('minutes.create')}>
            <div className="mx-auto flex max-w-3xl flex-col gap-5">
                <PageHeader title={t('minutes.create')} description={t('minutes.create_subtitle')} />

                {sessions.length === 0 ? (
                    <EmptyState
                        icon={ScrollText}
                        title={t('minutes.no_sessions')}
                        description={t('minutes.no_sessions_hint')}
                        action={
                            <Button variant="secondary" asChild>
                                <Link href="/minutes">{t('minutes.cancel')}</Link>
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <Notice tone="info">{t('minutes.create_notice')}</Notice>

                        <Panel>
                            <form onSubmit={submit}>
                                <PanelSection className="space-y-4">
                                    <h2 className="text-sm font-semibold text-ink">{t('minutes.form_sitting')}</h2>
                                    <Field
                                        id="session_id"
                                        label={t('minutes.session')}
                                        hint={t('minutes.session_hint')}
                                        error={form.errors.session_id}
                                        required
                                    >
                                        <SimpleSelect
                                            id="session_id"
                                            value={form.data.session_id}
                                            onValueChange={(value) => form.setData('session_id', value)}
                                            aria-invalid={Boolean(form.errors.session_id)}
                                            items={sessions.map((session) => ({
                                                value: session.id,
                                                label: `${session.session_number} — ${session.title}`,
                                            }))}
                                        />
                                    </Field>
                                </PanelSection>

                                <PanelSection className="space-y-4">
                                    <h2 className="text-sm font-semibold text-ink">{t('minutes.form_record')}</h2>
                                    <Field
                                        id="content"
                                        label={t('minutes.content')}
                                        hint={t('minutes.content_hint')}
                                        error={form.errors.content}
                                    >
                                        <Textarea
                                            id="content"
                                            value={form.data.content}
                                            onChange={(event) => form.setData('content', event.target.value)}
                                            className="min-h-[280px]"
                                            placeholder={t('minutes.content_placeholder')}
                                            aria-invalid={Boolean(form.errors.content)}
                                        />
                                    </Field>
                                </PanelSection>

                                <PanelFoot>
                                    <Button
                                        type="submit"
                                        variant="primary"
                                        disabled={form.processing || !form.data.session_id}
                                    >
                                        {form.processing ? t('minutes.saving') : t('minutes.save')}
                                    </Button>
                                    <Button variant="ghost" asChild>
                                        <Link href="/minutes">{t('minutes.cancel')}</Link>
                                    </Button>
                                </PanelFoot>
                            </form>
                        </Panel>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
