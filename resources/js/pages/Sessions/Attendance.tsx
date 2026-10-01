import type { QuorumSummary } from '@/components/session/QuorumCard';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Panel, PanelHead, PanelTitle } from '@/components/ui/panel';
import { SimpleSelect } from '@/components/ui/select';
import { UserAvatar } from '@/components/users/UserAvatar';
import { useSessionEcho } from '@/hooks/useSessionEcho';
import SessionLayout from '@/layouts/SessionLayout';
import { useFormatters } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { attendanceTakesRemarks, withHonorific } from '@/lib/sessionFloor';
import { cn } from '@/lib/utils';
import { router, useForm } from '@inertiajs/react';
import { Calendar, Check, Clock, RotateCcw, Save, Users } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';

type AttendanceRow = {
    id: string;
    user_id: string;
    display_name: string | null;
    avatar_url?: string | null;
    position_title?: string | null;
    district?: string | null;
    status: string;
    remarks?: string | null;
};

type GuestRow = {
    id: string;
    name: string;
    organization: string | null;
    speaking_topic: string | null;
    status: string;
};

type Draft = {
    status: string;
    remarks: string;
};

type Props = {
    session: {
        id: string;
        title: string;
        session_number: string;
        status: string;
        venue: string | null;
        type_label?: string | null;
        scheduled_start_at?: string | null;
    };
    attendance: AttendanceRow[];
    quorum: QuorumSummary;
    can: { record: boolean };
    statuses: { value: string; label: string }[];
    guests: GuestRow[];
    guest_statuses: { value: string; label: string }[];
};

const ATTENDANCE_KEY: Record<string, string> = {
    present: 'sessions.attendance_present',
    late: 'sessions.attendance_late',
    absent: 'sessions.attendance_absent',
    excused: 'sessions.attendance_excused',
    'on-official-business': 'sessions.attendance_official_business',
};

const GUEST_STATUS_KEY: Record<string, string> = {
    invited: 'sessions.guests_status_invited',
    present: 'sessions.guests_status_present',
    'did-not-appear': 'sessions.guests_status_did_not_appear',
};

function normalizeRemarks(value: string | null | undefined): string {
    return (value ?? '').trim().replace(/\s+/g, ' ');
}

function draftsFrom(rows: AttendanceRow[]): Record<string, Draft> {
    return Object.fromEntries(
        rows.map((row) => [row.user_id, { status: row.status, remarks: normalizeRemarks(row.remarks) }]),
    );
}

function countsTowardQuorum(status: string): boolean {
    return status === 'present' || status === 'late';
}

export default function SessionsAttendance({
    session,
    attendance,
    quorum,
    can,
    statuses,
    guests,
    guest_statuses,
}: Props) {
    const { t } = useTranslations();
    const { formatDateLong } = useFormatters();
    useSessionEcho(session.id, ['attendance', 'quorum', 'guests']);

    const [drafts, setDrafts] = useState<Record<string, Draft>>(() => draftsFrom(attendance));
    const [saving, setSaving] = useState(false);

    const dirty = attendance.some((row) => {
        const draft = drafts[row.user_id];

        if (!draft) {
            return false;
        }

        if (draft.status !== row.status) {
            return true;
        }

        if (!attendanceTakesRemarks(draft.status)) {
            return false;
        }

        return normalizeRemarks(draft.remarks) !== normalizeRemarks(row.remarks);
    });

    useEffect(() => {
        if (dirty || saving) {
            return;
        }

        setDrafts(draftsFrom(attendance));
    }, [attendance, dirty, saving]);

    const statusItems = statuses.map((status) => ({
        value: status.value,
        label: t(ATTENDANCE_KEY[status.value] ?? 'sessions.attendance_absent'),
    }));

    const presentCount = attendance.filter((row) => countsTowardQuorum(drafts[row.user_id]?.status ?? row.status)).length;
    const awaitingCount = Math.max(attendance.length - presentCount, 0);
    const seated = quorum.seated_count || attendance.length;
    const required = Math.max(quorum.required, 0);
    const met = required > 0 ? presentCount >= required : presentCount > 0;

    const rule =
        quorum.rule === 'two_thirds_of_seated'
            ? t('sessions.attendance_registry_rule_two_thirds', { required, seated })
            : quorum.rule === 'fixed'
              ? t('sessions.attendance_registry_rule_fixed', { required, seated })
              : t('sessions.attendance_registry_rule', { required, seated });

    const headline =
        presentCount === 0
            ? t('sessions.attendance_registry_none')
            : met
              ? t('sessions.attendance_registry_met')
              : t('sessions.attendance_registry_partial', { present: presentCount, required });

    const sessionWhen = session.scheduled_start_at ? formatDateLong(session.scheduled_start_at) : null;
    const sessionLine = [session.title, sessionWhen].filter(Boolean).join(' · ');

    function patchDraft(userId: string, patch: Partial<Draft>) {
        setDrafts((current) => {
            const existing = current[userId] ?? { status: 'absent', remarks: '' };

            return { ...current, [userId]: { ...existing, ...patch } };
        });
    }

    function markAllPresent() {
        setDrafts((current) => {
            const next = { ...current };

            for (const row of attendance) {
                next[row.user_id] = { status: 'present', remarks: '' };
            }

            return next;
        });
    }

    function discard() {
        setDrafts(draftsFrom(attendance));
    }

    function save() {
        if (!dirty || saving || !can.record) {
            return;
        }

        const records = attendance.flatMap((row) => {
            const draft = drafts[row.user_id];

            if (!draft) {
                return [];
            }

            const remarks = attendanceTakesRemarks(draft.status) ? normalizeRemarks(draft.remarks) : '';
            const changed = draft.status !== row.status || remarks !== normalizeRemarks(row.remarks);

            if (!changed) {
                return [];
            }

            return [
                {
                    user_id: row.user_id,
                    status: draft.status,
                    remarks: remarks === '' ? null : remarks,
                },
            ];
        });

        if (records.length === 0) {
            return;
        }

        setSaving(true);
        router.put(
            `/sessions/${session.id}/attendance`,
            { records },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    }

    return (
        <SessionLayout
            title={t('sessions.attendance_registry')}
            sessionTitle={session.title}
            sessionId={session.id}
            sessionStatus={session.status}
            venue={session.venue}
        >
            <div className="flex flex-col gap-4">
                <header className="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 px-1">
                    <div className="min-w-0">
                        <h2 className="text-[1.75rem] leading-tight font-semibold tracking-[-0.03em] text-ink">
                            {t('sessions.attendance_registry')}
                        </h2>
                        <p className="mt-1 text-sm text-ink-muted">{t('sessions.attendance_registry_lead')}</p>
                    </div>
                    {sessionLine ? (
                        <p className="flex shrink-0 items-center gap-2 text-sm text-ink-subtle">
                            <Calendar aria-hidden="true" strokeWidth={1.75} className="size-4" />
                            <span>{sessionLine}</span>
                        </p>
                    ) : null}
                </header>

                <section className="overflow-hidden rounded-[var(--radius-lg)] border border-line bg-surface shadow-[var(--shadow-sm)]">
                    <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-line px-5 py-4">
                        <div className="min-w-0">
                            <h3 className="text-[15px] leading-5 font-semibold tracking-[-0.01em] text-ink">
                                {t('sessions.attendance_quorum_summary')}
                            </h3>
                            <p className="mt-0.5 text-sm text-ink-subtle">{t('sessions.attendance_quorum_live')}</p>
                        </div>
                        <span
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium',
                                met
                                    ? 'border-[var(--color-success-line)] bg-success-soft text-success'
                                    : 'border-[var(--color-live-line)] bg-live-soft text-[var(--color-live-ink)]',
                            )}
                        >
                            <span aria-hidden="true" className={cn('size-1.5 rounded-full', met ? 'bg-success' : 'bg-live')} />
                            {met ? t('sessions.quorum_met') : t('sessions.quorum_not_met')}
                        </span>
                    </div>

                    <div className="p-4">
                        <div className="rounded-[var(--radius-lg)] border border-line bg-surface px-5 py-5 sm:px-6">
                            <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:gap-6">
                                <QuorumRing
                                    value={presentCount}
                                    max={Math.max(required, 1)}
                                    met={met}
                                    ofLabel={t('sessions.quorum_of', { required })}
                                    label={t('sessions.quorum_meter_label', {
                                        present: presentCount,
                                        required,
                                        seated,
                                    })}
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="text-[15px] leading-snug font-semibold tracking-[-0.01em] text-ink">{headline}</p>
                                    <p className="mt-1.5 max-w-3xl text-sm leading-relaxed text-ink-muted">{rule}</p>
                                    <div className="mt-3.5 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-sm text-ink-muted">
                                        <span className="inline-flex items-center gap-1.5">
                                            <Users aria-hidden="true" strokeWidth={1.75} className="size-4 text-ink-faint" />
                                            {t('sessions.attendance_present_count', { count: presentCount })}
                                        </span>
                                        <span className="inline-flex items-center gap-1.5">
                                            <Clock aria-hidden="true" strokeWidth={1.75} className="size-4 text-ink-faint" />
                                            {t('sessions.attendance_awaiting', { count: awaitingCount })}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="overflow-hidden rounded-[var(--radius-lg)] border border-line bg-surface shadow-[var(--shadow-sm)]">
                    <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-b border-line px-5 py-4">
                        <div className="min-w-0">
                            <h3 className="text-[15px] leading-5 font-semibold tracking-[-0.01em] text-ink">
                                {t('sessions.attendance_members')}
                            </h3>
                            <p className="mt-0.5 text-sm text-ink-subtle">
                                {attendance.length === 1
                                    ? t('sessions.attendance_roll_one')
                                    : t('sessions.attendance_roll', { count: attendance.length })}
                            </p>
                        </div>
                        {can.record ? (
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={markAllPresent}
                                disabled={saving}
                                className="h-9 border-line bg-surface px-3 font-medium text-ink shadow-[var(--shadow-xs)] hover:bg-canvas"
                            >
                                <Check aria-hidden="true" strokeWidth={2} />
                                {t('sessions.attendance_mark_all')}
                            </Button>
                        ) : null}
                    </div>

                    {attendance.length === 0 ? (
                        <p className="px-5 py-8 text-sm text-ink-muted">{t('sessions.no_members_present')}</p>
                    ) : (
                        <ul className="divide-y divide-line">
                            {attendance.map((row) => {
                                const draft = drafts[row.user_id] ?? { status: row.status, remarks: normalizeRemarks(row.remarks) };
                                const name = withHonorific(row.display_name) ?? row.display_name ?? '';
                                const detail = [row.position_title, row.district].filter(Boolean).join(' · ');

                                return (
                                    <li
                                        key={row.id}
                                        className="grid items-center gap-3 px-5 py-4 md:grid-cols-[minmax(15rem,0.95fr)_minmax(12rem,1.25fr)_10.5rem] md:gap-4"
                                    >
                                        <div className="flex min-w-0 items-center gap-3">
                                            <UserAvatar name={row.display_name} src={row.avatar_url} className="size-10 shrink-0" />
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-semibold text-ink">{name}</p>
                                                {detail ? <p className="truncate text-xs text-ink-subtle">{detail}</p> : null}
                                            </div>
                                        </div>
                                        <Input
                                            value={draft.remarks}
                                            maxLength={1000}
                                            disabled={!can.record || saving}
                                            onChange={(event) => patchDraft(row.user_id, { remarks: event.target.value })}
                                            placeholder={t('sessions.attendance_absent_reason')}
                                            aria-label={t('sessions.attendance_remarks', { name })}
                                            className="h-10 border-line bg-canvas px-3 text-sm placeholder:text-ink-faint disabled:bg-canvas"
                                        />
                                        {can.record ? (
                                            <SimpleSelect
                                                value={draft.status}
                                                onValueChange={(status) =>
                                                    patchDraft(row.user_id, {
                                                        status,
                                                        ...(attendanceTakesRemarks(status) ? {} : { remarks: '' }),
                                                    })
                                                }
                                                aria-label={t('sessions.attendance_status', { name })}
                                                disabled={saving}
                                                className="h-10 border-line bg-surface"
                                                items={statusItems}
                                            />
                                        ) : (
                                            <span className="flex h-10 items-center rounded-[var(--radius-md)] border border-line bg-surface px-3 text-sm text-ink">
                                                {t(ATTENDANCE_KEY[draft.status] ?? 'sessions.attendance_absent')}
                                            </span>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    {can.record ? (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line bg-surface-alt px-5 py-3">
                            <p className="text-sm text-ink-subtle">
                                {dirty ? t('sessions.attendance_dirty') : t('sessions.attendance_clean')}
                            </p>
                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={discard}
                                    disabled={!dirty || saving}
                                    className="h-9 px-2.5 text-ink-muted hover:bg-transparent disabled:opacity-40"
                                >
                                    <RotateCcw aria-hidden="true" strokeWidth={1.75} />
                                    {t('sessions.attendance_discard')}
                                </Button>
                                <Button
                                    type="button"
                                    onClick={save}
                                    disabled={!dirty || saving}
                                    className="h-9 border-[#3d4553] bg-[#3d4553] px-3.5 text-white shadow-none hover:border-[#323846] hover:bg-[#323846] disabled:border-[#3d4553] disabled:bg-[#3d4553] disabled:text-white disabled:opacity-100"
                                >
                                    <Save aria-hidden="true" strokeWidth={1.75} />
                                    {t('sessions.attendance_save')}
                                </Button>
                            </div>
                        </div>
                    ) : null}
                </section>

                <GuestPanel sessionId={session.id} guests={guests} statuses={guest_statuses} canRecord={can.record} />
            </div>
        </SessionLayout>
    );
}

function QuorumRing({
    value,
    max,
    met,
    ofLabel,
    label,
}: {
    value: number;
    max: number;
    met: boolean;
    ofLabel: string;
    label: string;
}) {
    const radius = 36;
    const circumference = 2 * Math.PI * radius;
    const ratio = Math.min(Math.max(value / Math.max(max, 1), 0), 1);
    const tone = met ? 'var(--color-success)' : 'var(--color-live)';
    const angle = -Math.PI / 2 + ratio * Math.PI * 2;
    const dotX = 50 + Math.cos(angle) * radius;
    const dotY = 50 + Math.sin(angle) * radius;

    return (
        <div className="relative size-[88px] shrink-0" role="img" aria-label={`${label}: ${value} of ${max}`}>
            <svg viewBox="0 0 100 100" className="size-full" aria-hidden="true">
                <circle cx="50" cy="50" r={radius} fill="none" stroke="var(--color-chart-track)" strokeWidth="7" />
                {ratio > 0 ? (
                    <circle
                        cx="50"
                        cy="50"
                        r={radius}
                        fill="none"
                        stroke={tone}
                        strokeWidth="7"
                        strokeLinecap="round"
                        strokeDasharray={`${circumference * ratio} ${circumference}`}
                        transform="rotate(-90 50 50)"
                    />
                ) : null}
                <circle cx={dotX} cy={dotY} r="4.25" fill={tone} />
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span className="text-[1.75rem] leading-none font-semibold tracking-[-0.04em] text-ink">{value}</span>
                <span className="mt-1 text-xs text-ink-faint">{ofLabel}</span>
            </div>
        </div>
    );
}

function GuestPanel({
    sessionId,
    guests,
    statuses,
    canRecord,
}: {
    sessionId: string;
    guests: GuestRow[];
    statuses: { value: string; label: string }[];
    canRecord: boolean;
}) {
    const { t } = useTranslations();
    const invited = guests.filter((guest) => guest.status === 'invited').length;
    const present = guests.filter((guest) => guest.status === 'present').length;
    const missed = guests.filter((guest) => guest.status === 'did-not-appear').length;
    const statusItems = statuses.map((status) => ({
        value: status.value,
        label: t(GUEST_STATUS_KEY[status.value] ?? 'sessions.guests_status_invited'),
    }));

    return (
        <Panel as="section">
            <PanelHead>
                <PanelTitle>{t('sessions.guests')}</PanelTitle>
                <p className="text-sm text-ink-muted">
                    {t('sessions.guests_tally', { invited, present, missed })}
                </p>
            </PanelHead>
            {canRecord ? <GuestAddForm sessionId={sessionId} /> : null}
            {guests.length > 0 ? (
                <ul className="divide-y divide-line">
                    {guests.map((guest) => (
                        <GuestListItem
                            key={guest.id}
                            sessionId={sessionId}
                            guest={guest}
                            canRecord={canRecord}
                            statusItems={statusItems}
                        />
                    ))}
                </ul>
            ) : null}
        </Panel>
    );
}

function GuestAddForm({ sessionId }: { sessionId: string }) {
    const { t } = useTranslations();
    const form = useForm({
        name: '',
        organization: '',
        speaking_topic: '',
    });
    const nameMissing = form.data.name.trim() === '';

    function submit(event: FormEvent) {
        event.preventDefault();

        if (nameMissing) {
            return;
        }

        form.post(`/sessions/${sessionId}/guests`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <form onSubmit={submit} className="flex flex-col gap-2 border-b border-line px-4 py-3">
            <div className="flex flex-wrap items-center gap-2">
                <Input
                    value={form.data.name}
                    maxLength={200}
                    onChange={(event) => form.setData('name', event.target.value)}
                    placeholder={t('sessions.guests_name')}
                    aria-label={t('sessions.guests_name')}
                    aria-invalid={form.errors.name ? true : undefined}
                    className="min-w-40 flex-1"
                />
                <Input
                    value={form.data.organization}
                    maxLength={200}
                    onChange={(event) => form.setData('organization', event.target.value)}
                    placeholder={t('sessions.guests_organization')}
                    aria-label={t('sessions.guests_organization')}
                    className="min-w-40 flex-1"
                />
                <Input
                    value={form.data.speaking_topic}
                    maxLength={500}
                    onChange={(event) => form.setData('speaking_topic', event.target.value)}
                    placeholder={t('sessions.guests_topic')}
                    aria-label={t('sessions.guests_topic')}
                    className="min-w-48 flex-[1.4]"
                />
                <Button type="submit" variant="primary" disabled={nameMissing || form.processing}>
                    {t('sessions.guests_add')}
                </Button>
            </div>
            {form.errors.name ? <p className="text-sm text-critical">{form.errors.name}</p> : null}
        </form>
    );
}

function GuestListItem({
    sessionId,
    guest,
    canRecord,
    statusItems,
}: {
    sessionId: string;
    guest: GuestRow;
    canRecord: boolean;
    statusItems: { value: string; label: string }[];
}) {
    const { t } = useTranslations();
    const statusLabel = t(GUEST_STATUS_KEY[guest.status] ?? 'sessions.guests_status_invited');
    const subtitle = [guest.organization, guest.speaking_topic].filter(Boolean).join(' · ');

    if (!canRecord) {
        return (
            <li className="flex flex-col gap-1 px-4 py-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="min-w-0 truncate text-sm font-medium text-ink">{guest.name}</p>
                    <span className="text-sm font-medium text-ink-muted">{statusLabel}</span>
                </div>
                {subtitle !== '' ? <p className="text-xs text-ink-muted">{subtitle}</p> : null}
            </li>
        );
    }

    return <GuestEditor sessionId={sessionId} guest={guest} statusItems={statusItems} />;
}

function GuestEditor({
    sessionId,
    guest,
    statusItems,
}: {
    sessionId: string;
    guest: GuestRow;
    statusItems: { value: string; label: string }[];
}) {
    const { t } = useTranslations();
    const savedName = guest.name;
    const savedOrganization = (guest.organization ?? '').trim();
    const savedTopic = (guest.speaking_topic ?? '').trim();
    const [name, setName] = useState(savedName);
    const [organization, setOrganization] = useState(savedOrganization);
    const [topic, setTopic] = useState(savedTopic);
    const focused = useRef(0);

    useEffect(() => {
        if (focused.current === 0) {
            setName(guest.name);
            setOrganization((guest.organization ?? '').trim());
            setTopic((guest.speaking_topic ?? '').trim());
        }
    }, [guest.name, guest.organization, guest.speaking_topic]);

    function persist(status: string) {
        const nextName = name.trim();

        if (nextName === '') {
            setName(savedName);

            return;
        }

        const nextOrganization = organization.trim();
        const nextTopic = topic.trim();

        if (
            nextName === savedName &&
            nextOrganization === savedOrganization &&
            nextTopic === savedTopic &&
            status === guest.status
        ) {
            setName(savedName);
            setOrganization(savedOrganization);
            setTopic(savedTopic);

            return;
        }

        router.put(
            `/sessions/${sessionId}/guests/${guest.id}`,
            {
                name: nextName,
                organization: nextOrganization,
                speaking_topic: nextTopic,
                status,
            },
            { preserveScroll: true },
        );
    }

    function remove() {
        const organizationName = savedOrganization;
        const message =
            organizationName !== ''
                ? t('sessions.guests_remove_confirm_org', { name: savedName, organization: organizationName })
                : t('sessions.guests_remove_confirm', { name: savedName });

        if (!window.confirm(message)) {
            return;
        }

        router.delete(`/sessions/${sessionId}/guests/${guest.id}`, { preserveScroll: true });
    }

    return (
        <li className="flex flex-col gap-2 px-4 py-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <Input
                    value={name}
                    maxLength={200}
                    onChange={(event) => setName(event.target.value)}
                    onFocus={() => {
                        focused.current += 1;
                    }}
                    onBlur={() => {
                        focused.current = Math.max(0, focused.current - 1);
                        persist(guest.status);
                    }}
                    aria-label={t('sessions.guests_name')}
                    className="min-w-40 max-w-md flex-1"
                />
                <div className="flex items-center gap-2">
                    <SimpleSelect
                        value={guest.status}
                        onValueChange={(status) => persist(status)}
                        aria-label={t('sessions.guests_status', { name: savedName })}
                        className="w-52"
                        items={statusItems}
                    />
                    <Button type="button" variant="ghost" size="sm" onClick={remove}>
                        {t('sessions.guests_remove')}
                    </Button>
                </div>
            </div>
            <div className="flex flex-wrap gap-2">
                <Input
                    value={organization}
                    maxLength={200}
                    onChange={(event) => setOrganization(event.target.value)}
                    onFocus={() => {
                        focused.current += 1;
                    }}
                    onBlur={() => {
                        focused.current = Math.max(0, focused.current - 1);
                        persist(guest.status);
                    }}
                    placeholder={t('sessions.guests_organization')}
                    aria-label={t('sessions.guests_organization')}
                    className="min-w-40 max-w-xs flex-1"
                />
                <Input
                    value={topic}
                    maxLength={500}
                    onChange={(event) => setTopic(event.target.value)}
                    onFocus={() => {
                        focused.current += 1;
                    }}
                    onBlur={() => {
                        focused.current = Math.max(0, focused.current - 1);
                        persist(guest.status);
                    }}
                    placeholder={t('sessions.guests_topic')}
                    aria-label={t('sessions.guests_topic')}
                    className="min-w-48 max-w-xl flex-[1.4]"
                />
            </div>
        </li>
    );
}
