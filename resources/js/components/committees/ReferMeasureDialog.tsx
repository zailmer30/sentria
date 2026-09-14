import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Field, fieldAria } from '@/components/ui/field';
import { Checkbox, Input, Textarea } from '@/components/ui/input';
import { Notice } from '@/components/ui/notice';
import { SimpleSelect } from '@/components/ui/select';
import { useTranslations } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type ReferableDocument = {
    id: string;
    title: string;
    reference_number: string | null;
};

type Props = {
    committeeId: string;
    documents: ReferableDocument[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function ReferMeasureDialog({ committeeId, documents, open, onOpenChange }: Props) {
    const { t } = useTranslations();
    const form = useForm({
        document_id: documents[0]?.id ?? '',
        committee_id: committeeId,
        instructions: '',
        due_at: '',
        is_primary: true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            instructions: data.instructions || null,
            due_at: data.due_at || null,
        }));

        form.post('/referrals', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent title={t('committees.refer_title')} description={t('committees.refer_hint')}>
                {documents.length === 0 ? (
                    <>
                        <Notice tone="info">{t('committees.document_empty')}</Notice>
                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                                {t('committees.cancel')}
                            </Button>
                        </DialogFooter>
                    </>
                ) : (
                    <form onSubmit={submit} className="space-y-4">
                        <Field
                            id="document_id"
                            label={t('committees.document')}
                            hint={t('committees.document_hint')}
                            error={form.errors.document_id}
                            required
                        >
                            <SimpleSelect
                                id="document_id"
                                value={form.data.document_id}
                                onValueChange={(value) => form.setData('document_id', value)}
                                aria-invalid={Boolean(form.errors.document_id)}
                                items={documents.map((document) => ({
                                    value: document.id,
                                    label: document.reference_number
                                        ? `${document.reference_number} — ${document.title}`
                                        : document.title,
                                }))}
                            />
                        </Field>

                        <Field
                            id="instructions"
                            label={t('committees.instructions')}
                            hint={t('committees.instructions_hint')}
                            error={form.errors.instructions}
                        >
                            <Textarea
                                {...fieldAria('instructions', {
                                    hint: t('committees.instructions_hint'),
                                    error: form.errors.instructions,
                                })}
                                value={form.data.instructions}
                                onChange={(event) => form.setData('instructions', event.target.value)}
                                rows={3}
                                placeholder={t('committees.instructions_placeholder')}
                            />
                        </Field>

                        <Field
                            id="due_at"
                            label={t('committees.due_at')}
                            hint={t('committees.due_at_hint')}
                            error={form.errors.due_at}
                        >
                            <Input
                                {...fieldAria('due_at', {
                                    hint: t('committees.due_at_hint'),
                                    error: form.errors.due_at,
                                })}
                                type="date"
                                value={form.data.due_at}
                                onChange={(event) => form.setData('due_at', event.target.value)}
                            />
                        </Field>

                        <label htmlFor="is_primary" className="flex items-start gap-2.5">
                            <Checkbox
                                id="is_primary"
                                className="mt-0.5"
                                checked={form.data.is_primary}
                                onChange={(event) => form.setData('is_primary', event.target.checked)}
                            />
                            <span>
                                <span className="block text-sm font-medium text-ink">{t('committees.is_primary')}</span>
                                <span className="block text-xs text-ink-muted">{t('committees.is_primary_hint')}</span>
                            </span>
                        </label>

                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                                {t('committees.cancel')}
                            </Button>
                            <Button type="submit" variant="primary" disabled={form.processing}>
                                {form.processing ? t('committees.saving') : t('committees.refer_save')}
                            </Button>
                        </DialogFooter>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
