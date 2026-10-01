import { BrandMark } from '@/components/branding/BrandMark';
import { Button } from '@/components/ui/button';
import { Field, fieldAria } from '@/components/ui/field';
import { FileDrop } from '@/components/ui/file-drop';
import { IndexHeader } from '@/components/ui/index-header';
import { Input } from '@/components/ui/input';
import { Notice } from '@/components/ui/notice';
import { Panel, PanelFoot, PanelSection } from '@/components/ui/panel';
import AppLayout from '@/layouts/AppLayout';
import { ACCENT_SWATCHES, PLATE_PATTERNS, PLATE_SWATCHES, type BrandSwatch, type PlatePattern } from '@/lib/brandSwatches';
import { useTranslations } from '@/lib/i18n';
import { knockoutPaper } from '@/lib/knockoutPaper';
import { cn } from '@/lib/utils';
import { Link, router, useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { CSSProperties, FormEvent, useEffect, useMemo, useState } from 'react';

type BrandingForm = {
    name: string;
    short_name: string;
    locality: string;
    accent: string;
    plate: string;
    plate_pattern: PlatePattern;
    logo_url: string | null;
};

type Props = {
    branding: BrandingForm;
    defaults: {
        name: string;
        short_name: string;
        locality: string;
        accent: string;
        plate: string;
        plate_pattern: PlatePattern;
    };
    brand_css: string;
    can: { update: boolean };
};

/**
 * The server prints brand CSS and the plate pattern into the first page load
 * only. After a save the page comes back over Inertia, so re-apply both here
 * or the new colours would wait for a hard refresh.
 */
function useAppliedBrand(css: string, pattern: PlatePattern) {
    useEffect(() => {
        let style = document.getElementById('sentria-brand');

        if (css === '') {
            style?.remove();
        } else {
            if (!style) {
                style = document.createElement('style');
                style.id = 'sentria-brand';
                document.head.appendChild(style);
            }

            style.textContent = css;
        }

        const root = document.documentElement;

        if (pattern === 'authored') {
            delete root.dataset.platePattern;
        } else {
            root.dataset.platePattern = pattern;
        }
    }, [css, pattern]);
}

const PLATE_MIN_CONTRAST = 4.5;

function normalizeHex(value: string): string | null {
    let hex = value.trim().toUpperCase();

    if (hex === '') {
        return null;
    }

    if (!hex.startsWith('#')) {
        hex = `#${hex}`;
    }

    if (/^#[0-9A-F]{3}$/.test(hex)) {
        hex = `#${hex[1]}${hex[1]}${hex[2]}${hex[2]}${hex[3]}${hex[3]}`;
    }

    return /^#[0-9A-F]{6}$/.test(hex) ? hex : null;
}

function linearChannel(value: number): number {
    const channel = value / 255;

    return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
}

function relativeLuminance(hex: string): number {
    const digits = hex.slice(1);
    const r = Number.parseInt(digits.slice(0, 2), 16);
    const g = Number.parseInt(digits.slice(2, 4), 16);
    const b = Number.parseInt(digits.slice(4, 6), 16);

    return 0.2126 * linearChannel(r) + 0.7152 * linearChannel(g) + 0.0722 * linearChannel(b);
}

function contrastRatio(a: string, b: string): number {
    const left = relativeLuminance(a);
    const right = relativeLuminance(b);
    const lighter = Math.max(left, right);
    const darker = Math.min(left, right);

    return (lighter + 0.05) / (darker + 0.05);
}

function onColor(hex: string): string {
    return contrastRatio(hex, '#FFFFFF') >= contrastRatio(hex, '#0F1419') ? '#FFFFFF' : '#0F1419';
}

/** Hues that read as the live/red signal: in session, voting open, quorum not met. */
function looksLikeLive(hex: string): boolean {
    const digits = hex.slice(1);
    const r = Number.parseInt(digits.slice(0, 2), 16) / 255;
    const g = Number.parseInt(digits.slice(2, 4), 16) / 255;
    const b = Number.parseInt(digits.slice(4, 6), 16) / 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const delta = max - min;

    if (max !== r || delta < 0.25) {
        return false;
    }

    const hue = (((((g - b) / delta) % 6) + 6) % 6) * 60;

    return hue <= 15 || hue >= 335;
}

function ColorField({
    id,
    label,
    hint,
    pickerLabel,
    swatchesLabel,
    value,
    fallback,
    swatches,
    error,
    disabled,
    onChange,
}: {
    id: string;
    label: string;
    hint: string;
    pickerLabel: string;
    swatchesLabel: string;
    value: string;
    fallback: string;
    swatches: BrandSwatch[];
    error?: string;
    disabled: boolean;
    onChange: (value: string) => void;
}) {
    const { t } = useTranslations();
    const normalized = normalizeHex(value);

    return (
        <div className="space-y-3">
            <fieldset>
                <legend className="text-sm font-medium text-ink">{swatchesLabel}</legend>
                <div className="mt-2 grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                    {swatches.map((swatch) => {
                        const selected = normalized === swatch.hex;
                        const name = t(`settings.branding.swatch.${swatch.id}`);

                        return (
                            <button
                                key={swatch.id}
                                type="button"
                                aria-pressed={selected}
                                disabled={disabled}
                                onClick={() => onChange(swatch.hex)}
                                className={cn(
                                    'flex min-w-0 items-center gap-2 rounded-[var(--radius-sm)] border px-2 py-1.5 text-left text-sm transition-colors duration-[var(--duration-fast)] disabled:cursor-not-allowed disabled:opacity-60',
                                    selected
                                        ? 'border-accent bg-accent-soft text-ink'
                                        : 'border-line bg-surface text-ink-muted hover:border-line-strong hover:text-ink',
                                )}
                            >
                                <span
                                    aria-hidden="true"
                                    className="flex size-5 shrink-0 items-center justify-center rounded-[var(--radius-xs)] border border-black/10"
                                    style={{ backgroundColor: swatch.hex, color: onColor(swatch.hex) }}
                                >
                                    {selected ? <Check className="size-3.5" strokeWidth={2.5} /> : null}
                                </span>
                                <span className="min-w-0 truncate">{name}</span>
                            </button>
                        );
                    })}
                </div>
            </fieldset>

            <Field id={id} label={label} hint={hint} error={error} required>
                <div className="flex items-center gap-2">
                    <input
                        type="color"
                        value={normalized ?? fallback}
                        onChange={(event) => onChange(event.target.value.toUpperCase())}
                        disabled={disabled}
                        aria-label={pickerLabel}
                        className="size-9 shrink-0 cursor-pointer rounded-[var(--radius-md)] border border-line-control bg-surface p-0.5 disabled:cursor-not-allowed"
                    />
                    <Input
                        {...fieldAria(id, { hint, error })}
                        value={value}
                        onChange={(event) => onChange(event.target.value)}
                        disabled={disabled}
                        spellCheck={false}
                        className="font-mono uppercase"
                        required
                    />
                </div>
            </Field>
        </div>
    );
}

export default function BrandingSettings({ branding, brand_css, can }: Props) {
    const { t } = useTranslations();
    useAppliedBrand(brand_css, branding.plate_pattern);
    const form = useForm({
        name: branding.name,
        short_name: branding.short_name,
        locality: branding.locality,
        accent: branding.accent,
        plate: branding.plate,
        plate_pattern: branding.plate_pattern,
        logo: null as File | null,
        remove_logo: false,
    });
    const [objectUrl, setObjectUrl] = useState<string | null>(null);

    useEffect(() => {
        if (!form.data.logo) {
            setObjectUrl(null);

            return;
        }

        let cancelled = false;
        let generated: string | null = null;

        void knockoutPaper(form.data.logo).then((url) => {
            generated = url;

            if (cancelled) {
                URL.revokeObjectURL(url);

                return;
            }

            setObjectUrl(url);
        });

        return () => {
            cancelled = true;

            if (generated) {
                URL.revokeObjectURL(generated);
            }
        };
    }, [form.data.logo]);

    const previewAccent = normalizeHex(form.data.accent) ?? branding.accent;
    const previewLogo = form.data.remove_logo ? null : (objectUrl ?? branding.logo_url);
    const lightAccent = useMemo(() => contrastRatio(previewAccent, '#FFFFFF') < 4.5, [previewAccent]);
    const liveAccent = useMemo(() => looksLikeLive(previewAccent), [previewAccent]);
    const typedPlate = normalizeHex(form.data.plate);
    const plateTooLight = typedPlate !== null && contrastRatio(typedPlate, '#FFFFFF') < PLATE_MIN_CONTRAST;
    const previewPlate = typedPlate && !plateTooLight ? typedPlate : branding.plate;
    const previewPlateStyle = { '--plate-preview': previewPlate } as CSSProperties;

    function submit(event: FormEvent) {
        event.preventDefault();
        form.put('/settings/branding', {
            forceFormData: true,
            onSuccess: () => {
                form.setData('logo', null);
                setObjectUrl(null);
            },
        });
    }

    function resetDefaults() {
        router.post('/settings/branding/reset');
    }

    return (
        <AppLayout title={t('settings.branding.title')}>
            <div className="mx-auto flex max-w-3xl flex-col gap-5">
                <IndexHeader
                    eyebrow={t('index.eyebrow.administration')}
                    title={t('settings.branding.title')}
                    description={t('settings.branding.intro')}
                    actions={
                        <Button variant="secondary" asChild>
                            <Link href="/settings">{t('settings.branding.back')}</Link>
                        </Button>
                    }
                />

                <Panel>
                    <form onSubmit={submit}>
                        <PanelSection className="space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold text-ink">{t('settings.branding.identity')}</h2>
                                <p className="mt-1 text-sm text-ink-muted">{t('settings.branding.identity_hint')}</p>
                            </div>

                            <Field
                                id="logo"
                                label={t('settings.branding.logo')}
                                hint={t('settings.branding.logo_hint')}
                                error={form.errors.logo}
                            >
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start">
                                    <div className="flex size-16 shrink-0 items-center justify-center rounded-[var(--radius-md)] border border-line bg-canvas-sunk bg-[image:linear-gradient(45deg,var(--color-line)_25%,transparent_25%,transparent_75%,var(--color-line)_75%),linear-gradient(45deg,var(--color-line)_25%,transparent_25%,transparent_75%,var(--color-line)_75%)] bg-[size:8px_8px] bg-[position:0_0,4px_4px]">
                                        <BrandMark
                                            className="size-16"
                                            logoUrl={previewLogo}
                                            shortName={form.data.short_name}
                                            name={form.data.name}
                                        />
                                    </div>
                                    <div className="min-w-0 flex-1 space-y-2">
                                        <FileDrop
                                            id="logo"
                                            file={form.data.logo}
                                            onFileChange={(file) => {
                                                form.setData((current) => ({
                                                    ...current,
                                                    logo: file,
                                                    remove_logo: false,
                                                }));
                                            }}
                                            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                                            disabled={!can.update || form.processing}
                                            invalid={Boolean(form.errors.logo)}
                                            dropLabel={t('settings.branding.logo_drop')}
                                            browseLabel={t('settings.branding.logo_browse')}
                                            replaceLabel={t('settings.branding.logo_replace')}
                                            removeLabel={t('settings.branding.logo_remove_selected')}
                                            emptyHint={t('settings.branding.logo_empty_hint')}
                                            aria-describedby={
                                                fieldAria('logo', {
                                                    hint: t('settings.branding.logo_hint'),
                                                    error: form.errors.logo,
                                                })['aria-describedby']
                                            }
                                        />
                                        {branding.logo_url && !form.data.logo ? (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                disabled={!can.update || form.processing}
                                                onClick={() => {
                                                    form.setData((current) => ({
                                                        ...current,
                                                        logo: null,
                                                        remove_logo: true,
                                                    }));
                                                }}
                                            >
                                                {t('settings.branding.logo_remove')}
                                            </Button>
                                        ) : null}
                                    </div>
                                </div>
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="name"
                                    label={t('settings.branding.name')}
                                    error={form.errors.name}
                                    required
                                    className="sm:col-span-2"
                                >
                                    <Input
                                        {...fieldAria('name', { error: form.errors.name })}
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        disabled={!can.update}
                                        required
                                    />
                                </Field>
                                <Field
                                    id="short_name"
                                    label={t('settings.branding.short_name')}
                                    hint={t('settings.branding.short_name_hint')}
                                    error={form.errors.short_name}
                                    required
                                >
                                    <Input
                                        {...fieldAria('short_name', {
                                            hint: t('settings.branding.short_name_hint'),
                                            error: form.errors.short_name,
                                        })}
                                        value={form.data.short_name}
                                        onChange={(event) => form.setData('short_name', event.target.value)}
                                        disabled={!can.update}
                                        required
                                    />
                                </Field>
                                <Field
                                    id="locality"
                                    label={t('settings.branding.locality')}
                                    error={form.errors.locality}
                                    required
                                >
                                    <Input
                                        {...fieldAria('locality', { error: form.errors.locality })}
                                        value={form.data.locality}
                                        onChange={(event) => form.setData('locality', event.target.value)}
                                        disabled={!can.update}
                                        required
                                    />
                                </Field>
                            </div>
                        </PanelSection>

                        <PanelSection className="space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold text-ink">{t('settings.branding.accent')}</h2>
                                <p className="mt-1 text-sm text-ink-muted">{t('settings.branding.accent_hint')}</p>
                            </div>

                            <ColorField
                                id="accent"
                                label={t('settings.branding.accent_value')}
                                hint={t('settings.branding.accent_value_hint')}
                                pickerLabel={t('settings.branding.accent_picker')}
                                swatchesLabel={t('settings.branding.swatches')}
                                value={form.data.accent}
                                fallback="#0038A8"
                                swatches={ACCENT_SWATCHES}
                                error={form.errors.accent}
                                disabled={!can.update}
                                onChange={(value) => form.setData('accent', value)}
                            />

                            {lightAccent ? <Notice tone="caution">{t('settings.branding.accent_contrast')}</Notice> : null}
                            {liveAccent ? <Notice tone="caution">{t('settings.branding.accent_live')}</Notice> : null}

                            <div className="rounded-[var(--radius-md)] border border-line bg-canvas-sunk px-4 py-3">
                                <p className="text-2xs font-semibold tracking-[0.06em] text-ink-faint uppercase">
                                    {t('settings.branding.preview')}
                                </p>
                                <div className="mt-3 flex flex-wrap items-center gap-4">
                                    <div className="flex items-center gap-3">
                                        <span
                                            aria-hidden="true"
                                            className={cn(
                                                'flex size-10 shrink-0 items-center justify-center font-mono text-base font-semibold',
                                                previewLogo ? 'bg-transparent' : 'overflow-hidden rounded-[var(--radius-md)]',
                                            )}
                                            style={
                                                previewLogo
                                                    ? undefined
                                                    : { backgroundColor: previewAccent, color: onColor(previewAccent) }
                                            }
                                        >
                                            {previewLogo ? (
                                                <img src={previewLogo} alt="" className="size-full object-contain p-0.5" />
                                            ) : (
                                                (Array.from(
                                                    (form.data.short_name || form.data.name || 'S').trim(),
                                                )[0]?.toUpperCase() ?? 'S')
                                            )}
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block truncate text-md font-semibold text-ink">{t('app.name')}</span>
                                            <span className="block truncate text-xs text-ink-faint">{form.data.short_name}</span>
                                        </span>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="primary"
                                        className="pointer-events-none"
                                        style={{
                                            backgroundColor: previewAccent,
                                            borderColor: previewAccent,
                                            color: onColor(previewAccent),
                                        }}
                                        tabIndex={-1}
                                    >
                                        {t('settings.branding.preview_action')}
                                    </Button>
                                </div>
                            </div>
                        </PanelSection>

                        <PanelSection className="space-y-4">
                            <div>
                                <h2 className="text-sm font-semibold text-ink">{t('settings.branding.plate')}</h2>
                                <p className="mt-1 text-sm text-ink-muted">{t('settings.branding.plate_hint')}</p>
                            </div>

                            <ColorField
                                id="plate"
                                label={t('settings.branding.accent_value')}
                                hint={t('settings.branding.plate_value_hint')}
                                pickerLabel={t('settings.branding.plate_picker')}
                                swatchesLabel={t('settings.branding.swatches')}
                                value={form.data.plate}
                                fallback="#132042"
                                swatches={PLATE_SWATCHES}
                                error={form.errors.plate}
                                disabled={!can.update}
                                onChange={(value) => form.setData('plate', value)}
                            />

                            {plateTooLight ? <Notice tone="danger">{t('settings.branding.plate_too_light')}</Notice> : null}

                            <fieldset>
                                <legend className="text-sm font-medium text-ink">{t('settings.branding.pattern')}</legend>
                                <p className="mt-1 text-sm text-ink-muted">{t('settings.branding.pattern_hint')}</p>
                                <div className="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    {PLATE_PATTERNS.map((pattern) => {
                                        const selected = form.data.plate_pattern === pattern;

                                        return (
                                            <button
                                                key={pattern}
                                                type="button"
                                                aria-pressed={selected}
                                                disabled={!can.update}
                                                onClick={() => form.setData('plate_pattern', pattern)}
                                                className={cn(
                                                    'flex flex-col gap-1.5 rounded-[var(--radius-sm)] border p-1.5 text-left text-sm transition-colors duration-[var(--duration-fast)] disabled:cursor-not-allowed disabled:opacity-60',
                                                    selected
                                                        ? 'border-accent bg-accent-soft text-ink'
                                                        : 'border-line bg-surface text-ink-muted hover:border-line-strong hover:text-ink',
                                                )}
                                            >
                                                <span
                                                    aria-hidden="true"
                                                    data-plate-pattern={pattern}
                                                    className="bg-plate-preview relative flex h-12 items-end justify-end rounded-[var(--radius-xs)] p-1 text-floor-ink"
                                                    style={previewPlateStyle}
                                                >
                                                    {selected ? <Check className="size-3.5" strokeWidth={2.5} /> : null}
                                                </span>
                                                <span className="truncate px-0.5">
                                                    {t(`settings.branding.pattern.${pattern}`)}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                                {form.errors.plate_pattern ? (
                                    <p className="mt-1.5 text-sm text-critical">{form.errors.plate_pattern}</p>
                                ) : null}
                            </fieldset>

                            <div className="rounded-[var(--radius-md)] border border-line bg-canvas-sunk px-4 py-3">
                                <p className="text-2xs font-semibold tracking-[0.06em] text-ink-faint uppercase">
                                    {t('settings.branding.preview')}
                                </p>
                                <div
                                    data-plate-pattern={form.data.plate_pattern}
                                    className="bg-plate-preview mt-3 overflow-hidden rounded-[var(--radius-md)] px-5 py-4 text-floor-ink"
                                    style={previewPlateStyle}
                                >
                                    <p className="text-2xs font-semibold tracking-[0.16em] uppercase opacity-70">
                                        {form.data.short_name}
                                    </p>
                                    <p className="mt-1 text-lg font-semibold">{t('settings.branding.plate_preview_title')}</p>
                                    <p className="mt-1 text-sm opacity-80">{t('settings.branding.plate_preview_body')}</p>
                                    <span
                                        className="mt-3 inline-flex h-8 items-center rounded-full bg-floor-ink px-3.5 text-sm font-medium"
                                        style={{ color: previewPlate }}
                                    >
                                        {t('settings.branding.preview_action')}
                                    </span>
                                </div>
                            </div>
                        </PanelSection>

                        {can.update ? (
                            <PanelFoot>
                                <Button type="submit" variant="primary" disabled={form.processing}>
                                    {t('settings.branding.save')}
                                </Button>
                                <Button type="button" variant="secondary" disabled={form.processing} onClick={resetDefaults}>
                                    {t('settings.branding.reset')}
                                </Button>
                            </PanelFoot>
                        ) : null}
                    </form>
                </Panel>
            </div>
        </AppLayout>
    );
}
