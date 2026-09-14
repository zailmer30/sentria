import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Field, fieldAria } from '@/components/ui/field';
import { Textarea } from '@/components/ui/input';
import { useTranslations } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEvent, useEffect } from 'react';

type OutcomeStatus = 'returned' | 'closed';

type Props = {
    referralId: string;
    status: OutcomeStatus;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function OutcomeReferralDialog({ referralId, status, open, onOpenChange }: Props) {
    const { t } = useTranslations();
    const form = useForm({
        status,
        outcome_notes: '',
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({ status, outcome_notes: '' });
        form.clearErrors();
        // Reset the reason each time the dialog opens.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, referralId, status]);

    function submit(event: FormEvent) {
        event.preventDefault();

        if (!form.data.outcome_notes.trim()) {
            form.setError(
                'outcome_notes',
                t(status === 'closed' ? 'committees.close_reason_required' : 'committees.return_reason_required'),
            );

            return;
        }

        form.put(`/referrals/${referralId}`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    const titleKey = status === 'closed' ? 'committees.close_title' : 'committees.return_title';
    const hintKey = status === 'closed' ? 'committees.close_hint' : 'committees.return_hint';
    const reasonKey = status === 'closed' ? 'committees.close_reason' : 'committees.return_reason';
    const reasonHintKey = status === 'closed' ? 'committees.close_reason_hint' : 'committees.return_reason_hint';
    const placeholderKey =
        status === 'closed' ? 'committees.close_reason_placeholder' : 'committees.return_reason_placeholder';
    const saveKey = status === 'closed' ? 'committees.close_save' : 'committees.return_save';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent title={t(titleKey)} description={t(hintKey)}>
                <form onSubmit={submit} className="space-y-4">
                    <Field
                        id="outcome_notes"
                        label={t(reasonKey)}
                        hint={t(reasonHintKey)}
                        error={form.errors.outcome_notes}
                        required
                    >
                        <Textarea
                            {...fieldAria('outcome_notes', {
                                hint: t(reasonHintKey),
                                error: form.errors.outcome_notes,
                            })}
                            value={form.data.outcome_notes}
                            onChange={(event) => form.setData('outcome_notes', event.target.value)}
                            rows={4}
                            placeholder={t(placeholderKey)}
                        />
                    </Field>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            {t('committees.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            variant={status === 'closed' ? 'danger' : 'secondary'}
                            disabled={form.processing}
                        >
                            {form.processing ? t('committees.saving') : t(saveKey)}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** @deprecated Prefer OutcomeReferralDialog with status="returned" */
export function ReturnReferralDialog(props: Omit<Props, 'status'>) {
    return <OutcomeReferralDialog {...props} status="returned" />;
}
