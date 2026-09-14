import { PortalContainer } from '@/components/portal/PortalContainer';
import { PortalRecordCard, type PortalPublicationCard } from '@/components/portal/PortalRecordCard';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PortalLayout from '@/layouts/PortalLayout';
import { useTranslations } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEvent } from 'react';

type Props = {
    featured: PortalPublicationCard[];
    filters: { keyword?: string | null };
    stats: {
        published: number;
        coverage_since: number;
    };
};

export default function PortalHome({ featured, filters, stats }: Props) {
    const { t } = useTranslations();

    function submitSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.get('/portal/search', { keyword: String(form.get('keyword') ?? '') });
    }

    return (
        <PortalLayout title={t('portal.home_title')} description={t('portal.home_description')} ogUrl="/portal">
            <section className="relative overflow-hidden bg-plate text-accent-on">
                <div className="pointer-events-none absolute inset-0 bg-hairlines" aria-hidden="true" />
                <PortalContainer className="relative py-14 lg:py-20">
                    <p className="text-eyebrow text-accent-on/80">{t('portal.eyebrow')}</p>
                    <h1 className="mt-4 max-w-4xl text-balance text-3xl font-bold text-accent-on sm:text-4xl lg:text-5xl">
                        {t('portal.home_headline')}
                    </h1>
                    <p className="mt-5 max-w-2xl text-base text-accent-on/80 sm:text-lg">{t('portal.home_supporting')}</p>

                    <form
                        onSubmit={submitSearch}
                        className="mt-8 flex max-w-3xl flex-col gap-3 sm:flex-row sm:items-center"
                    >
                        <div className="relative min-w-0 flex-1">
                            <Search
                                aria-hidden="true"
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-muted"
                            />
                            <Input
                                id="keyword"
                                name="keyword"
                                defaultValue={filters.keyword ?? ''}
                                placeholder={t('portal.search_placeholder')}
                                aria-label={t('portal.search_label')}
                                className="h-12 bg-surface pl-10 text-ink"
                            />
                        </div>
                        <Button type="submit" variant="secondary" size="lg" className="h-12 shrink-0 bg-surface">
                            {t('portal.search_cta')}
                        </Button>
                    </form>

                    <dl className="mt-12 grid grid-cols-1 gap-6 border-t border-accent-on/20 pt-6 sm:grid-cols-3">
                        <div>
                            <dt className="text-sm text-accent-on/70">{t('portal.stat.published')}</dt>
                            <dd className="mt-1 font-display text-2xl font-semibold tracking-tight">
                                {String(stats.published).padStart(2, '0')}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-accent-on/70">{t('portal.stat.coverage')}</dt>
                            <dd className="mt-1 font-display text-2xl font-semibold tracking-tight">
                                {stats.coverage_since}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-accent-on/70">{t('portal.stat.cadence')}</dt>
                            <dd className="mt-1 font-display text-2xl font-semibold tracking-tight">
                                {t('portal.stat.cadence_value')}
                            </dd>
                        </div>
                    </dl>
                </PortalContainer>
            </section>

            <PortalContainer className="py-10 lg:py-12">
                <div className="mb-6 flex items-end justify-between gap-4">
                    <h2 id="featured-heading" className="font-display text-xl font-semibold text-ink">
                        {t('portal.featured_heading')}
                    </h2>
                    <Button variant="link" size="sm" asChild>
                        <Link href="/portal/search">{t('portal.view_all')}</Link>
                    </Button>
                </div>

                {featured.length === 0 ? (
                    <div className="rounded-[var(--radius-lg)] border border-dashed border-line bg-surface p-10 text-center">
                        <p className="font-display text-lg font-semibold">{t('portal.no_results')}</p>
                    </div>
                ) : (
                    <ul className="space-y-4" aria-labelledby="featured-heading">
                        {featured.map((item) => (
                            <li key={item.slug}>
                                <PortalRecordCard item={item} />
                            </li>
                        ))}
                    </ul>
                )}
            </PortalContainer>
        </PortalLayout>
    );
}
