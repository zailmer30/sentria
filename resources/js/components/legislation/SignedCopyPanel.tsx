import { Button } from '@/components/ui/button';
import { FileDrop } from '@/components/ui/file-drop';
import { Panel, PanelBody } from '@/components/ui/panel';
import { UserAvatar } from '@/components/users/UserAvatar';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import type { PageProps } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import { Download, Eye, FilePenLine } from 'lucide-react';
import { useRef } from 'react';

export type SignedCopy = {
    filename: string | null;
    size: number | null;
    mime: string | null;
    uploaded_at: string | null;
    uploaded_by: string | null;
    available: boolean;
};

type Props = {
    kind: 'ordinance' | 'resolution';
    recordId: string;
    signedCopy: SignedCopy | null;
    canManage: boolean;
    className?: string;
};

export function SignedCopyPanel({ kind, recordId, signedCopy, canManage, className }: Props) {
    const { t } = useTranslations();
    const { formatDate } = useFormatters();
    const replaceInputRef = useRef<HTMLInputElement>(null);
    const form = useForm({ file: null as File | null });
    const page = usePage<PageProps & { errors: Record<string, string> }>();
    const fileError = form.errors.file ?? page.props.errors.file;
    const basePath = `/${kind === 'ordinance' ? 'ordinances' : 'resolutions'}/${recordId}/signed-copy`;

    function upload(file: File) {
        form.setData('file', file);
        form.post(basePath, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('file'),
        });
    }

    function remove() {
        if (!window.confirm(t('legislation.signed_copy_remove_confirm'))) {
            return;
        }

        router.delete(basePath, { preserveScroll: true });
    }

    return (
        <Panel className={className}>
            <PanelBody className="px-6 py-5">
                <div className="flex items-start justify-between gap-3">
                    <p className="label-eyebrow">{t('legislation.signed_copy')}</p>
                    <span className="flex size-8 items-center justify-center rounded-sm bg-canvas-sunk text-ink-faint">
                        <FilePenLine aria-hidden="true" strokeWidth={1.75} className="size-4" />
                    </span>
                </div>
                <p className="mt-1 text-xs text-ink-subtle">{t('legislation.signed_copy_hint')}</p>

                {signedCopy ? (
                    <div className="mt-3 space-y-3">
                        <div>
                            <p className="truncate text-sm font-semibold text-ink">
                                {signedCopy.filename ?? t('legislation.signed_copy')}
                            </p>
                            <p className="mt-1 font-mono text-2xs text-ink-faint">
                                {signedCopy.size !== null ? formatFileSize(signedCopy.size) : null}
                                {signedCopy.uploaded_at ? (
                                    <>
                                        {signedCopy.size !== null ? ' · ' : null}
                                        {t('legislation.signed_copy_uploaded_on', {
                                            date: formatDate(signedCopy.uploaded_at),
                                        })}
                                    </>
                                ) : null}
                            </p>
                        </div>
                        {signedCopy.uploaded_by ? (
                            <div className="flex items-center gap-2">
                                <UserAvatar
                                    name={signedCopy.uploaded_by}
                                    className="size-7"
                                    fallbackClassName="text-2xs"
                                />
                                <p className="text-sm text-ink">{signedCopy.uploaded_by}</p>
                            </div>
                        ) : null}
                        {signedCopy.available ? (
                            <div className="flex flex-wrap gap-2">
                                <Button variant="secondary" className="flex-1" asChild>
                                    <a href={`${basePath}/preview`} target="_blank" rel="noreferrer">
                                        <Eye aria-hidden="true" strokeWidth={1.75} />
                                        {t('legislation.signed_copy_preview')}
                                    </a>
                                </Button>
                                <Button variant="secondary" className="flex-1" asChild>
                                    <a href={`${basePath}/download`}>
                                        <Download aria-hidden="true" strokeWidth={1.75} />
                                        {t('legislation.signed_copy_download')}
                                    </a>
                                </Button>
                            </div>
                        ) : null}
                        {canManage ? (
                            <div className="flex flex-wrap gap-2">
                                <input
                                    ref={replaceInputRef}
                                    type="file"
                                    accept="application/pdf,.pdf"
                                    className="sr-only"
                                    aria-label={t('legislation.signed_copy_replace')}
                                    onChange={(event) => {
                                        const next = event.target.files?.[0];
                                        event.target.value = '';

                                        if (next) {
                                            upload(next);
                                        }
                                    }}
                                />
                                <Button
                                    type="button"
                                    variant="secondary"
                                    className="flex-1"
                                    disabled={form.processing}
                                    onClick={() => replaceInputRef.current?.click()}
                                >
                                    {form.processing
                                        ? t('legislation.signed_copy_uploading')
                                        : t('legislation.signed_copy_replace')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="flex-1"
                                    disabled={form.processing}
                                    onClick={remove}
                                >
                                    {t('legislation.signed_copy_remove')}
                                </Button>
                            </div>
                        ) : null}
                        {fileError ? (
                            <p className="text-xs font-medium text-critical">{fileError}</p>
                        ) : null}
                    </div>
                ) : canManage ? (
                    <div className="mt-3 space-y-1.5">
                        <FileDrop
                            id={`${kind}-signed-copy`}
                            file={form.data.file}
                            accept="application/pdf,.pdf"
                            disabled={form.processing}
                            invalid={Boolean(fileError)}
                            dropLabel={t('documents.file_drop')}
                            browseLabel={t('documents.file_browse')}
                            replaceLabel={t('documents.file_replace')}
                            removeLabel={t('documents.file_remove')}
                            emptyHint={t('legislation.signed_copy_pdf_only')}
                            icon={FilePenLine}
                            aria-describedby={fileError ? `${kind}-signed-copy-error` : undefined}
                            onFileChange={(file) => {
                                form.clearErrors('file');

                                if (file) {
                                    upload(file);
                                } else {
                                    form.setData('file', null);
                                }
                            }}
                        />
                        {fileError ? (
                            <p
                                id={`${kind}-signed-copy-error`}
                                className="text-xs font-medium text-critical"
                            >
                                {fileError}
                            </p>
                        ) : null}
                    </div>
                ) : (
                    <p className="mt-3 text-sm text-ink-subtle">{t('legislation.signed_copy_empty')}</p>
                )}
            </PanelBody>
        </Panel>
    );
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        const kb = bytes / 1024;

        return `${kb < 10 ? kb.toFixed(1) : Math.round(kb)} KB`;
    }

    const mb = bytes / (1024 * 1024);

    return `${mb < 10 ? mb.toFixed(1) : Math.round(mb)} MB`;
}
