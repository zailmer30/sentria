import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Definition, DefinitionList } from '@/components/ui/definition-list';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelBody, PanelHead, PanelTitle } from '@/components/ui/panel';
import { StatusChip } from '@/components/ui/status';
import { UserAvatar } from '@/components/users/UserAvatar';
import AppLayout from '@/layouts/AppLayout';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';

type RoleSummary = { id: string; name: string; label: string };

type UserDetail = {
    id: string;
    display_name: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    name_suffix: string | null;
    honorific: string | null;
    email: string;
    employee_number: string | null;
    position_title: string | null;
    district: string | null;
    phone: string | null;
    locale: string;
    is_active: boolean;
    is_seated_member: boolean;
    last_login_at: string | null;
    created_at: string | null;
    avatar_url: string | null;
    roles: RoleSummary[];
};

type Props = {
    user: UserDetail;
    can: { update: boolean; delete: boolean };
};

export default function UsersShow({ user, can }: Props) {
    const { t } = useTranslations();
    const { formatDateTime } = useFormatters();

    function destroy() {
        if (!window.confirm(t('users.delete_confirm'))) {
            return;
        }

        router.delete(`/users/${user.id}`);
    }

    return (
        <AppLayout title={user.display_name}>
            <div className="mx-auto flex max-w-3xl flex-col gap-5">
                <PageHeader
                    title={user.display_name}
                    description={t('users.show_subtitle')}
                    leading={
                        <UserAvatar
                            name={user.display_name}
                            src={user.avatar_url}
                            alt={user.display_name}
                            className="size-14"
                            fallbackClassName="text-sm"
                        />
                    }
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {can.update ? (
                                <Button variant="primary" asChild>
                                    <Link href={`/users/${user.id}/edit`}>{t('users.edit')}</Link>
                                </Button>
                            ) : null}
                            {can.delete ? (
                                <Button variant="ghost" className="text-critical" onClick={destroy}>
                                    {t('users.delete')}
                                </Button>
                            ) : null}
                        </div>
                    }
                />

                <Panel>
                    <PanelHead>
                        <PanelTitle>{t('users.form_identity')}</PanelTitle>
                        <StatusChip tone={user.is_active ? 'final' : 'closed'} size="sm">
                            {user.is_active ? t('users.active') : t('users.inactive')}
                        </StatusChip>
                    </PanelHead>
                    <PanelBody>
                        <DefinitionList>
                            <Definition label={t('users.email')}>{user.email}</Definition>
                            <Definition label={t('users.honorific')}>{user.honorific ?? '—'}</Definition>
                            <Definition label={t('users.first_name')}>{user.first_name}</Definition>
                            <Definition label={t('users.middle_name')}>{user.middle_name ?? '—'}</Definition>
                            <Definition label={t('users.last_name')}>{user.last_name}</Definition>
                            <Definition label={t('users.name_suffix')}>{user.name_suffix ?? '—'}</Definition>
                            <Definition label={t('users.employee_number')}>{user.employee_number ?? '—'}</Definition>
                            <Definition label={t('users.position_title')}>{user.position_title ?? '—'}</Definition>
                            <Definition label={t('users.district')}>{user.district ?? '—'}</Definition>
                            <Definition label={t('users.phone')}>{user.phone ?? '—'}</Definition>
                            <Definition label={t('users.locale')}>
                                {user.locale === 'fil' ? t('users.locale_fil') : t('users.locale_en')}
                            </Definition>
                            <Definition label={t('users.is_seated_member')}>
                                {user.is_seated_member ? t('users.active') : t('users.inactive')}
                            </Definition>
                            <Definition label={t('users.last_login')} numeric>
                                {user.last_login_at ? formatDateTime(user.last_login_at) : '—'}
                            </Definition>
                            <Definition label={t('users.created_at')} numeric>
                                {user.created_at ? formatDateTime(user.created_at) : '—'}
                            </Definition>
                        </DefinitionList>
                    </PanelBody>
                </Panel>

                <Panel>
                    <PanelHead>
                        <PanelTitle>{t('users.roles')}</PanelTitle>
                    </PanelHead>
                    <PanelBody>
                        {user.roles.length === 0 ? (
                            <p className="text-sm text-ink-muted">{t('users.no_roles')}</p>
                        ) : (
                            <div className="flex flex-wrap gap-2">
                                {user.roles.map((role) => (
                                    <Badge key={role.id}>{role.label}</Badge>
                                ))}
                            </div>
                        )}
                    </PanelBody>
                </Panel>
            </div>
        </AppLayout>
    );
}
