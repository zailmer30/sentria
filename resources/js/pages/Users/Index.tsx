import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { FilterBar, FilterCell, SearchField } from '@/components/ui/filter-bar';
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
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { UserAvatar } from '@/components/users/UserAvatar';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';
import { Plus, SlidersHorizontal, Users } from 'lucide-react';
import { FormEvent, useState } from 'react';

type RoleSummary = { id: string; name: string; label: string };

type UserRow = {
    id: string;
    display_name: string;
    avatar_url: string | null;
    email: string;
    is_active: boolean;
    roles: RoleSummary[];
};

type Props = {
    users: Paginated<UserRow>;
    filters: { search: string | null; active: string | null };
    can: { create: boolean };
};

const ANY = '__any';

export default function UsersIndex({ users, filters, can }: Props) {
    const { t } = useTranslations();
    const [search, setSearch] = useState(filters.search ?? '');
    const active = filters.active ?? ANY;

    function visit(next: { search?: string; active?: string } = {}) {
        const nextSearch = next.search ?? search;
        const nextActive = next.active ?? active;

        router.get(
            '/users',
            {
                search: nextSearch || undefined,
                active: nextActive === ANY ? undefined : nextActive,
            },
            { preserveState: true },
        );
    }

    function applyFilters(event: FormEvent) {
        event.preventDefault();
        visit({ search });
    }

    function resetFilters() {
        setSearch('');
        visit({ search: '', active: ANY });
    }

    return (
        <AppLayout title={t('users.title')}>
            <div className="flex flex-col gap-5">
                <IndexHeader
                    eyebrow={t('index.eyebrow.administration')}
                    title={t('users.title')}
                    description={t('users.index_subtitle')}
                    actions={
                        can.create ? (
                            <Button variant="plate" asChild>
                                <Link href="/users/create">
                                    <Plus aria-hidden="true" strokeWidth={1.75} />
                                    {t('users.create')}
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                <RegisterFrame
                    toolbar={
                        <ToggleGroup
                            type="single"
                            size="default"
                            value={active === ANY ? 'all' : active}
                            aria-label={t('users.filter_active')}
                            onValueChange={(next) => {
                                if (next === 'all') {
                                    visit({ active: ANY, search });
                                } else if (next === '1' || next === '0') {
                                    visit({ active: next, search });
                                }
                            }}
                        >
                            <ToggleGroupItem value="all">{t('users.filter_all')}</ToggleGroupItem>
                            <ToggleGroupItem value="1">{t('users.filter_active_only')}</ToggleGroupItem>
                            <ToggleGroupItem value="0">{t('users.filter_inactive_only')}</ToggleGroupItem>
                        </ToggleGroup>
                    }
                    filters={
                        <FilterBar
                            inline
                            hideSubmit
                            onSubmit={applyFilters}
                            trailing={
                                <Button type="button" variant="secondary" onClick={resetFilters}>
                                    <SlidersHorizontal aria-hidden="true" strokeWidth={1.75} />
                                    {t('register.reset')}
                                </Button>
                            }
                        >
                            <FilterCell grow>
                                <SearchField
                                    id="search"
                                    value={search}
                                    onChange={setSearch}
                                    placeholder={t('users.search_placeholder')}
                                    label={t('users.search')}
                                />
                            </FilterCell>
                        </FilterBar>
                    }
                    footer={
                        <RegisterFooter
                            inset
                            from={users.from}
                            to={users.to}
                            total={users.total}
                            links={users.links}
                            label={t('users.title')}
                        />
                    }
                >
                    <Register flush caption={t('users.title')}>
                        <RegisterHead>
                            <RegisterHeadCell>{t('users.name')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('users.email')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('users.roles')}</RegisterHeadCell>
                            <RegisterHeadCell>{t('users.status')}</RegisterHeadCell>
                            <RegisterHeadCell align="right">
                                <span className="sr-only">{t('register.row_actions')}</span>
                            </RegisterHeadCell>
                        </RegisterHead>
                        <RegisterBody>
                            {users.data.length === 0 ? (
                                <RegisterEmpty colSpan={5}>
                                    <EmptyState bare icon={Users} title={t('users.empty')} />
                                </RegisterEmpty>
                            ) : (
                                users.data.map((user) => (
                                    <RegisterRow key={user.id}>
                                        <RegisterCellPrimary href={`/users/${user.id}`}>
                                            <span className="flex items-center gap-2.5">
                                                <UserAvatar
                                                    name={user.display_name}
                                                    src={user.avatar_url}
                                                    className="size-8"
                                                    fallbackClassName="text-2xs"
                                                />
                                                {user.display_name}
                                            </span>
                                        </RegisterCellPrimary>
                                        <RegisterCell>{user.email}</RegisterCell>
                                        <RegisterCell>
                                            {user.roles.length > 0
                                                ? user.roles.map((role) => role.label).join(', ')
                                                : '—'}
                                        </RegisterCell>
                                        <RegisterCell nowrap>
                                            <StatusChip tone={user.is_active ? 'final' : 'closed'} size="sm">
                                                {user.is_active ? t('users.active') : t('users.inactive')}
                                            </StatusChip>
                                        </RegisterCell>
                                        <RegisterCellActions>
                                            <RegisterOpenLink href={`/users/${user.id}`}>
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
