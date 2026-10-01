import { Button } from '@/components/ui/button';
import { Definition, DefinitionList } from '@/components/ui/definition-list';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Field, fieldAria } from '@/components/ui/field';
import { FileDrop } from '@/components/ui/file-drop';
import { Input, Textarea } from '@/components/ui/input';
import { Notice } from '@/components/ui/notice';
import { Progress } from '@/components/ui/progress';
import { SearchableSelect, SimpleSelect } from '@/components/ui/select';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { FormEvent, useEffect, useRef, useState } from 'react';

/**
 * Intake for a new legislative record, as a four-step dialog: type,
 * details, source file, then a last look before the filing is sent.
 */

type DocumentTypeOption = {
    value: string;
    label: string;
    tag?: string;
    next_reference?: string;
};

type ConfidentialityOption = { value: string; label: string };
type CommitteeOption = { id: string; name: string };

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    documentTypes: DocumentTypeOption[];
    confidentialityLevels: ConfidentialityOption[];
    committees: CommitteeOption[];
    maxUploadSizeKb: number;
    acceptedFileTypes: string;
};

type Step = 0 | 1 | 2 | 3;

const STEPS = ['type', 'details', 'upload', 'review'] as const;
const DRAFT_KEY = 'sentria.submit-document-draft';

type DraftPayload = {
    title: string;
    document_type: string;
    confidentiality: string;
    abstract: string;
    external_author: string;
    enacting_clause: string;
    explanatory_note: string;
    committee_id: string;
    reference_number: string;
    change_summary: string;
    step: Step;
};

export function SubmitDocumentDialog({
    open,
    onOpenChange,
    documentTypes,
    confidentialityLevels,
    committees,
    maxUploadSizeKb,
    acceptedFileTypes,
}: Props) {
    const { t } = useTranslations();
    const [step, setStep] = useState<Step>(0);
    const [draftSaved, setDraftSaved] = useState(false);
    /** After a successful filing, closing must not re-write the cleared draft. */
    const skipDraftPersist = useRef(false);

    const defaultType = documentTypes[0]?.value ?? '';
    const defaultConfidentiality =
        confidentialityLevels.find((level) => level.value === 'internal')?.value ?? confidentialityLevels[0]?.value ?? 'internal';

    const emptyForm = {
        title: '',
        document_type: defaultType,
        confidentiality: defaultConfidentiality,
        abstract: '',
        external_author: '',
        enacting_clause: '',
        explanatory_note: '',
        committee_id: '',
        reference_number: documentTypes[0]?.next_reference ?? '',
        change_summary: '',
        file: null as File | null,
    };

    const form = useForm(emptyForm);

    const fileHint = t('documents.file_hint', { size: formatMaxSize(maxUploadSizeKb) });
    const fileDescribedBy = fieldAria('file', {
        hint: form.data.file ? fileHint : undefined,
        error: form.errors.file,
    })['aria-describedby'];
    const isMeasure = isLegislativeMeasure(form.data.document_type);
    const isOrdinance = isOrdinanceMeasure(form.data.document_type);
    const selectedType = documentTypes.find((type) => type.value === form.data.document_type);
    const selectedConfidentiality = confidentialityLevels.find((level) => level.value === form.data.confidentiality);
    const selectedCommittee = committees.find((committee) => committee.id === form.data.committee_id);
    const lastStep = step === 3;

    useEffect(() => {
        if (!open) {
            return;
        }

        skipDraftPersist.current = false;

        const draft = readDraft();
        const restoredType = documentTypes.find((type) => type.value === draft?.document_type);

        form.setData({
            title: draft?.title ?? '',
            document_type: restoredType?.value ?? defaultType,
            confidentiality:
                confidentialityLevels.find((level) => level.value === draft?.confidentiality)?.value ?? defaultConfidentiality,
            abstract: draft?.abstract ?? '',
            external_author: draft?.external_author ?? '',
            enacting_clause: draft?.enacting_clause ?? '',
            explanatory_note: draft?.explanatory_note ?? '',
            committee_id: committees.some((committee) => committee.id === draft?.committee_id) ? (draft?.committee_id ?? '') : '',
            reference_number: restoredType?.next_reference ?? documentTypes[0]?.next_reference ?? '',
            change_summary: draft?.change_summary ?? '',
            file: null,
        });
        form.clearErrors();
        // sessionStorage is not available during SSR; restore only when the dialog opens.
        // eslint-disable-next-line react-hooks/set-state-in-effect -- restore local draft
        setStep(draft?.step ?? 0);
        setDraftSaved(false);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    function changeDocumentType(value: string) {
        const next = documentTypes.find((type) => type.value === value);

        form.setData((data) => ({
            ...data,
            document_type: value,
            reference_number: next?.next_reference ?? '',
        }));
        form.clearErrors('document_type');
        form.clearErrors('reference_number');
    }

    function takeFile(next: File | null) {
        if (!next) {
            form.setData('file', null);
            form.clearErrors('file');

            return;
        }

        const allowed = acceptedFileTypes.split(',').map((type) => type.trim().toLowerCase());
        const extension = fileExtension(next.name);

        if (extension && allowed.length > 0 && !allowed.includes(extension)) {
            form.setData('file', next);
            form.setError('file', t('documents.file_type_invalid'));

            return;
        }

        if (next.size > maxUploadSizeKb * 1024) {
            form.setData('file', next);
            form.setError('file', t('documents.file_too_large', { size: formatMaxSize(maxUploadSizeKb) }));

            return;
        }

        form.clearErrors('file');
        form.setData('file', next);
    }

    function persistDraft(nextStep: Step = step) {
        writeDraft({
            title: form.data.title,
            document_type: form.data.document_type,
            confidentiality: form.data.confidentiality,
            abstract: form.data.abstract,
            external_author: form.data.external_author,
            enacting_clause: form.data.enacting_clause,
            explanatory_note: form.data.explanatory_note,
            committee_id: form.data.committee_id,
            reference_number: form.data.reference_number,
            change_summary: form.data.change_summary,
            step: nextStep,
        });
    }

    function saveDraft() {
        persistDraft();
        setDraftSaved(true);
    }

    function resetFilingForm() {
        form.reset();
        form.clearErrors();
        form.setData('file', null);
        setStep(0);
        setDraftSaved(false);
    }

    function handleOpenChange(next: boolean) {
        if (form.processing) {
            return;
        }

        if (!next) {
            if (skipDraftPersist.current) {
                skipDraftPersist.current = false;
            } else {
                persistDraft();
            }
        }

        onOpenChange(next);
    }

    function goBack() {
        if (step === 0) {
            return;
        }

        setDraftSaved(false);
        setStep((current) => (current - 1) as Step);
    }

    function goNext() {
        if (!validateStep(step)) {
            return;
        }

        setDraftSaved(false);
        setStep((current) => (current + 1) as Step);
    }

    function validateStep(current: Step): boolean {
        if (current === 0) {
            if (!form.data.document_type) {
                form.setError('document_type', t('documents.type_required'));

                return false;
            }

            if (!form.data.confidentiality) {
                form.setError('confidentiality', t('documents.confidentiality_required'));

                return false;
            }

            return true;
        }

        if (current === 1) {
            let ok = true;

            if (!form.data.title.trim()) {
                form.setError('title', t('documents.title_required'));
                ok = false;
            }

            if (!form.data.external_author.trim()) {
                form.setError('external_author', t('documents.author_required'));
                ok = false;
            }

            if (isMeasure && !form.data.enacting_clause.trim()) {
                form.setError('enacting_clause', t('documents.enacting_clause_required'));
                ok = false;
            }

            if (isOrdinance && !form.data.explanatory_note.trim()) {
                form.setError('explanatory_note', t('documents.explanatory_note_required'));
                ok = false;
            }

            return ok;
        }

        if (current === 2) {
            if (!form.data.file) {
                form.setError('file', t('documents.file_required'));

                return false;
            }

            if (form.errors.file) {
                return false;
            }

            return true;
        }

        return true;
    }

    function submitFiling() {
        if (!form.data.file) {
            form.setError('file', t('documents.file_required'));
            setStep(2);

            return;
        }

        form.transform((data) => ({
            ...data,
            external_author: data.external_author.trim(),
        }));

        form.post('/documents', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                clearDraft();
                skipDraftPersist.current = true;
                resetFilingForm();
                onOpenChange(false);
            },
            onError: (errors) => {
                setStep(firstStepWithError(errors));
            },
        });
    }

    function handleSubmit(event: FormEvent) {
        event.preventDefault();

        if (!lastStep) {
            goNext();

            return;
        }

        submitFiling();
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent
                size="lg"
                title={t('documents.create')}
                description={t('documents.create_subtitle')}
                bodyClassName="px-5 py-5"
                headerExtra={
                    <Stepper
                        current={step}
                        labels={STEPS.map((key) => t(`documents.step_${key}`))}
                        ariaLabel={t('documents.steps')}
                        onSelect={(next) => {
                            if (next < step) {
                                setDraftSaved(false);
                                setStep(next);
                            }
                        }}
                    />
                }
                footer={
                    <div className="flex shrink-0 flex-col gap-3 border-t border-line px-5 py-3.5">
                        {form.processing && form.progress ? (
                            <div className="space-y-1.5">
                                <p className="text-xs text-ink-muted">
                                    {t('documents.uploading', { percent: form.progress.percentage ?? 0 })}
                                </p>
                                <Progress
                                    value={form.progress.percentage ?? 0}
                                    aria-label={t('documents.uploading', {
                                        percent: form.progress.percentage ?? 0,
                                    })}
                                />
                            </div>
                        ) : null}
                        <div className="flex items-center justify-between gap-3">
                            <Button
                                type="button"
                                variant="ghost"
                                disabled={step === 0 || form.processing}
                                onClick={goBack}
                                className="text-ink-muted"
                            >
                                <ChevronLeft aria-hidden="true" strokeWidth={1.75} />
                                {t('documents.back')}
                            </Button>
                            <div className="flex flex-wrap items-center justify-end gap-2">
                                <Button type="button" variant="secondary" disabled={form.processing} onClick={saveDraft}>
                                    {t('documents.save_draft')}
                                </Button>
                                <Button
                                    type="submit"
                                    form="submit-document"
                                    variant="plate"
                                    disabled={form.processing || Boolean(form.data.file && form.errors.file)}
                                >
                                    {form.processing
                                        ? t('documents.submitting')
                                        : lastStep
                                          ? t('documents.create')
                                          : t('documents.next_step')}
                                    {form.processing || lastStep ? null : <ChevronRight aria-hidden="true" strokeWidth={1.75} />}
                                </Button>
                            </div>
                        </div>
                    </div>
                }
            >
                <form id="submit-document" onSubmit={handleSubmit} className="space-y-5">
                    {draftSaved ? <Notice tone="info">{t('documents.draft_saved')}</Notice> : null}

                    {step === 0 ? (
                        <TypeStep
                            documentTypes={documentTypes}
                            confidentialityLevels={confidentialityLevels}
                            documentType={form.data.document_type}
                            confidentiality={form.data.confidentiality}
                            typeError={form.errors.document_type}
                            confidentialityError={form.errors.confidentiality}
                            onTypeChange={changeDocumentType}
                            onConfidentialityChange={(value) => {
                                form.setData('confidentiality', value);
                                form.clearErrors('confidentiality');
                            }}
                        />
                    ) : null}

                    {step === 1 ? (
                        <DetailsStep
                            form={form}
                            isMeasure={isMeasure}
                            isOrdinance={isOrdinance}
                            selectedType={selectedType}
                            committees={committees}
                        />
                    ) : null}

                    {step === 2 ? (
                        <UploadStep
                            file={form.data.file}
                            error={form.errors.file}
                            hint={fileHint}
                            describedBy={fileDescribedBy}
                            accept={acceptedFileTypes}
                            processing={form.processing}
                            onFileChange={takeFile}
                        />
                    ) : null}

                    {step === 3 ? (
                        <ReviewStep
                            title={form.data.title}
                            typeLabel={selectedType?.label ?? form.data.document_type}
                            reference={form.data.reference_number}
                            confidentialityLabel={
                                selectedConfidentiality
                                    ? t(`documents.confidentiality_${selectedConfidentiality.value}`)
                                    : form.data.confidentiality
                            }
                            committee={selectedCommittee?.name ?? null}
                            abstract={form.data.abstract}
                            author={form.data.external_author}
                            enactingClause={form.data.enacting_clause}
                            explanatoryNote={form.data.explanatory_note}
                            isMeasure={isMeasure}
                            isOrdinance={isOrdinance}
                            file={form.data.file}
                        />
                    ) : null}
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Stepper({
    current,
    labels,
    ariaLabel,
    onSelect,
}: {
    current: Step;
    labels: string[];
    ariaLabel: string;
    onSelect: (step: Step) => void;
}) {
    return (
        <nav aria-label={ariaLabel} className="shrink-0 border-b border-line px-5 py-4">
            <ol className="grid grid-cols-4">
                {labels.map((label, index) => {
                    const state = index < current ? 'done' : index === current ? 'current' : 'upcoming';
                    const clickable = index < current;

                    return (
                        <li key={label} className="relative flex flex-col items-center gap-1.5">
                            {index > 0 ? (
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'absolute top-4 right-1/2 left-[-50%] h-px',
                                        index <= current ? 'bg-floor-plate' : 'bg-line-strong',
                                    )}
                                />
                            ) : null}
                            <button
                                type="button"
                                disabled={!clickable}
                                onClick={() => onSelect(index as Step)}
                                aria-current={state === 'current' ? 'step' : undefined}
                                className={cn(
                                    'relative z-10 flex size-8 items-center justify-center rounded-full border text-sm font-semibold',
                                    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-focus)]',
                                    state === 'upcoming'
                                        ? 'border-line-strong bg-surface text-ink-faint'
                                        : 'border-floor-plate bg-floor-plate text-floor-ink',
                                    clickable && 'cursor-pointer',
                                    !clickable && 'cursor-default',
                                )}
                            >
                                {index + 1}
                            </button>
                            <span
                                className={cn(
                                    'text-[0.65rem] font-semibold tracking-[0.12em] uppercase',
                                    state === 'upcoming' ? 'text-ink-faint' : 'text-ink',
                                )}
                            >
                                {label}
                            </span>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

function TypeStep({
    documentTypes,
    confidentialityLevels,
    documentType,
    confidentiality,
    typeError,
    confidentialityError,
    onTypeChange,
    onConfidentialityChange,
}: {
    documentTypes: DocumentTypeOption[];
    confidentialityLevels: ConfidentialityOption[];
    documentType: string;
    confidentiality: string;
    typeError?: string;
    confidentialityError?: string;
    onTypeChange: (value: string) => void;
    onConfidentialityChange: (value: string) => void;
}) {
    const { t } = useTranslations();

    return (
        <div className="space-y-5">
            <Field
                id="document_type"
                label={t('documents.document_type')}
                hint={t('documents.type_hint')}
                error={typeError}
                required
            >
                <SearchableSelect
                    id="document_type"
                    value={documentType}
                    onValueChange={onTypeChange}
                    items={documentTypes.map((type) => ({
                        value: type.value,
                        label: type.label,
                        keywords: type.tag,
                    }))}
                    placeholder={t('documents.type_placeholder')}
                    searchPlaceholder={t('documents.type_search')}
                    emptyLabel={t('documents.type_empty')}
                    aria-invalid={Boolean(typeError)}
                    aria-label={t('documents.document_type')}
                />
            </Field>

            <fieldset className="space-y-2">
                <legend className="text-sm font-medium text-ink">{t('documents.security_classification')}</legend>
                <div className="flex flex-col gap-2">
                    {confidentialityLevels.map((level) => {
                        const selected = confidentiality === level.value;

                        return (
                            <label
                                key={level.value}
                                className={cn(
                                    'flex cursor-pointer items-start gap-3 rounded-[var(--radius-md)] border px-3 py-2.5',
                                    'transition-[background-color,border-color] duration-[var(--duration-fast)]',
                                    'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-[var(--color-focus)]',
                                    selected
                                        ? 'border-floor-plate bg-accent-soft'
                                        : 'border-line-strong bg-surface hover:bg-canvas-sunk',
                                )}
                            >
                                <input
                                    type="radio"
                                    name="confidentiality"
                                    value={level.value}
                                    checked={selected}
                                    onChange={() => onConfidentialityChange(level.value)}
                                    className="sr-only"
                                />
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border',
                                        selected ? 'border-floor-plate bg-floor-plate' : 'border-line-control bg-surface',
                                    )}
                                />
                                <span className="min-w-0 text-sm font-semibold text-ink">
                                    {t(`documents.confidentiality_${level.value}`)}
                                    <span className="mt-0.5 block text-xs font-normal text-ink-muted">
                                        {t(`documents.confidentiality_${level.value}_card`)}
                                    </span>
                                </span>
                            </label>
                        );
                    })}
                </div>
                {confidentialityError ? <p className="text-xs font-medium text-critical">{confidentialityError}</p> : null}
            </fieldset>

            <Notice tone="info">{t('documents.classification_info')}</Notice>
        </div>
    );
}

function DetailsStep({
    form,
    isMeasure,
    isOrdinance,
    selectedType,
    committees,
}: {
    form: ReturnType<
        typeof useForm<{
            title: string;
            document_type: string;
            confidentiality: string;
            abstract: string;
            external_author: string;
            enacting_clause: string;
            explanatory_note: string;
            committee_id: string;
            reference_number: string;
            change_summary: string;
            file: File | null;
        }>
    >;
    isMeasure: boolean;
    isOrdinance: boolean;
    selectedType?: DocumentTypeOption;
    committees: CommitteeOption[];
}) {
    const { t } = useTranslations();
    const referenceHint = t('documents.reference_assigned_hint');

    return (
        <div className="space-y-4">
            <Field
                id="title"
                label={t('documents.title_label')}
                hint={t('documents.title_hint')}
                error={form.errors.title}
                required
            >
                <Input
                    {...fieldAria('title', { hint: t('documents.title_hint'), error: form.errors.title })}
                    value={form.data.title}
                    onChange={(event) => form.setData('title', event.target.value)}
                    required
                    autoComplete="off"
                    placeholder={t('documents.title_placeholder')}
                />
            </Field>

            <Field
                id="external_author"
                label={t('documents.author')}
                hint={t('documents.author_hint')}
                error={form.errors.external_author}
                required
            >
                <Input
                    {...fieldAria('external_author', {
                        hint: t('documents.author_hint'),
                        error: form.errors.external_author,
                    })}
                    value={form.data.external_author}
                    onChange={(event) => form.setData('external_author', event.target.value)}
                    required
                    autoComplete="off"
                    placeholder={t('documents.author_placeholder')}
                />
            </Field>

            <Field
                id="reference_number"
                label={t('documents.reference')}
                hint={referenceHint}
                error={form.errors.reference_number}
                required
            >
                <Input
                    {...fieldAria('reference_number', {
                        hint: referenceHint,
                        error: form.errors.reference_number,
                    })}
                    value={form.data.reference_number}
                    readOnly
                    aria-readonly="true"
                    autoComplete="off"
                    className="bg-canvas-sunk font-mono"
                    placeholder={selectedType?.next_reference ?? t('documents.reference_placeholder')}
                />
            </Field>

            {isMeasure ? (
                <>
                    <Field
                        id="enacting_clause"
                        label={t('documents.enacting_clause')}
                        hint={t('documents.enacting_clause_hint')}
                        error={form.errors.enacting_clause}
                        required
                    >
                        <Textarea
                            {...fieldAria('enacting_clause', {
                                hint: t('documents.enacting_clause_hint'),
                                error: form.errors.enacting_clause,
                            })}
                            value={form.data.enacting_clause}
                            onChange={(event) => form.setData('enacting_clause', event.target.value)}
                            rows={3}
                            required
                            placeholder={t('documents.enacting_clause_placeholder')}
                        />
                    </Field>
                    {isOrdinance ? (
                        <Field
                            id="explanatory_note"
                            label={t('documents.explanatory_note')}
                            hint={t('documents.explanatory_note_hint')}
                            error={form.errors.explanatory_note}
                            required
                        >
                            <Textarea
                                {...fieldAria('explanatory_note', {
                                    hint: t('documents.explanatory_note_hint'),
                                    error: form.errors.explanatory_note,
                                })}
                                value={form.data.explanatory_note}
                                onChange={(event) => form.setData('explanatory_note', event.target.value)}
                                rows={4}
                                required
                                placeholder={t('documents.explanatory_note_placeholder')}
                            />
                        </Field>
                    ) : null}
                </>
            ) : null}

            <Field
                id="committee_id"
                label={t('documents.committee')}
                hint={t('documents.committee_hint')}
                error={form.errors.committee_id}
            >
                <SimpleSelect
                    id="committee_id"
                    value={form.data.committee_id}
                    onValueChange={(value) => form.setData('committee_id', value)}
                    noneLabel={t('documents.no_committee')}
                    items={committees.map((committee) => ({
                        value: committee.id,
                        label: committee.name,
                    }))}
                />
            </Field>

            <Field id="abstract" label={t('documents.abstract')} hint={t('documents.abstract_hint')} error={form.errors.abstract}>
                <Textarea
                    {...fieldAria('abstract', {
                        hint: t('documents.abstract_hint'),
                        error: form.errors.abstract,
                    })}
                    value={form.data.abstract}
                    onChange={(event) => form.setData('abstract', event.target.value)}
                    rows={4}
                    placeholder={t('documents.abstract_placeholder')}
                />
            </Field>
        </div>
    );
}

function UploadStep({
    file,
    error,
    hint,
    describedBy,
    accept,
    processing,
    onFileChange,
}: {
    file: File | null;
    error?: string;
    hint: string;
    describedBy?: string;
    accept: string;
    processing: boolean;
    onFileChange: (file: File | null) => void;
}) {
    const { t } = useTranslations();

    return (
        <div className="space-y-4">
            <div>
                <h2 className="text-sm font-medium text-ink">{t('documents.form_source')}</h2>
                <p className="mt-1 text-sm text-ink-muted">{t('documents.form_source_hint')}</p>
            </div>
            <Field id="file" label={t('documents.file')} hint={file ? hint : undefined} error={error} required>
                <FileDrop
                    id="file"
                    file={file}
                    onFileChange={onFileChange}
                    accept={accept}
                    disabled={processing}
                    invalid={Boolean(error)}
                    dropLabel={t('documents.file_drop')}
                    browseLabel={t('documents.file_browse')}
                    replaceLabel={t('documents.file_replace')}
                    removeLabel={t('documents.file_remove')}
                    emptyHint={hint}
                    aria-describedby={describedBy}
                />
            </Field>
        </div>
    );
}

function ReviewStep({
    title,
    typeLabel,
    reference,
    confidentialityLabel,
    committee,
    abstract,
    author,
    enactingClause,
    explanatoryNote,
    isMeasure,
    isOrdinance,
    file,
}: {
    title: string;
    typeLabel: string;
    reference: string;
    confidentialityLabel: string;
    committee: string | null;
    abstract: string;
    author: string;
    enactingClause: string;
    explanatoryNote: string;
    isMeasure: boolean;
    isOrdinance: boolean;
    file: File | null;
}) {
    const { t } = useTranslations();

    return (
        <div className="space-y-4">
            <Notice tone="info">{t('documents.create_notice')}</Notice>
            <DefinitionList>
                <Definition label={t('documents.document_type')}>{typeLabel}</Definition>
                <Definition label={t('documents.security_classification')}>{confidentialityLabel}</Definition>
                <Definition label={t('documents.title_label')}>{title || t('documents.not_provided')}</Definition>
                <Definition label={t('documents.author')}>{author || t('documents.not_provided')}</Definition>
                <Definition label={t('documents.reference')} numeric>
                    {reference || t('documents.no_reference')}
                </Definition>
                <Definition label={t('documents.committee')}>{committee ?? t('documents.no_committee')}</Definition>
                <Definition label={t('documents.abstract')}>{abstract || t('documents.not_provided')}</Definition>
                {isMeasure ? (
                    <>
                        <Definition label={t('documents.enacting_clause')}>
                            {enactingClause || t('documents.not_provided')}
                        </Definition>
                    </>
                ) : null}
                {isOrdinance ? (
                    <Definition label={t('documents.explanatory_note')}>
                        {explanatoryNote || t('documents.not_provided')}
                    </Definition>
                ) : null}
                <Definition label={t('documents.file')}>{file ? file.name : t('documents.no_file')}</Definition>
            </DefinitionList>
        </div>
    );
}

function firstStepWithError(errors: Record<string, string>): Step {
    if (errors.document_type || errors.confidentiality) {
        return 0;
    }

    if (
        errors.title ||
        errors.external_author ||
        errors.reference_number ||
        errors.enacting_clause ||
        errors.explanatory_note ||
        errors.committee_id ||
        errors.abstract
    ) {
        return 1;
    }

    if (errors.file) {
        return 2;
    }

    return 3;
}

function readDraft(): DraftPayload | null {
    try {
        const raw = sessionStorage.getItem(DRAFT_KEY);

        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as DraftPayload;

        if (typeof parsed !== 'object' || parsed === null) {
            return null;
        }

        return parsed;
    } catch {
        return null;
    }
}

function writeDraft(draft: DraftPayload): void {
    try {
        sessionStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
    } catch {
        // Private mode or quota — filing can still continue without a draft.
    }
}

function clearDraft(): void {
    try {
        sessionStorage.removeItem(DRAFT_KEY);
    } catch {
        // Ignore storage failures on clear.
    }
}

function fileExtension(name: string): string {
    const dot = name.lastIndexOf('.');

    return dot >= 0 ? name.slice(dot).toLowerCase() : '';
}

function formatMaxSize(kb: number): string {
    if (kb >= 1024) {
        const mb = kb / 1024;

        return `${Number.isInteger(mb) ? mb : mb.toFixed(1)} MB`;
    }

    return `${kb} KB`;
}

function isLegislativeMeasure(type: string): boolean {
    return ['proposed-ordinance', 'proposed-resolution', 'ordinance', 'resolution'].includes(type);
}

function isOrdinanceMeasure(type: string): boolean {
    return type === 'proposed-ordinance' || type === 'ordinance';
}
