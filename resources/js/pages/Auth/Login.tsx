/**
 * Staff sign-in: a 50/50 navy plate and paper form. Fortify still owns POST /login;
 * this page only rebuilds the surface. Forgot-password and reset stay on GuestLayout.
 */

import { Checkbox } from '@/components/ui/input';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, Eye, EyeOff, FileText, Gavel, Landmark, Lock, Users, type LucideIcon } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type LoginProps = {
    canResetPassword: boolean;
    status?: string;
};

export default function Login({ canResetPassword, status }: LoginProps) {
    const { organization, flash } = usePage<PageProps>().props;
    const { t } = useTranslations();
    const [showPassword, setShowPassword] = useState(false);
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const pageTitle = `${t('auth.sign_in')} · ${t('app.name')}`;
    const banner = status ?? flash?.success ?? flash?.error ?? null;
    const year = new Date().getFullYear();

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/login');
    }

    return (
        <div className="login-shell min-h-screen antialiased">
            <Head title={pageTitle} />

            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-[8px] focus:border focus:border-[var(--color-focus)] focus:bg-[var(--login-white)] focus:px-3 focus:py-2 focus:text-sm focus:font-medium focus:text-[var(--login-heading)]"
            >
                {t('a11y.skip')}
            </a>

            <div className="grid min-h-screen gap-0 lg:h-screen lg:grid-cols-2 lg:overflow-hidden">
                <aside className="login-brand flex flex-col px-7 py-8 lg:h-full lg:min-h-0 lg:overflow-y-auto lg:px-12 lg:pt-10 lg:pb-8">
                    <div className="flex items-center gap-3">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full border border-[var(--login-lockup-border)] bg-[var(--login-lockup-bg)]">
                            <Landmark aria-hidden="true" className="size-[18px] text-white" strokeWidth={1.75} />
                        </span>
                        <div className="min-w-0">
                            <p className="text-[18px] leading-[1.1] font-bold tracking-[-0.02em] text-white">{t('app.name')}</p>
                            <p className="text-[11px] leading-[1.3] font-normal text-[var(--login-muted)]">{t('app.tagline')}</p>
                        </div>
                    </div>

                    <div className="my-auto w-full max-w-[460px] py-10">
                        <p className="mb-4 text-[11px] leading-none font-medium tracking-[0.18em] text-[var(--login-muted)] uppercase">
                            {organization.name}
                        </p>
                        <p className="mb-4 max-w-[18ch] text-[clamp(28px,3.2vw,36px)] leading-[1.15] font-bold tracking-[-0.03em] [text-wrap:wrap] text-white">
                            {t('auth.headline')}
                        </p>
                        <p className="mb-9 max-w-[42ch] text-[14px] leading-[1.6] font-normal text-[var(--login-muted)]">
                            {t('auth.brand_body')}
                        </p>
                        <ul className="flex flex-col gap-5">
                            <FeatureRow
                                icon={FileText}
                                title={t('auth.feature.registry.title')}
                                body={t('auth.feature.registry.body')}
                            />
                            <FeatureRow icon={Gavel} title={t('auth.feature.floor.title')} body={t('auth.feature.floor.body')} />
                            <FeatureRow
                                icon={Users}
                                title={t('auth.feature.access.title')}
                                body={t('auth.feature.access.body')}
                            />
                        </ul>
                    </div>

                    <p className="text-[12px] leading-normal font-normal text-[var(--login-faint)]">
                        {t('auth.left_footer', { locality: organization.locality })}
                    </p>
                </aside>

                <section className="relative flex min-h-0 flex-col bg-[var(--login-white)] lg:h-full lg:overflow-y-auto">
                    <div className="relative flex min-h-full flex-1 flex-col px-7 py-8 lg:px-12">
                        <Link
                            href="/portal"
                            className="absolute top-8 right-7 inline-flex items-center gap-1 text-[13px] font-medium text-[var(--login-body)] no-underline hover:underline lg:right-12"
                        >
                            {t('nav.portal')}
                            <ArrowRight aria-hidden="true" className="size-[14px]" strokeWidth={2} />
                        </Link>

                        <form id="main" onSubmit={submit} className="mx-auto my-auto w-full max-w-[400px] pt-10 pb-8" noValidate>
                            <div className="mb-5 flex justify-center">
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-[var(--login-badge)] px-3 py-1 text-[12px] font-medium text-[var(--login-badge-ink)]">
                                    <Lock aria-hidden="true" className="size-3" strokeWidth={2} />
                                    {t('auth.authorized_only')}
                                </span>
                            </div>

                            <h1 className="mb-2 text-center text-[32px] leading-[1.15] font-bold tracking-[-0.03em] [text-wrap:wrap] text-[var(--login-heading)]">
                                {t('auth.sign_in')}
                            </h1>
                            <p className="mx-auto mb-8 max-w-[36ch] text-center text-[14px] leading-[1.5] font-normal text-[var(--login-faint)]">
                                {t('auth.staff_subhead')}
                            </p>

                            {banner ? (
                                <p role="status" className="mb-4 text-center text-[12px] leading-[1.5] text-[var(--login-body)]">
                                    {banner}
                                </p>
                            ) : null}

                            <div className="mb-4">
                                <label
                                    htmlFor="email"
                                    className="mb-1.5 block text-[13px] font-semibold text-[var(--login-heading)]"
                                >
                                    {t('auth.email_official')}
                                </label>
                                <input
                                    id="email"
                                    className="login-input"
                                    type="email"
                                    name="email"
                                    autoComplete="username"
                                    placeholder={t('auth.email_placeholder')}
                                    value={form.data.email}
                                    onChange={(event) => form.setData('email', event.target.value)}
                                    aria-invalid={form.errors.email ? true : undefined}
                                    aria-describedby={form.errors.email ? 'email-error' : undefined}
                                    required
                                />
                                <FieldError id="email-error" message={form.errors.email} />
                            </div>

                            <div className="mb-4">
                                <div className="mb-1.5 flex items-center justify-between">
                                    <label htmlFor="password" className="text-[13px] font-semibold text-[var(--login-heading)]">
                                        {t('auth.password')}
                                    </label>
                                    {canResetPassword ? (
                                        <Link
                                            href="/forgot-password"
                                            className="text-[13px] font-medium text-[var(--login-faint)] no-underline hover:underline"
                                        >
                                            {t('auth.forgot')}
                                        </Link>
                                    ) : null}
                                </div>
                                <div className="relative">
                                    <input
                                        id="password"
                                        className="login-input login-input-password"
                                        type={showPassword ? 'text' : 'password'}
                                        name="password"
                                        autoComplete="current-password"
                                        value={form.data.password}
                                        onChange={(event) => form.setData('password', event.target.value)}
                                        aria-invalid={form.errors.password ? true : undefined}
                                        aria-describedby={form.errors.password ? 'password-error' : undefined}
                                        required
                                    />
                                    <button
                                        type="button"
                                        className="absolute top-1/2 right-3.5 -translate-y-1/2 text-[var(--login-muted)] hover:text-[var(--login-heading)]"
                                        aria-pressed={showPassword}
                                        aria-label={showPassword ? t('auth.hide_password') : t('auth.show_password')}
                                        onClick={() => setShowPassword((visible) => !visible)}
                                    >
                                        {showPassword ? (
                                            <EyeOff aria-hidden="true" className="size-4" strokeWidth={1.75} />
                                        ) : (
                                            <Eye aria-hidden="true" className="size-4" strokeWidth={1.75} />
                                        )}
                                    </button>
                                </div>
                                <FieldError id="password-error" message={form.errors.password} />
                            </div>

                            <label className="mb-5 flex items-center gap-2 text-[13px] font-normal text-[var(--login-body)]">
                                <Checkbox
                                    name="remember"
                                    checked={form.data.remember}
                                    onChange={(event) => form.setData('remember', event.target.checked)}
                                />
                                {t('auth.remember')}
                            </label>

                            <button
                                type="submit"
                                disabled={form.processing}
                                className={cn(
                                    'mb-7 inline-flex h-11 w-full items-center justify-center gap-1 rounded-[8px] border-0',
                                    'bg-[var(--login-navy)] text-[14px] font-semibold text-white shadow-none',
                                    'hover:bg-[var(--login-navy-hover)]',
                                    'disabled:pointer-events-none disabled:opacity-60',
                                )}
                            >
                                {t('auth.login')}
                                <ArrowRight aria-hidden="true" className="size-[14px]" strokeWidth={2} />
                            </button>

                            <div className="mb-3 flex items-center gap-3">
                                <span className="h-px flex-1 bg-[var(--login-hairline)]" />
                                <span className="text-[12px] font-medium text-[var(--login-faint)]">{t('auth.need_access')}</span>
                                <span className="h-px flex-1 bg-[var(--login-hairline)]" />
                            </div>

                            <p className="mb-5 text-center text-[12px] leading-[1.55] font-normal text-[var(--login-faint)]">
                                {t('auth.help_copy')}
                            </p>

                            <div className="flex items-start gap-2.5 rounded-[8px] border border-[var(--login-hairline)] bg-[var(--login-notice)] px-3.5 py-3">
                                <Lock
                                    aria-hidden="true"
                                    className="mt-px size-[14px] shrink-0 text-[var(--login-faint)]"
                                    strokeWidth={2}
                                />
                                <p className="text-[12px] leading-[1.5] font-normal text-[var(--login-body)]">
                                    {t('auth.audit_notice')}
                                </p>
                            </div>
                        </form>

                        <p className="shrink-0 text-center text-[12px] font-normal text-[var(--login-muted)]">
                            {t('auth.copyright', {
                                year,
                                name: organization.name,
                                locality: organization.locality,
                            })}
                        </p>
                    </div>
                </section>
            </div>
        </div>
    );
}

function FeatureRow({ icon: Icon, title, body }: { icon: LucideIcon; title: string; body: string }) {
    return (
        <li className="flex items-start gap-3.5">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-[8px] border border-[var(--login-icon-well-border)] bg-[var(--login-icon-well-bg)]">
                <Icon aria-hidden="true" className="size-[18px] text-white" strokeWidth={1.75} />
            </span>
            <div>
                <p className="mb-0.5 text-[14px] leading-[1.3] font-semibold text-white">{title}</p>
                <p className="text-[13px] leading-[1.45] font-normal text-[var(--login-muted)]">{body}</p>
            </div>
        </li>
    );
}

function FieldError({ id, message }: { id: string; message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <p id={id} className="mt-1.5 text-[12px] font-medium text-critical">
            {message}
        </p>
    );
}
