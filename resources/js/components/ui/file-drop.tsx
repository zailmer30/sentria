import { cn } from '@/lib/utils';
import { FileText, X, type LucideIcon } from 'lucide-react';
import { DragEvent, useRef, useState } from 'react';

/**
 * A file control that looks like the rest of the form, not like the browser's
 * native picker. The empty well is the file input itself (opacity 0) so the
 * picker opens even inside `overflow-hidden` panels — programmatic clicks on a
 * clipped `sr-only` input are ignored by Chromium. Colour is never the only
 * error signal — invalid also fills the well and the Field wrapper names the
 * problem.
 */

type FileDropProps = {
    id: string;
    file: File | null;
    onFileChange: (file: File | null) => void;
    accept?: string;
    disabled?: boolean;
    invalid?: boolean;
    dropLabel: string;
    browseLabel: string;
    replaceLabel: string;
    removeLabel: string;
    emptyHint?: string;
    icon?: LucideIcon;
    className?: string;
    'aria-describedby'?: string;
};

export function FileDrop({
    id,
    file,
    onFileChange,
    accept,
    disabled = false,
    invalid = false,
    dropLabel,
    browseLabel,
    replaceLabel,
    removeLabel,
    emptyHint,
    icon: Icon = FileText,
    className,
    'aria-describedby': describedBy,
}: FileDropProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const dragDepth = useRef(0);
    const [dragging, setDragging] = useState(false);

    function handleDragEnter(event: DragEvent<HTMLDivElement>) {
        event.preventDefault();
        event.stopPropagation();

        if (disabled) {
            return;
        }

        dragDepth.current += 1;
        setDragging(true);
    }

    function handleDragOver(event: DragEvent<HTMLDivElement>) {
        event.preventDefault();
        event.stopPropagation();
    }

    function handleDragLeave(event: DragEvent<HTMLDivElement>) {
        event.preventDefault();
        event.stopPropagation();
        dragDepth.current = Math.max(0, dragDepth.current - 1);

        if (dragDepth.current === 0) {
            setDragging(false);
        }
    }

    function handleDrop(event: DragEvent<HTMLDivElement>) {
        event.preventDefault();
        event.stopPropagation();
        dragDepth.current = 0;
        setDragging(false);

        if (disabled) {
            return;
        }

        const next = event.dataTransfer.files.item(0);

        if (next) {
            onFileChange(next);
        }
    }

    return (
        <div
            onDragEnter={handleDragEnter}
            onDragOver={handleDragOver}
            onDragLeave={handleDragLeave}
            onDrop={handleDrop}
            className={cn(
                'relative rounded-[var(--radius-md)] border border-line-control bg-surface',
                'transition-[border-color,background-color,box-shadow] duration-[var(--duration-fast)]',
                'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-[var(--color-focus)]',
                dragging && !disabled && 'border-accent bg-accent-soft',
                invalid && 'border-critical bg-critical-soft',
                disabled && 'cursor-not-allowed border-line-strong bg-canvas-sunk opacity-45',
                className,
            )}
        >
            <input
                ref={inputRef}
                id={id}
                type="file"
                accept={accept}
                disabled={disabled}
                className={cn(
                    'z-10 cursor-pointer disabled:cursor-not-allowed',
                    file
                        ? 'absolute top-0 left-0 size-px overflow-hidden opacity-0'
                        : 'absolute inset-0 size-full opacity-0',
                )}
                aria-label={`${dropLabel} ${browseLabel}`}
                aria-invalid={invalid || undefined}
                aria-describedby={describedBy}
                onChange={(event) => {
                    onFileChange(event.target.files?.[0] ?? null);
                    event.target.value = '';
                }}
            />

            {file ? (
                <div className="flex items-center gap-3 px-3 py-3">
                    <label
                        htmlFor={id}
                        className={cn(
                            'flex min-w-0 flex-1 items-center gap-3 rounded-[var(--radius-sm)] text-left',
                            'focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-[var(--color-focus)]',
                            disabled ? 'pointer-events-none' : 'cursor-pointer',
                        )}
                    >
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-[var(--radius-md)] border border-line bg-surface-alt text-ink-faint">
                            <Icon aria-hidden="true" className="size-4" strokeWidth={1.75} />
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-medium text-ink">{file.name}</span>
                            <span className="mt-0.5 block font-mono text-xs text-ink-subtle">
                                {formatFileSize(file.size)}
                                <span className="font-sans text-ink-faint"> · {replaceLabel}</span>
                            </span>
                        </span>
                    </label>
                    <button
                        type="button"
                        disabled={disabled}
                        onClick={() => onFileChange(null)}
                        className="relative z-20 inline-flex size-9 shrink-0 items-center justify-center rounded-[var(--radius-md)] text-ink-muted transition-colors duration-[var(--duration-fast)] hover:bg-canvas-sunk hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-focus)]"
                        aria-label={removeLabel}
                    >
                        <X aria-hidden="true" className="size-4" strokeWidth={1.75} />
                    </button>
                </div>
            ) : (
                <div className="pointer-events-none flex w-full flex-col items-center gap-1.5 px-4 py-8 text-center">
                    <span className="mb-1 flex size-10 items-center justify-center rounded-[var(--radius-md)] border border-line bg-surface-alt text-ink-faint">
                        <Icon aria-hidden="true" className="size-4" strokeWidth={1.75} />
                    </span>
                    <span className="text-sm font-medium text-ink">
                        {dropLabel}{' '}
                        <span className="underline decoration-[var(--color-line-strong)] underline-offset-2">
                            {browseLabel}
                        </span>
                    </span>
                    {emptyHint ? <span className="max-w-sm text-xs font-normal text-ink-subtle">{emptyHint}</span> : null}
                </div>
            )}
        </div>
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
