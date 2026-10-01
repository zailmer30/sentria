import { HistoryTimeline, type HistoryEvent } from '@/components/legislation/LegislativeHistoryPreview';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageHeader } from '@/components/ui/page-header';
import { Identifier } from '@/components/ui/provenance';
import AppLayout from '@/layouts/AppLayout';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { History } from 'lucide-react';

type Subject = {
    type: 'ordinance' | 'resolution' | 'document';
    id: string;
    title: string;
    number: string;
    document_slug: string | null;
};

type Props = {
    subject: Subject;
    events: HistoryEvent[];
};

export default function LegislativeHistory({ subject, events }: Props) {
    const { t } = useTranslations();
    const { formatDate } = useFormatters();
    const newestFirst = events.toReversed();

    const backHref =
        subject.type === 'ordinance'
            ? `/ordinances/${subject.id}`
            : subject.type === 'resolution'
              ? `/resolutions/${subject.id}`
              : subject.document_slug
                ? `/documents/${subject.document_slug}`
                : '/documents';

    return (
        <AppLayout title={t('legislation.history_title')}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title={subject.title}
                    description={t('legislation.history_subtitle')}
                    provenance={<Identifier>{subject.number}</Identifier>}
                    actions={
                        <Button variant="secondary" asChild>
                            <Link href={backHref}>{t('legislation.back')}</Link>
                        </Button>
                    }
                />

                {newestFirst.length === 0 ? (
                    <EmptyState icon={History} title={t('legislation.history_empty')} />
                ) : (
                    <HistoryTimeline events={newestFirst} formatDate={formatDate} showMeta />
                )}
            </div>
        </AppLayout>
    );
}
