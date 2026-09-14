import { useTranslations } from '@/lib/i18n';
import { Sparkles } from 'lucide-react';

/**
 * Placeholder for an assistant turn that has not arrived yet. Same material as
 * finished AI output so the thread does not jump when the answer lands.
 */
export function AiProcessing() {
    const { t } = useTranslations();

    return (
        <aside
            role="status"
            aria-live="polite"
            aria-busy="true"
            aria-label={t('ai.processing_status')}
            className="machine-surface animate-rise-in relative overflow-hidden rounded-[var(--radius-md)] border border-[var(--color-machine-line)]"
        >
            <span aria-hidden="true" className="ai-processing-scan" />
            <div className="relative flex items-center gap-3 px-3 py-3">
                <Sparkles
                    aria-hidden="true"
                    strokeWidth={2}
                    className="size-3.5 shrink-0 text-[var(--color-machine-ink)]"
                />
                <p className="text-sm text-[var(--color-machine-ink)]">{t('ai.asking')}</p>
                <span aria-hidden="true" className="ai-thinking-dots ml-auto text-[var(--color-machine-ink)]">
                    <span />
                    <span />
                    <span />
                </span>
            </div>
        </aside>
    );
}
