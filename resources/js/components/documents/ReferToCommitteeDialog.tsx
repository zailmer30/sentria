import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Field, fieldAria } from '@/components/ui/field';
import { Input, Textarea } from '@/components/ui/input';
import { Notice } from '@/components/ui/notice';
import { SearchableMultiSelect } from '@/components/ui/select';
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
    mode?: 'create' | 'edit';
    committeeIds?: string[];
    meetingOn?: string | null;
    remarks?: string | null;
    /** Prefix field ids when more than one refer dialog can mount on a page. */
    idPrefix?: string;
};

export function ReferToCommitteeDialog({
    documentSlug,
    committeeId,
    committees,
    open,
    onOpenChange,
    action,
    agendaItemId,
    mode = 'create',
    committeeIds,
    meetingOn,
    remarks,
    idPrefix = 'refer',
}: Props) {
    const { t } = useTranslations();
    const editing = mode === 'edit';
    const initialCommitteeIds =
        committeeIds && committeeIds.length > 0 ? committeeIds : committeeId ? [committeeId] : [];
    const form = useForm({
        to: 'committee-referral',
        committee_ids: initialCommitteeIds,
        meeting_on: meetingOn ?? '',
        remarks: remarks ?? '',
        agenda_item_id: agendaItemId ?? '',
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({
            to: 'committee-referral',
            committee_ids: initialCommitteeIds,
            meeting_on: meetingOn ?? '',
            remarks: remarks ?? '',
            agenda_item_id: agendaItemId ?? '',
        });
        form.clearErrors();
        // Prefill only when the dialog opens or the assigned committees change.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, committeeId, agendaItemId, meetingOn, remarks, editing, committeeIds?.join('|')]);

    function submit(event: FormEvent) {
        event.preventDefault();

        if (form.data.committee_ids.length === 0) {
            form.setError('committee_ids', t('documents.refer_committee_required'));

            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (editing) {
            form.transform((data) => ({
                committee_ids: data.committee_ids,
                meeting_on: data.meeting_on || null,
                remarks: data.remarks.trim() || null,
            }));
            form.put(`/documents/${documentSlug}/referral`, options);

            return;
        }

        form.transform((data) => ({
            ...data,
            meeting_on: data.meeting_on || null,
            remarks: data.remarks.trim() || null,
        }));

        form.post(action ?? `/documents/${documentSlug}/transition`, options);
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                title={editing ? t('documents.refer_edit_title') : t('documents.refer_title')}
                description={editing ? t('documents.refer_edit_hint') : t('documents.refer_hint')}
            >
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
                            id={`${idPrefix}-committee-ids`}
                            label={t('documents.committee')}
                            hint={t('documents.refer_committee_hint')}
                            error={form.errors.committee_ids}
                            required
                        >
                            <SearchableMultiSelect
                                id={`${idPrefix}-committee-ids`}
                                values={form.data.committee_ids}
                                onValuesChange={(next) => form.setData('committee_ids', next)}
                                items={committees.map((committee) => ({
                                    value: committee.id,
                                    label: committee.name,
                                }))}
                                placeholder={t('documents.refer_committee_placeholder')}
                                searchPlaceholder={t('documents.refer_committee_search')}
                                emptyLabel={t('documents.refer_committee_empty_search')}
                                removeLabel={(name) => t('documents.refer_committee_remove', { name })}
                                aria-invalid={Boolean(form.errors.committee_ids)}
                                aria-label={t('documents.committee')}
                            />
                        </Field>

                        <Field
                            id={`${idPrefix}-meeting-on`}
                            label={t('documents.refer_meeting_on')}
                            hint={t('documents.refer_meeting_on_hint')}
                            error={form.errors.meeting_on}
                        >
                            <Input
                                {...fieldAria(`${idPrefix}-meeting-on`, {
                                    hint: t('documents.refer_meeting_on_hint'),
                                    error: form.errors.meeting_on,
                                })}
                                type="date"
                                value={form.data.meeting_on}
                                onChange={(event) => form.setData('meeting_on', event.target.value)}
                            />
                        </Field>

                        <Field
                            id={`${idPrefix}-remarks`}
                            label={t('documents.refer_remarks')}
                            hint={t('documents.refer_remarks_hint')}
                            error={form.errors.remarks}
                        >
                            <Textarea
                                {...fieldAria(`${idPrefix}-remarks`, {
                                    hint: t('documents.refer_remarks_hint'),
                                    error: form.errors.remarks,
                                })}
                                value={form.data.remarks}
                                onChange={(event) => form.setData('remarks', event.target.value)}
                                rows={3}
                                placeholder={t('documents.refer_remarks_placeholder')}
                            />
                        </Field>

                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                                {t('committees.cancel')}
                            </Button>
                            <Button type="submit" variant="primary" disabled={form.processing}>
                                {form.processing
                                    ? t('committees.saving')
                                    : editing
                                      ? t('documents.refer_update_save')
                                      : t('committees.refer_save')}
                            </Button>
                        </DialogFooter>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
