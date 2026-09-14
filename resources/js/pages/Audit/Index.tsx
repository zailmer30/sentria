import { EmptyState } from '@/components/ui/empty-state';
import { IndexHeader } from '@/components/ui/index-header';
import { Notice } from '@/components/ui/notice';
import {
    Register,
    RegisterBody,
    RegisterCell,
    RegisterCellDate,
    RegisterCellPrimary,
    RegisterEmpty,
    RegisterFooter,
    RegisterFrame,
    RegisterHead,
    RegisterHeadCell,
    RegisterRow,
    type Paginated,
} from '@/components/ui/register';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { ScrollText } from 'lucide-react';

type AuditLogRow = {
    id: string;
    sequence: number;
    event: string;
    category: string;
    message: string | null;
    actor_label: string | null;
    actor_role: string | null;
    is_ai_actor: boolean;
    occurred_at: string | null;
    user: { display_name: string; email: string } | null;
};

type Props = {
    logs: Paginated<AuditLogRow>;
    can: { verify: boolean };
};

export default function AuditIndex({ logs, can }: Props) {
    const { t } = useTranslations();

    return (
        <AppLayout title={t('audit.title')}>
            <div className="flex flex-col gap-5">
                <IndexHeader
                    eyebrow={t('index.eyebrow.oversight')}
                    title={t('audit.title')}
                    description={t('audit.intro')}
                />

                {can.verify ? <Notice tone="info">{t('audit.verify_hint')}</Notice> : null}

                <RegisterFrame
                    footer={
                        <RegisterFooter
                            inset
                            from={logs.from}
                            to={logs.to}
                            total={logs.total}
                            links={logs.links}
                            label={t('audit.title')}
                        />
                    }
                >
                    <Register flush caption={t('audit.title')}>
                        <RegisterHead>
                            <RegisterHeadCell align="right">{t('audit.sequence')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('audit.event')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('audit.actor')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('audit.occurred_at')}</RegisterHeadCell>
                        </RegisterHead>
                        <RegisterBody>
                            {logs.data.length === 0 ? (
                                <RegisterEmpty colSpan={4}>
                                    <EmptyState bare icon={ScrollText} title={t('audit.empty')} />
                                </RegisterEmpty>
                            ) : (
                                logs.data.map((log) => (
                                    <RegisterRow key={log.id}>
                                        <RegisterCell numeric nowrap align="right">
                                            {log.sequence}
                                        </RegisterCell>
                                        <RegisterCellPrimary secondary={log.message ?? log.category}>
                                            {log.event}
                                        </RegisterCellPrimary>
                                        <RegisterCell>
                                            {log.user?.display_name ?? log.actor_label ?? '—'}
                                            {log.is_ai_actor ? (
                                                <span className="ml-2 font-mono text-2xs text-machine-ink uppercase">
                                                    {t('audit.ai_actor')}
                                                </span>
                                            ) : null}
                                        </RegisterCell>
                                        <RegisterCellDate value={log.occurred_at} withTime />
                                    </RegisterRow>
                                ))
                            )}
                        </RegisterBody>
                    </Register>
                </RegisterFrame>
            </div>
        </AppLayout>
    );
}
