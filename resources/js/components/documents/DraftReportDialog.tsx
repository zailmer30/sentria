import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Field, fieldAria } from '@/components/ui/field';
import { Input, Textarea } from '@/components/ui/input';
import { SimpleSelect } from '@/components/ui/select';
import { useTranslations } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEvent, useEffect } from 'react';

type OpenReferral = {
    id: string;
    committee_id: string;
    committee: string | null;
};

type Props = {
    referral: OpenReferral;
    nextReportNumber: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    canFileNow?: boolean;
};

const RECOMMENDATIONS = ['approve', 'disapprove', 'amend', 'defer', 'no-action'] as const;

export function DraftReportDialog({ referral, nextReportNumber, open, onOpenChange, canFileNow = false }: Props) {
    const { t } = useTranslations();
    const form = useForm({
        committee_referral_id: referral.id,
        committee_id: referral.committee_id,
        recommendation: 'approve',
        findings: '',
        recommendation_notes: '',
        file_now: false,
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({
            committee_referral_id: referral.id,
            committee_id: referral.committee_id,
            recommendation: 'approve',
            findings: '',
            recommendation_notes: '',
            file_now: false,
        });
        form.clearErrors();
        // Prefill only when the dialog opens or the referral changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, referral.id, referral.committee_id]);

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            findings: data.findings || null,
            recommendation_notes: data.recommendation_notes || null,
            file_now: canFileNow,
        }));

        form.post('/reports', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                title={t(canFileNow ? 'documents.submit_report_title' : 'documents.draft_report_title')}
                description={t(canFileNow ? 'documents.submit_report_hint' : 'documents.draft_report_hint')}
            >
                <form onSubmit={submit} className="space-y-4">
                    <Field id="report-committee" label={t('documents.committee')}>
                        <p id="report-committee" className="text-sm text-ink">
                            {referral.committee ?? t('documents.no_committee')}
                        </p>
                    </Field>

                    <Field
                        id="recommendation"
                        label={t('committees.recommendation')}
                        error={form.errors.recommendation}
                        required
                    >
                        <SimpleSelect
                            id="recommendation"
                            value={form.data.recommendation}
                            onValueChange={(value) => form.setData('recommendation', value)}
                            aria-invalid={Boolean(form.errors.recommendation)}
                            items={RECOMMENDATIONS.map((recommendation) => ({
                                value: recommendation,
                                label: t(`committees.recommendation_${recommendation.replaceAll('-', '_')}`),
                            }))}
                        />
                    </Field>

                    <Field
                        id="findings"
                        label={t('committees.findings')}
                        hint={t('committees.findings_hint')}
                        error={form.errors.findings}
                    >
                        <Textarea
                            {...fieldAria('findings', {
                                hint: t('committees.findings_hint'),
                                error: form.errors.findings,
                            })}
                            value={form.data.findings}
                            onChange={(event) => form.setData('findings', event.target.value)}
                            rows={3}
                        />
                    </Field>

                    <Field
                        id="recommendation_notes"
                        label={t('committees.recommendation_notes')}
                        hint={t('committees.recommendation_notes_hint')}
                        error={form.errors.recommendation_notes}
                    >
                        <Textarea
                            {...fieldAria('recommendation_notes', {
                                hint: t('committees.recommendation_notes_hint'),
                                error: form.errors.recommendation_notes,
                            })}
                            value={form.data.recommendation_notes}
                            onChange={(event) => form.setData('recommendation_notes', event.target.value)}
                            rows={2}
                        />
                    </Field>

                    <Field
                        id="report_number"
                        label={t('committees.report_number')}
                        hint={t('committees.report_number_hint')}
                    >
                        <Input
                            {...fieldAria('report_number', {
                                hint: t('committees.report_number_hint'),
                            })}
                            value={nextReportNumber}
                            readOnly
                            aria-readonly="true"
                            autoComplete="off"
                            className="bg-canvas-sunk font-mono"
                            placeholder={t('committees.report_number_placeholder')}
                        />
                    </Field>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            {t('committees.cancel')}
                        </Button>
                        {canFileNow ? (
                            <Button
                                type="button"
                                variant="secondary"
                                disabled={form.processing}
                                onClick={() => {
                                    form.setData('file_now', false);
                                    form.transform((data) => ({
                                        ...data,
                                        findings: data.findings || null,
                                        recommendation_notes: data.recommendation_notes || null,
                                        file_now: false,
                                    }));
                                    form.post('/reports', {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            form.reset();
                                            onOpenChange(false);
                                        },
                                    });
                                }}
                            >
                                {form.processing ? t('committees.saving') : t('committees.create_report_save')}
                            </Button>
                        ) : null}
                        <Button type="submit" variant="primary" disabled={form.processing}>
                            {form.processing
                                ? t('committees.saving')
                                : t(canFileNow ? 'committees.submit_report' : 'committees.create_report_save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
