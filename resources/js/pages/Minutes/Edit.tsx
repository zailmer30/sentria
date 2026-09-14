import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Textarea } from '@/components/ui/input';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelFoot, PanelSection } from '@/components/ui/panel';
import { Provenance, ProvenanceField } from '@/components/ui/provenance';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type MinutesDetail = {
    id: string;
    content: string | null;
    session: { title: string; session_number: string } | null;
};

type Props = {
    minutes: MinutesDetail;
};

export default function MinutesEdit({ minutes }: Props) {
    const { t } = useTranslations();
    const form = useForm({ content: minutes.content ?? '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.put(`/minutes/${minutes.id}`);
    }

    return (
        <AppLayout title={t('minutes.edit')}>
            <div className="mx-auto flex max-w-3xl flex-col gap-5">
                <PageHeader
                    title={t('minutes.edit')}
                    description={t('minutes.edit_subtitle')}
                    provenance={
                        minutes.session?.session_number ? (
                            <Provenance bare>
                                <ProvenanceField label={t('minutes.session')}>
                                    {minutes.session.session_number}
                                </ProvenanceField>
                            </Provenance>
                        ) : undefined
                    }
                />

                <Panel>
                    <form onSubmit={submit}>
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
                                    className="min-h-[320px]"
                                    aria-invalid={Boolean(form.errors.content)}
                                />
                            </Field>
                        </PanelSection>

                        <PanelFoot>
                            <Button type="submit" variant="primary" disabled={form.processing}>
                                {form.processing ? t('minutes.saving') : t('minutes.save')}
                            </Button>
                            <Button variant="ghost" asChild>
                                <Link href={`/minutes/${minutes.id}`}>{t('minutes.cancel')}</Link>
                            </Button>
                        </PanelFoot>
                    </form>
                </Panel>
            </div>
        </AppLayout>
    );
}
