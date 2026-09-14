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
    RegisterFrame,
    RegisterHead,
    RegisterHeadCell,
    RegisterOpenLink,
    RegisterRow,
} from '@/components/ui/register';
import { StatusChip } from '@/components/ui/status';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { Plus, Shield } from 'lucide-react';

type RoleRow = {
    id: string;
    name: string;
    label: string;
    is_system: boolean;
    precedence: number;
    permissions_count: number;
    users_count: number;
};

type Props = {
    roles: RoleRow[];
    can: { create: boolean; manage: boolean };
};

export default function RolesIndex({ roles, can }: Props) {
    const { t } = useTranslations();

    return (
        <AppLayout title={t('roles.manage_title')}>
            <div className="flex flex-col gap-5">
                <IndexHeader
                    eyebrow={t('index.eyebrow.administration')}
                    title={t('roles.manage_title')}
                    description={t('roles.index_subtitle')}
                    actions={
                        can.create ? (
                            <Button variant="plate" asChild>
                                <Link href="/roles/create">
                                    <Plus aria-hidden="true" strokeWidth={1.75} />
                                    {t('roles.create')}
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                <RegisterFrame>
                    <Register flush caption={t('roles.manage_title')}>
                        <RegisterHead>
                            <RegisterHeadCell>{t('roles.label')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('roles.name')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('users.status')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">{t('roles.permissions_count')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">{t('roles.users_count')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">
                                <span className="sr-only">{t('register.row_actions')}</span>
                            </RegisterHeadCell>
                        </RegisterHead>
                        <RegisterBody>
                            {roles.length === 0 ? (
                                <RegisterEmpty colSpan={6}>
                                    <EmptyState bare icon={Shield} title={t('roles.empty')} />
                                </RegisterEmpty>
                            ) : (
                                roles.map((role) => (
                                    <RegisterRow key={role.id}>
                                        <RegisterCellPrimary
                                            href={can.manage ? `/roles/${role.id}/edit` : undefined}
                                            secondary={`#${role.precedence}`}
                                        >
                                            {role.label}
                                        </RegisterCellPrimary>
                                        <RegisterCell numeric nowrap>
                                            {role.name}
                                        </RegisterCell>
                                        <RegisterCell nowrap>
                                            <StatusChip tone={role.is_system ? 'review' : 'draft'} size="sm">
                                                {role.is_system ? t('roles.system') : t('roles.custom')}
                                            </StatusChip>
                                        </RegisterCell>
                                        <RegisterCell numeric align="right">
                                            {role.permissions_count}
                                        </RegisterCell>
                                        <RegisterCell numeric align="right">
                                            {role.users_count}
                                        </RegisterCell>
                                        <RegisterCellActions>
                                            {can.manage ? (
                                                <RegisterOpenLink href={`/roles/${role.id}/edit`}>
                                                    {t('register.open')}
                                                </RegisterOpenLink>
                                            ) : null}
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
