import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { IndexHeader } from '@/components/ui/index-header';
import {
    Register,
    RegisterBody,
    RegisterCell,
    RegisterCellActions,
    RegisterCellPrimary,
    RegisterEmpty,
    RegisterFooter,
    RegisterFrame,
    RegisterHead,
    RegisterHeadCell,
    RegisterOpenLink,
    RegisterRow,
    type Paginated,
} from '@/components/ui/register';
import { StatusChip } from '@/components/ui/status';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';

type CommitteeRow = {
    id: string;
    slug: string;
    name: string;
    code: string | null;
    is_active: boolean;
    members_count: number;
    referrals_count: number;
    reports_count: number;
};

type Props = {
    committees: Paginated<CommitteeRow>;
    can: { create: boolean };
};

export default function CommitteesIndex({ committees, can }: Props) {
    const { t } = useTranslations();

    return (
        <AppLayout title={t('committees.title')}>
            <div className="flex flex-col gap-5">
                <IndexHeader
                    eyebrow={t('index.eyebrow.record')}
                    title={t('committees.title')}
                    description={t('committees.index_subtitle')}
                    actions={
                        can.create ? (
                            <Button variant="plate" asChild>
                                <Link href="/committees/create">
                                    <Plus aria-hidden="true" strokeWidth={1.75} />
                                    {t('committees.create')}
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                <RegisterFrame
                    footer={
                        <RegisterFooter
                            inset
                            from={committees.from}
                            to={committees.to}
                            total={committees.total}
                            links={committees.links}
                            label={t('committees.title')}
                        />
                    }
                >
                    <Register flush caption={t('committees.title')}>
                        <RegisterHead>
                            <RegisterHeadCell>{t('committees.code')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('committees.name')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('committees.status')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">{t('committees.members')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">{t('committees.referrals')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">{t('committees.reports')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">
                                <span className="sr-only">{t('register.row_actions')}</span>
                            </RegisterHeadCell>
                        </RegisterHead>
                        <RegisterBody>
                            {committees.data.length === 0 ? (
                                <RegisterEmpty colSpan={7}>
                                    <EmptyState bare icon={Users} title={t('committees.empty')} />
                                </RegisterEmpty>
                            ) : (
                                committees.data.map((committee) => (
                                    <RegisterRow key={committee.id}>
                                        <RegisterCell numeric nowrap>
                                            {committee.code ?? '—'}
                                        </RegisterCell>
                                        <RegisterCellPrimary href={`/committees/${committee.slug}`}>
                                            {committee.name}
                                        </RegisterCellPrimary>
                                        <RegisterCell nowrap>
                                            <StatusChip tone={committee.is_active ? 'final' : 'closed'} size="sm">
                                                {committee.is_active ? t('committees.active') : t('committees.inactive')}
                                            </StatusChip>
                                        </RegisterCell>
                                        <RegisterCell numeric align="right">
                                            {committee.members_count}
                                        </RegisterCell>
                                        <RegisterCell numeric align="right">
                                            {committee.referrals_count}
                                        </RegisterCell>
                                        <RegisterCell numeric align="right">
                                            {committee.reports_count}
                                        </RegisterCell>
                                        <RegisterCellActions>
                                            <RegisterOpenLink href={`/committees/${committee.slug}`}>
                                                {t('register.open')}
                                            </RegisterOpenLink>
                                        </RegisterCellActions>
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
