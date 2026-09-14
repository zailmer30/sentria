import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelBody, PanelHead, PanelSection, PanelTitle } from '@/components/ui/panel';
import { Provenance, ProvenanceField } from '@/components/ui/provenance';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';

type LoginRow = {
    id: string;
    actor_label: string | null;
    actor_role: string | null;
    ip_address: string | null;
    occurred_at: string | null;
    user: { display_name: string; email: string } | null;
};

type Props = {
    status: {
        app_env: string;
        queue_connection: string;
        failed_jobs_count: number;
        ai_enabled: boolean;
        backup_status: string;
    };
    backup: {
        last_at: string | null;
        last_size_bytes: number | null;
        last_path: string | null;
        last_restore_test_at: string | null;
        next_scheduled: string | null;
        rpo_minutes: number;
        rto_minutes: number;
    };
    recent_logins: LoginRow[];
    horizon_url: string | null;
};

function formatBytes(bytes: number | null, t: (key: string, replacements?: Record<string, string | number>) => string): string {
    if (bytes === null || bytes === 0) {
        return '—';
    }

    if (bytes < 1024 * 1024) {
        return t('monitoring.size_kb', { size: Math.round(bytes / 1024) });
    }

    return t('monitoring.size_mb', { size: (bytes / (1024 * 1024)).toFixed(1) });
}

export default function Monitoring({ status, backup, recent_logins, horizon_url }: Props) {
    const { t } = useTranslations();

    const figures = [
        { label: t('monitoring.app_env'), value: status.app_env },
        { label: t('monitoring.queue_connection'), value: status.queue_connection },
        { label: t('monitoring.failed_jobs'), value: String(status.failed_jobs_count) },
        { label: t('monitoring.ai_enabled'), value: status.ai_enabled ? t('monitoring.yes') : t('monitoring.no') },
        { label: t('monitoring.backup_status'), value: t(`monitoring.backup_${status.backup_status}`) },
    ];

    return (
        <AppLayout title={t('monitoring.title')}>
            <div className="space-y-8">
                <PageHeader title={t('monitoring.title')} description={t('monitoring.intro')} />

                <Panel>
                    <PanelBody>
                        <Provenance bare>
                            {figures.map((figure) => (
                                <ProvenanceField key={figure.label} label={figure.label}>
                                    <span className="font-mono">{figure.value}</span>
                                </ProvenanceField>
                            ))}
                        </Provenance>
                    </PanelBody>
                </Panel>

                <Panel as="section" id="backup">
                    <PanelHead>
                        <PanelTitle>{t('monitoring.backup_dashboard')}</PanelTitle>
                    </PanelHead>
                    <PanelBody>
                        <Provenance bare>
                            <ProvenanceField label={t('monitoring.last_backup')}>
                                {backup.last_at ? new Date(backup.last_at).toLocaleString() : t('monitoring.none')}
                            </ProvenanceField>
                            <ProvenanceField label={t('monitoring.backup_size')}>
                                {formatBytes(backup.last_size_bytes, t)}
                            </ProvenanceField>
                            <ProvenanceField label={t('monitoring.last_restore_test')}>
                                {backup.last_restore_test_at
                                    ? new Date(backup.last_restore_test_at).toLocaleString()
                                    : t('monitoring.none')}
                            </ProvenanceField>
                            <ProvenanceField label={t('monitoring.next_backup')}>
                                {backup.next_scheduled ?? t('monitoring.schedule_placeholder')}
                            </ProvenanceField>
                            <ProvenanceField label={t('monitoring.rpo')}>
                                {t('monitoring.minutes_value', { count: backup.rpo_minutes })}
                            </ProvenanceField>
                            <ProvenanceField label={t('monitoring.rto')}>
                                {t('monitoring.minutes_value', { count: backup.rto_minutes })}
                            </ProvenanceField>
                        </Provenance>
                        <p className="mt-4 text-sm text-ink-muted">
                            {t('monitoring.backup_commands')}{' '}
                            <code className="rounded-[var(--radius-xs)] bg-canvas-sunk px-1 py-0.5 font-mono text-xs">
                                php artisan sentria:backup
                            </code>
                        </p>
                        <p className="mt-2 text-sm text-ink-muted">{t('monitoring.backup_docs_hint')}</p>
                    </PanelBody>
                </Panel>

                {horizon_url ? (
                    <Button variant="link" asChild>
                        <Link href={horizon_url}>{t('monitoring.open_horizon')}</Link>
                    </Button>
                ) : null}

                <Panel as="section">
                    <PanelHead>
                        <PanelTitle>{t('monitoring.recent_logins')}</PanelTitle>
                    </PanelHead>
                    {recent_logins.length === 0 ? (
                        <PanelBody>
                            <EmptyState bare title={t('monitoring.no_logins')} />
                        </PanelBody>
                    ) : (
                        <ul className="divide-y divide-line">
                            {recent_logins.map((login) => (
                                <PanelSection key={login.id} className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="text-sm text-ink">
                                        {login.user?.display_name ?? login.actor_label ?? '—'}
                                    </span>
                                    <span className="font-mono text-xs text-ink-muted">
                                        {login.occurred_at ? new Date(login.occurred_at).toLocaleString() : '—'}
                                        {login.ip_address ? ` · ${login.ip_address}` : ''}
                                    </span>
                                </PanelSection>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>
        </AppLayout>
    );
}
