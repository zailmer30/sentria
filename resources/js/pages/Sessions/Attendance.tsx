import { Button } from '@/components/ui/button';
import { Figure } from '@/components/ui/figure';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelBody, PanelHead, PanelTitle } from '@/components/ui/panel';
import { SimpleSelect } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';

type AttendanceRow = {
    id: string;
    user_id: string;
    display_name: string | null;
    status: string;
};

type Props = {
    session: { id: string; title: string };
    attendance: AttendanceRow[];
    quorum: { present_count: number; required: number; met: boolean };
    can: { record: boolean };
    statuses: { value: string; label: string }[];
};

export default function SessionsAttendance({ session, attendance, quorum, can, statuses }: Props) {
    const { t } = useTranslations();

    function updateStatus(userId: string, status: string) {
        router.put(`/sessions/${session.id}/attendance`, {
            records: [{ user_id: userId, status }],
        });
    }

    return (
        <AppLayout title={t('sessions.attendance')}>
            <div className="mx-auto max-w-4xl space-y-6">
                <PageHeader
                    title={t('sessions.attendance')}
                    description={session.title}
                    actions={
                        <Button variant="secondary" asChild>
                            <Link href={`/sessions/${session.id}`}>{t('sessions.back')}</Link>
                        </Button>
                    }
                />

                <Panel>
                    <PanelHead sunk>
                        <PanelTitle>{t('sessions.quorum')}</PanelTitle>
                    </PanelHead>
                    <PanelBody>
                        <Figure
                            value={`${quorum.present_count}/${quorum.required}`}
                            label={quorum.met ? t('sessions.quorum_met') : t('sessions.quorum_not_met')}
                            size="sm"
                        />
                    </PanelBody>
                </Panel>

                <Panel>
                    <ul className="divide-y divide-line">
                        {attendance.map((row) => (
                            <li key={row.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                                <span className="text-sm text-ink">{row.display_name}</span>
                                {can.record ? (
                                    <SimpleSelect
                                        value={row.status}
                                        onValueChange={(status) => updateStatus(row.user_id, status)}
                                        aria-label={t('sessions.attendance_status', { name: row.display_name ?? '' })}
                                        className="w-44"
                                        items={statuses}
                                    />
                                ) : (
                                    <span className="text-sm text-ink-muted">{row.status}</span>
                                )}
                            </li>
                        ))}
                    </ul>
                </Panel>
            </div>
        </AppLayout>
    );
}
