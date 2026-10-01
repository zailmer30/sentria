import { Button } from '@/components/ui/button';
import { FileDrop } from '@/components/ui/file-drop';
import { Panel, PanelBody, PanelFoot } from '@/components/ui/panel';
import { UserAvatar } from '@/components/users/UserAvatar';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import { Download, Eye, FilePenLine, FileText, RefreshCw, Trash2 } from 'lucide-react';
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
    requiresUnpublishConfirmation?: boolean;
    className?: string;
};

export function SignedCopyPanel({
    kind,
    recordId,
    signedCopy,
    canManage,
    requiresUnpublishConfirmation = false,
    className,
}: Props) {
    const { t } = useTranslations();
    const { formatDate } = useFormatters();
    const replaceInputRef = useRef<HTMLInputElement>(null);
    const form = useForm({ file: null as File | null, confirm_unpublish: false });
    const page = usePage<PageProps & { errors: Record<string, string> }>();
    const fileError = form.errors.file ?? page.props.errors.file;
    const basePath = `/${kind === 'ordinance' ? 'ordinances' : 'resolutions'}/${recordId}/signed-copy`;

    function upload(file: File) {
        if (requiresUnpublishConfirmation && !window.confirm(t('legislation.signed_copy_unpublish_confirm'))) {
            return;
        }

        form.transform(() => ({
            file,
            ...(requiresUnpublishConfirmation ? { confirm_unpublish: 1 } : {}),
        }));
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

    const filename = signedCopy?.filename ?? t('legislation.signed_copy');
    const showActions = Boolean(signedCopy && (signedCopy.available || canManage));

    return (
        <Panel className={className}>
            <PanelBody className="px-5 py-5">
                <div className="min-w-0">
                    <h2 className="text-sm font-semibold tracking-tight text-ink">{t('legislation.signed_copy')}</h2>
                    <p className="mt-1 max-w-xl text-xs leading-relaxed text-ink-subtle">
                        {t('legislation.signed_copy_hint')}
                    </p>
                </div>

                {signedCopy ? (
                    <div className="mt-4 flex min-w-0 items-start gap-3.5 rounded-md border border-line bg-canvas-sunk/40 px-3.5 py-3.5">
                        <PdfMark />
                        <div className="min-w-0 flex-1 pt-0.5">
                            <p className="truncate text-sm font-semibold tracking-tight text-ink" title={filename}>
                                {filename}
                            </p>
                            <div className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1.5 text-xs text-ink-subtle">
                                {signedCopy.size !== null ? (
                                    <span className="font-mono tracking-tight">{formatFileSize(signedCopy.size)}</span>
                                ) : null}
                                {signedCopy.uploaded_at ? (
                                    <>
                                        {signedCopy.size !== null ? (
                                            <span aria-hidden="true" className="text-ink-faint">
                                                ·
                                            </span>
                                        ) : null}
                                        <span>
                                            {t('legislation.signed_copy_uploaded_on', {
                                                date: formatDate(signedCopy.uploaded_at),
                                            })}
                                        </span>
                                    </>
                                ) : null}
                                {signedCopy.uploaded_by ? (
                                    <>
                                        {signedCopy.size !== null || signedCopy.uploaded_at ? (
                                            <span aria-hidden="true" className="text-ink-faint">
                                                ·
                                            </span>
                                        ) : null}
                                        <span className="inline-flex min-w-0 items-center gap-1.5">
                                            <UserAvatar
                                                name={signedCopy.uploaded_by}
                                                className="size-5"
                                                fallbackClassName="text-[9px]"
                                            />
                                            <span className="truncate font-medium text-ink">{signedCopy.uploaded_by}</span>
                                        </span>
                                    </>
                                ) : null}
                            </div>
                        </div>
                    </div>
                ) : canManage ? (
                    <div className="mt-4 space-y-1.5">
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
                    </div>
                ) : (
                    <p className="mt-4 text-sm text-ink-subtle">{t('legislation.signed_copy_empty')}</p>
                )}

                {fileError ? (
                    <p
                        id={signedCopy ? undefined : `${kind}-signed-copy-error`}
                        className="mt-3 text-xs font-medium text-critical"
                    >
                        {fileError}
                    </p>
                ) : null}
            </PanelBody>

            {showActions ? (
                <PanelFoot className="bg-surface">
                    {signedCopy?.available ? (
                        <>
                            <Button variant="secondary" size="sm" asChild>
                                <a href={`${basePath}/preview`} target="_blank" rel="noreferrer">
                                    <Eye aria-hidden="true" strokeWidth={1.75} />
                                    {t('legislation.signed_copy_preview')}
                                </a>
                            </Button>
                            <Button variant="secondary" size="sm" asChild>
                                <a href={`${basePath}/download`}>
                                    <Download aria-hidden="true" strokeWidth={1.75} />
                                    {t('legislation.signed_copy_download')}
                                </a>
                            </Button>
                        </>
                    ) : null}
                    {canManage ? (
                        <div className={cn('flex items-center gap-1', signedCopy?.available && 'sm:ml-auto')}>
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
                                variant="ghost"
                                size="sm"
                                disabled={form.processing}
                                onClick={() => replaceInputRef.current?.click()}
                            >
                                <RefreshCw
                                    aria-hidden="true"
                                    strokeWidth={1.75}
                                    className={cn(form.processing && 'animate-spin')}
                                />
                                {form.processing
                                    ? t('legislation.signed_copy_uploading')
                                    : t('legislation.signed_copy_replace')}
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="text-ink-subtle hover:bg-critical-soft hover:text-critical"
                                disabled={form.processing}
                                onClick={remove}
                            >
                                <Trash2 aria-hidden="true" strokeWidth={1.75} />
                                {t('legislation.signed_copy_remove')}
                            </Button>
                        </div>
                    ) : null}
                </PanelFoot>
            ) : null}
        </Panel>
    );
}

function PdfMark() {
    return (
        <span
            aria-hidden="true"
            className="flex size-11 shrink-0 flex-col items-center justify-center gap-0.5 rounded-md border border-line bg-surface shadow-xs"
        >
            <FileText strokeWidth={1.75} className="size-4 text-ink" />
            <span className="text-[9px] leading-none font-semibold tracking-[0.08em] text-ink-subtle">PDF</span>
        </span>
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
