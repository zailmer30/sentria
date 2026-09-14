import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { FileDrop } from '@/components/ui/file-drop';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEvent, useEffect } from 'react';

type Props = {
    documentSlug: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function ReuploadDocumentDialog({ documentSlug, open, onOpenChange }: Props) {
    const { t } = useTranslations();
    const form = useForm({
        change_summary: '',
        file: null as File | null,
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({ change_summary: '', file: null });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, documentSlug]);

    function submit(event: FormEvent) {
        event.preventDefault();

        if (!form.data.file) {
            form.setError('file', t('documents.file_required'));

            return;
        }

        form.post(`/documents/${documentSlug}/versions`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent title={t('documents.reupload_file')} description={t('documents.reupload_version_hint')}>
                <form onSubmit={submit} className="space-y-3">
                    <div className="space-y-1.5">
                        <FileDrop
                            id="reupload-file"
                            file={form.data.file}
                            onFileChange={(file) => {
                                form.setData('file', file);
                                form.clearErrors('file');
                            }}
                            dropLabel={t('documents.file_drop')}
                            browseLabel={t('documents.file_browse')}
                            replaceLabel={t('documents.file_replace')}
                            removeLabel={t('documents.file_remove')}
                            invalid={Boolean(form.errors.file)}
                            aria-describedby={form.errors.file ? 'reupload-file-error' : undefined}
                        />
                        {form.errors.file ? (
                            <p id="reupload-file-error" className="text-xs font-medium text-critical">
                                {form.errors.file}
                            </p>
                        ) : null}
                    </div>

                    <Input
                        id="reupload-change-summary"
                        placeholder={t('documents.change_summary')}
                        value={form.data.change_summary}
                        onChange={(event) => form.setData('change_summary', event.target.value)}
                        aria-label={t('documents.change_summary')}
                    />

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            {t('documents.cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? t('documents.saving') : t('documents.reupload_file')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
