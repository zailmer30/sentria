import { Definition, DefinitionList } from '@/components/ui/definition-list';
import { Notice } from '@/components/ui/notice';
import { Panel, PanelBody, PanelHead } from '@/components/ui/panel';
import { cn } from '@/lib/utils';
import { useTranslations } from '@/lib/i18n';
import { BookOpen, ChevronDown } from 'lucide-react';
import { useId, useState } from 'react';

const STEPS: { title: string; body: string; items: string[] }[] = [
    {
        title: 'chamber.manual.mixer.title',
        body: 'chamber.manual.mixer.body',
        items: [
            'chamber.manual.mixer.i1',
            'chamber.manual.mixer.i2',
            'chamber.manual.mixer.i3',
            'chamber.manual.mixer.i4',
        ],
    },
    {
        title: 'chamber.manual.s1.title',
        body: 'chamber.manual.s1.body',
        items: [
            'chamber.manual.s1.i1',
            'chamber.manual.s1.i2',
            'chamber.manual.s1.i3',
            'chamber.manual.s1.i4',
            'chamber.manual.s1.i5',
        ],
    },
    {
        title: 'chamber.manual.s2.title',
        body: 'chamber.manual.s2.body',
        items: ['chamber.manual.s2.i1', 'chamber.manual.s2.i2', 'chamber.manual.s2.i3'],
    },
    {
        title: 'chamber.manual.s3.title',
        body: 'chamber.manual.s3.body',
        items: ['chamber.manual.s3.i1', 'chamber.manual.s3.i2'],
    },
    {
        title: 'chamber.manual.s4.title',
        body: 'chamber.manual.s4.body',
        items: [
            'chamber.manual.s4.i1',
            'chamber.manual.s4.i2',
            'chamber.manual.s4.i3',
            'chamber.manual.s4.i4',
            'chamber.manual.s4.i5',
        ],
    },
    {
        title: 'chamber.manual.s5.title',
        body: 'chamber.manual.s5.body',
        items: ['chamber.manual.s5.i1', 'chamber.manual.s5.i2'],
    },
    {
        title: 'chamber.manual.s6.title',
        body: 'chamber.manual.s6.body',
        items: ['chamber.manual.s6.i1', 'chamber.manual.s6.i2', 'chamber.manual.s6.i3'],
    },
    {
        title: 'chamber.manual.s7.title',
        body: 'chamber.manual.s7.body',
        items: [
            'chamber.manual.s7.i1',
            'chamber.manual.s7.i2',
            'chamber.manual.s7.i3',
            'chamber.manual.s7.i4',
        ],
    },
    {
        title: 'chamber.manual.s8.title',
        body: 'chamber.manual.s8.body',
        items: [
            'chamber.manual.s8.i1',
            'chamber.manual.s8.i2',
            'chamber.manual.s8.i3',
            'chamber.manual.s8.i4',
        ],
    },
];

export function ChamberMicrophoneManual({ defaultOpen = false }: { defaultOpen?: boolean }) {
    const { t } = useTranslations();
    const [open, setOpen] = useState(defaultOpen);
    const panelId = useId();

    return (
        <Panel as="section" className="gap-0">
            <PanelHead sunk className={cn('p-0', !open && 'border-b-0')}>
                <button
                    type="button"
                    className="flex min-h-12 w-full items-center justify-between gap-3 px-5 py-3.5 text-left"
                    aria-expanded={open}
                    aria-controls={panelId}
                    onClick={() => setOpen((value) => !value)}
                >
                    <span className="flex items-center gap-2">
                        <BookOpen aria-hidden="true" className="size-4 text-accent" strokeWidth={1.75} />
                        <span className="text-sm font-semibold text-ink">{t('chamber.manual.title')}</span>
                    </span>
                    <span className="flex items-center gap-2 text-xs text-ink-muted">
                        {open ? t('chamber.manual.hide') : t('chamber.manual.show')}
                        <ChevronDown
                            aria-hidden="true"
                            className={cn(
                                'size-4 shrink-0 text-ink-faint transition-transform duration-[var(--duration-base)]',
                                open && 'rotate-180',
                            )}
                            strokeWidth={1.75}
                        />
                    </span>
                </button>
            </PanelHead>
            {open ? (
            <PanelBody id={panelId} className="flex flex-col gap-5">
                <p className="text-sm text-ink-muted">{t('chamber.manual.intro')}</p>
                <p className="text-sm text-ink-muted">{t('chamber.manual.who')}</p>
                <Notice tone="caution">{t('chamber.manual.not_supported')}</Notice>

                <ol className="flex flex-col gap-6">
                    {STEPS.map((step, index) => (
                        <li key={step.title} className="grid gap-2 sm:grid-cols-[2.25rem_1fr] sm:gap-4">
                            <span className="font-mono text-sm font-medium text-ink-faint tabular-nums">
                                {String(index + 1).padStart(2, '0')}
                            </span>
                            <div className="min-w-0">
                                <h3 className="text-sm font-semibold text-ink">{t(step.title)}</h3>
                                <p className="mt-1 text-sm text-ink-muted">{t(step.body)}</p>
                                <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-ink-muted">
                                    {step.items.map((item) => (
                                        <li key={item}>{t(item)}</li>
                                    ))}
                                </ul>
                                {index === 3 ? (
                                    <DefinitionList className="mt-3">
                                        <Definition label={t('chamber.channel_index')}>
                                            {t('chamber.manual.field.channel')}
                                        </Definition>
                                        <Definition label={t('chamber.device')}>
                                            {t('chamber.manual.field.device')}
                                        </Definition>
                                        <Definition label={t('chamber.member')}>
                                            {t('chamber.manual.field.member')}
                                        </Definition>
                                        <Definition label={t('chamber.label')}>
                                            {t('chamber.manual.field.label')}
                                        </Definition>
                                        <Definition label={t('chamber.active')}>
                                            {t('chamber.manual.field.active')}
                                        </Definition>
                                    </DefinitionList>
                                ) : null}
                            </div>
                        </li>
                    ))}
                </ol>
            </PanelBody>
            ) : (
                <div id={panelId} hidden />
            )}
        </Panel>
    );
}
