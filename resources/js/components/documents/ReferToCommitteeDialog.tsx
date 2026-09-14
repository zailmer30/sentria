import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Notice } from '@/components/ui/notice';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslations } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEvent, useEffect } from 'react';

type CommitteeOption = {
    id: string;
    name: string;
};

type Props = {
    documentSlug: string;
    committeeId: string | null;
    committees: CommitteeOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Defaults to the document-desk transition. Floor referral posts here instead. */
    action?: string;
    agendaItemId?: string;
};

export function ReferToCommitteeDialog({
    documentSlug,
    committeeId,
    committees,
    open,
    onOpenChange,
    action,
    agendaItemId,
}: Props) {
    const { t } = useTranslations();
    const form = useForm({
        to: 'committee-referral',
        committee_id: committeeId ?? '',
        agenda_item_id: agendaItemId ?? '',
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({
            to: 'committee-referral',
            committee_id: committeeId ?? '',
            agenda_item_id: agendaItemId ?? '',
        });
        form.clearErrors();
        // Prefill only when the dialog opens or the assigned committee changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, committeeId, agendaItemId]);

    function submit(event: FormEvent) {
        event.preventDefault();

        if (!form.data.committee_id) {
            form.setError('committee_id', t('documents.refer_committee_required'));

            return;
        }

        form.post(action ?? `/documents/${documentSlug}/transition`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent title={t('documents.refer_title')} description={t('documents.refer_hint')}>
                {committees.length === 0 ? (
                    <>
                        <Notice tone="info">{t('documents.refer_committee_empty')}</Notice>
                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                                {t('committees.cancel')}
                            </Button>
                        </DialogFooter>
                    </>
                ) : (
                    <form onSubmit={submit} className="space-y-4">
                        <Field
                            id="refer-committee-id"
                            label={t('documents.committee')}
                            hint={t('documents.refer_committee_hint')}
                            error={form.errors.committee_id}
                            required
                        >
                            <Select
                                value={form.data.committee_id || undefined}
                                onValueChange={(value) => form.setData('committee_id', value)}
                            >
                                <SelectTrigger
                                    id="refer-committee-id"
                                    aria-invalid={Boolean(form.errors.committee_id)}
                                >
                                    <SelectValue placeholder={t('documents.refer_committee_placeholder')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {committees.map((committee) => (
                                        <SelectItem key={committee.id} value={committee.id}>
                                            {committee.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>

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
