import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Field, fieldAria } from '@/components/ui/field';
import { Textarea } from '@/components/ui/input';
import { useTranslations } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEvent, useEffect } from 'react';

type Props = {
    documentSlug: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function ReturnDocumentDialog({ documentSlug, open, onOpenChange }: Props) {
    const { t } = useTranslations();
    const form = useForm({
        to: 'returned-for-revision',
        return_reason: '',
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({ to: 'returned-for-revision', return_reason: '' });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, documentSlug]);

    function submit(event: FormEvent) {
        event.preventDefault();

        if (!form.data.return_reason.trim()) {
            form.setError('return_reason', t('documents.return_reason_required'));

            return;
        }

        form.post(`/documents/${documentSlug}/transition`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent title={t('documents.return_title')} description={t('documents.return_hint')}>
                <form onSubmit={submit} className="space-y-4">
                    <Field
                        id="return_reason"
                        label={t('documents.return_reason')}
                        hint={t('documents.return_reason_hint')}
                        error={form.errors.return_reason}
                        required
                    >
                        <Textarea
                            {...fieldAria('return_reason', {
                                hint: t('documents.return_reason_hint'),
                                error: form.errors.return_reason,
                            })}
                            value={form.data.return_reason}
                            onChange={(event) => form.setData('return_reason', event.target.value)}
                            rows={4}
                            placeholder={t('documents.return_reason_placeholder')}
                        />
                    </Field>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            {t('documents.cancel')}
                        </Button>
                        <Button type="submit" variant="secondary" disabled={form.processing}>
                            {form.processing ? t('documents.saving') : t('documents.return_save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
