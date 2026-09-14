import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Checkbox, Input, Textarea } from '@/components/ui/input';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelFoot, PanelSection } from '@/components/ui/panel';
import { SimpleSelect } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

/**
 * Create and edit share one instrument: identity, calendar, officers, chamber.
 * The save control is the only accent on the page; cancel never competes with it.
 */

export type SessionUserOption = { id: string; display_name: string };

export type SessionFormRecord = {
    id: string;
    session_number: string;
    title: string;
    type: string;
    legislative_year: number | null;
    scheduled_start_at: string | null;
    scheduled_end_at: string | null;
    venue: string | null;
    presiding_officer_id: string | null;
    secretary_id: string | null;
    seated_member_count: number | null;
    is_public: boolean;
    notes: string | null;
};

type SessionTypeOption = {
    value: string;
    label: string;
    tag: string;
    next_session_number: string;
    next_title: string;
    next_sequence: number;
};

type Props = {
    session?: SessionFormRecord;
    users: SessionUserOption[];
    sessionTypes?: SessionTypeOption[];
};

export default function SessionFormPage({ session, users, sessionTypes = [] }: Props) {
    const { t } = useTranslations();
    const isEdit = Boolean(session);
    const defaultType = sessionTypes[0];

    const form = useForm({
        session_number: session?.session_number ?? defaultType?.next_session_number ?? '',
        title: session?.title ?? defaultType?.next_title ?? '',
        type: session?.type ?? defaultType?.value ?? 'regular',
        legislative_year: session?.legislative_year ?? new Date().getFullYear(),
        scheduled_start_at: toDatetimeLocal(session?.scheduled_start_at ?? null),
        scheduled_end_at: toDatetimeLocal(session?.scheduled_end_at ?? null),
        venue: session?.venue ?? '',
        presiding_officer_id: session?.presiding_officer_id ?? '',
        secretary_id: session?.secretary_id ?? '',
        seated_member_count: session?.seated_member_count != null ? String(session.seated_member_count) : '',
        is_public: session?.is_public ?? true,
        notes: session?.notes ?? '',
    });

    function assignedIdentity(type: string, year: number) {
        const option = sessionTypes.find((item) => item.value === type);
        const sequence = option?.next_sequence ?? 1;
        const padded = String(sequence).padStart(5, '0');

        return {
            session_number: option ? `${option.tag}-${year}-${padded}` : '',
            title: option?.next_title ?? '',
        };
    }

    function changeType(value: string) {
        if (isEdit) {
            form.setData('type', value);

            return;
        }

        const year = Number(form.data.legislative_year) || new Date().getFullYear();

        form.setData((data) => ({
            ...data,
            type: value,
            ...assignedIdentity(value, year),
        }));
        form.clearErrors('type');
        form.clearErrors('session_number');
        form.clearErrors('title');
    }

    function changeLegislativeYear(year: number) {
        if (isEdit) {
            form.setData('legislative_year', year);

            return;
        }

        form.setData((data) => ({
            ...data,
            legislative_year: year,
            ...assignedIdentity(data.type, year),
        }));
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        const payload = {
            ...form.data,
            scheduled_start_at: form.data.scheduled_start_at || null,
            scheduled_end_at: form.data.scheduled_end_at || null,
            presiding_officer_id: form.data.presiding_officer_id || null,
            secretary_id: form.data.secretary_id || null,
            seated_member_count: form.data.seated_member_count === '' ? null : Number(form.data.seated_member_count),
            notes: form.data.notes || null,
        };

        form.transform(() => payload);

        if (isEdit && session) {
            form.put(`/sessions/${session.id}`);
        } else {
            form.post('/sessions');
        }
    }

    const cancelHref = session ? `/sessions/${session.id}` : '/sessions';

    return (
        <AppLayout title={isEdit ? t('sessions.edit') : t('sessions.create')}>
            <div className="mx-auto flex max-w-3xl flex-col gap-5">
                <PageHeader
                    title={isEdit ? t('sessions.edit') : t('sessions.create')}
                    description={isEdit ? t('sessions.edit_subtitle') : t('sessions.create_subtitle')}
                />

                <Panel>
                    <form onSubmit={submit}>
                        <PanelSection className="space-y-4">
                            <h2 className="text-sm font-semibold text-ink">{t('sessions.form_identity')}</h2>
                            <p className="text-sm text-ink-muted">{t('sessions.form_identity_hint')}</p>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field id="type" label={t('sessions.type')} error={form.errors.type} required>
                                    <SimpleSelect
                                        id="type"
                                        value={form.data.type}
                                        onValueChange={changeType}
                                        aria-invalid={Boolean(form.errors.type)}
                                        items={[
                                            { value: 'regular', label: t('sessions.type_regular') },
                                            { value: 'special', label: t('sessions.type_special') },
                                            { value: 'committee-hearing', label: t('sessions.type_committee') },
                                            { value: 'public-hearing', label: t('sessions.type_public') },
                                        ]}
                                    />
                                </Field>
                                <Field
                                    id="session_number"
                                    label={t('sessions.number')}
                                    hint={isEdit ? t('sessions.number_hint') : t('sessions.number_assigned_hint')}
                                    error={form.errors.session_number}
                                    required
                                >
                                    <Input
                                        id="session_number"
                                        value={form.data.session_number}
                                        onChange={
                                            isEdit
                                                ? (event) => form.setData('session_number', event.target.value)
                                                : undefined
                                        }
                                        readOnly={!isEdit}
                                        aria-readonly={isEdit ? undefined : true}
                                        required
                                        autoComplete="off"
                                        aria-invalid={Boolean(form.errors.session_number)}
                                        className={isEdit ? 'font-mono' : 'bg-canvas-sunk font-mono'}
                                        placeholder={t('sessions.number_placeholder')}
                                    />
                                </Field>
                            </div>

                            <Field
                                id="title"
                                label={t('sessions.title_label')}
                                hint={isEdit ? undefined : t('sessions.title_assigned_hint')}
                                error={form.errors.title}
                                required
                            >
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={
                                        isEdit ? (event) => form.setData('title', event.target.value) : undefined
                                    }
                                    readOnly={!isEdit}
                                    aria-readonly={isEdit ? undefined : true}
                                    required
                                    aria-invalid={Boolean(form.errors.title)}
                                    className={isEdit ? undefined : 'bg-canvas-sunk'}
                                    placeholder={t('sessions.title_placeholder')}
                                />
                            </Field>
                        </PanelSection>

                        <PanelSection className="space-y-4">
                            <h2 className="text-sm font-semibold text-ink">{t('sessions.form_when')}</h2>
                            <p className="text-sm text-ink-muted">{t('sessions.form_when_hint')}</p>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="scheduled_start_at"
                                    label={t('sessions.scheduled_start')}
                                    error={form.errors.scheduled_start_at}
                                >
                                    <Input
                                        id="scheduled_start_at"
                                        type="datetime-local"
                                        value={form.data.scheduled_start_at}
                                        onChange={(event) => form.setData('scheduled_start_at', event.target.value)}
                                        aria-invalid={Boolean(form.errors.scheduled_start_at)}
                                    />
                                </Field>
                                <Field
                                    id="scheduled_end_at"
                                    label={t('sessions.scheduled_end')}
                                    error={form.errors.scheduled_end_at}
                                >
                                    <Input
                                        id="scheduled_end_at"
                                        type="datetime-local"
                                        value={form.data.scheduled_end_at}
                                        onChange={(event) => form.setData('scheduled_end_at', event.target.value)}
                                        aria-invalid={Boolean(form.errors.scheduled_end_at)}
                                    />
                                </Field>
                                <Field id="venue" label={t('sessions.venue')} error={form.errors.venue}>
                                    <Input
                                        id="venue"
                                        value={form.data.venue}
                                        onChange={(event) => form.setData('venue', event.target.value)}
                                        aria-invalid={Boolean(form.errors.venue)}
                                        placeholder={t('sessions.venue_placeholder')}
                                    />
                                </Field>
                                <Field
                                    id="legislative_year"
                                    label={t('sessions.legislative_year')}
                                    error={form.errors.legislative_year}
                                >
                                    <Input
                                        id="legislative_year"
                                        type="number"
                                        min={2000}
                                        max={2100}
                                        value={form.data.legislative_year}
                                        onChange={(event) =>
                                            changeLegislativeYear(Number(event.target.value))
                                        }
                                        aria-invalid={Boolean(form.errors.legislative_year)}
                                    />
                                </Field>
                            </div>
                        </PanelSection>

                        <PanelSection className="space-y-4">
                            <h2 className="text-sm font-semibold text-ink">{t('sessions.form_officers')}</h2>
                            <p className="text-sm text-ink-muted">{t('sessions.form_officers_hint')}</p>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="presiding_officer_id"
                                    label={t('sessions.presiding_officer')}
                                    error={form.errors.presiding_officer_id}
                                >
                                    <SimpleSelect
                                        id="presiding_officer_id"
                                        value={form.data.presiding_officer_id}
                                        onValueChange={(value) => form.setData('presiding_officer_id', value)}
                                        noneLabel={t('sessions.none')}
                                        items={users.map((user) => ({ value: user.id, label: user.display_name }))}
                                    />
                                </Field>
                                <Field id="secretary_id" label={t('sessions.secretary')} error={form.errors.secretary_id}>
                                    <SimpleSelect
                                        id="secretary_id"
                                        value={form.data.secretary_id}
                                        onValueChange={(value) => form.setData('secretary_id', value)}
                                        noneLabel={t('sessions.none')}
                                        items={users.map((user) => ({ value: user.id, label: user.display_name }))}
                                    />
                                </Field>
                            </div>
                        </PanelSection>

                        <PanelSection className="space-y-4">
                            <h2 className="text-sm font-semibold text-ink">{t('sessions.form_chamber')}</h2>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="seated_member_count"
                                    label={t('sessions.seated_members')}
                                    hint={t('sessions.seated_members_hint')}
                                    error={form.errors.seated_member_count}
                                >
                                    <Input
                                        id="seated_member_count"
                                        type="number"
                                        min={1}
                                        value={form.data.seated_member_count}
                                        onChange={(event) => form.setData('seated_member_count', event.target.value)}
                                        aria-invalid={Boolean(form.errors.seated_member_count)}
                                    />
                                </Field>
                            </div>

                            <label htmlFor="is_public" className="flex items-start gap-2.5">
                                <Checkbox
                                    id="is_public"
                                    className="mt-0.5"
                                    checked={form.data.is_public}
                                    onChange={(event) => form.setData('is_public', event.target.checked)}
                                />
                                <span>
                                    <span className="block text-sm font-medium text-ink">{t('sessions.is_public')}</span>
                                    <span className="block text-xs text-ink-muted">{t('sessions.is_public_hint')}</span>
                                </span>
                            </label>

                            <Field id="notes" label={t('sessions.notes')} error={form.errors.notes}>
                                <Textarea
                                    id="notes"
                                    rows={4}
                                    value={form.data.notes}
                                    onChange={(event) => form.setData('notes', event.target.value)}
                                    aria-invalid={Boolean(form.errors.notes)}
                                />
                            </Field>
                        </PanelSection>

                        <PanelFoot>
                            <Button type="submit" variant="primary" disabled={form.processing}>
                                {form.processing ? t('sessions.saving') : t('sessions.save')}
                            </Button>
                            <Button variant="ghost" asChild>
                                <Link href={cancelHref}>{t('sessions.cancel')}</Link>
                            </Button>
                        </PanelFoot>
                    </form>
                </Panel>
            </div>
        </AppLayout>
    );
}

function toDatetimeLocal(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}
