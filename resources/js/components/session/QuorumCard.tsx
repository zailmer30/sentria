import { Gauge } from '@/components/ui/gauge';
import { Panel } from '@/components/ui/panel';
import { StatusChip } from '@/components/ui/status';
import { UserAvatar } from '@/components/users/UserAvatar';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Users } from 'lucide-react';

/**
 * Quorum as one card: the count inside a ring filled toward the number
 * required, the faces of the members counted present, and the rule that sets
 * the threshold. The digits are the record; the ring and faces only make them
 * faster to read.
 *
 * `plate` draws the same card inside the navy chamber plate, without its own
 * frame, so it can sit in the plate's fact strip.
 *
 * The system reports; the presiding officer decides. This never says "proceed".
 */

export type QuorumPresentMember = {
    id: string;
    display_name: string | null;
    avatar_url: string | null;
};

export type QuorumSummary = {
    seated_count: number;
    present_count: number;
    required: number;
    met: boolean;
    rule?: string;
    present_members?: QuorumPresentMember[];
};

const MAX_FACES = 8;

export function QuorumCard({
    quorum,
    tone = 'surface',
    raised = false,
    className,
}: {
    quorum: QuorumSummary;
    tone?: 'surface' | 'plate';
    raised?: boolean;
    className?: string;
}) {
    const { t } = useTranslations();
    const plate = tone === 'plate';
    const members = quorum.present_members ?? [];
    const shown = members.slice(0, MAX_FACES);
    const overflow = members.length - shown.length;
    const seated = quorum.seated_count || quorum.required;

    const requirement =
        quorum.rule === 'two_thirds_of_seated'
            ? t('sessions.quorum_requirement_two_thirds', { seated })
            : quorum.rule === 'fixed'
              ? t('sessions.quorum_requirement_fixed', { required: quorum.required })
              : t('sessions.quorum_requirement', { seated });

    const faceBorder = plate ? 'border-[var(--color-floor-plate)]' : 'border-surface';

    const content = (
        <>
            <div className="flex items-center justify-between gap-3">
                {plate ? (
                    <h2 className="flex items-center gap-1.5 text-2xs font-semibold tracking-[0.06em] text-floor-ink-faint uppercase">
                        <Users aria-hidden="true" strokeWidth={1.75} className="size-3.5 shrink-0" />
                        {t('sessions.quorum')}
                    </h2>
                ) : (
                    <h2 className="text-md font-semibold text-ink">{t('sessions.quorum')}</h2>
                )}
                <StatusChip tone={quorum.met ? 'final' : 'live'} size="sm">
                    {quorum.met ? t('sessions.quorum_met') : t('sessions.quorum_not_met')}
                </StatusChip>
            </div>

            <div className={cn('flex items-center', plate ? 'mt-3 gap-4' : 'mt-4 gap-5')}>
                <Gauge
                    size={plate ? 88 : 104}
                    sweep={360}
                    value={quorum.present_count}
                    max={Math.max(quorum.required, 1)}
                    tone={quorum.met ? 'success' : 'live'}
                    trackClassName={plate ? 'stroke-[var(--color-floor-line)]' : undefined}
                    label={t('sessions.quorum_meter_label', {
                        present: quorum.present_count,
                        required: quorum.required,
                        seated,
                    })}
                >
                    <span
                        className={cn(
                            'font-mono leading-none font-medium tracking-[-0.02em]',
                            plate ? 'text-2xl text-floor-ink' : 'text-figure-sm text-ink',
                        )}
                    >
                        {quorum.present_count}
                    </span>
                    <span className={cn('font-mono text-2xs', plate ? 'text-floor-ink-faint' : 'text-ink-faint')}>
                        {t('sessions.quorum_of', { required: quorum.required })}
                    </span>
                </Gauge>

                <div className="flex min-w-0 flex-1 flex-col gap-2.5">
                    {shown.length > 0 ? (
                        <ul aria-label={t('sessions.quorum_present_members')} className="flex flex-wrap items-center">
                            {shown.map((member, index) => (
                                <li key={member.id} className={cn(index > 0 && '-ml-2')}>
                                    <UserAvatar
                                        name={member.display_name}
                                        src={member.avatar_url}
                                        alt={member.display_name ?? ''}
                                        className={cn('size-8 border-2', faceBorder)}
                                    />
                                    <span className="sr-only">{member.display_name}</span>
                                </li>
                            ))}
                            {overflow > 0 ? (
                                <li className="-ml-2">
                                    <span
                                        className={cn(
                                            'flex size-8 items-center justify-center rounded-full border-2 font-mono text-2xs font-semibold',
                                            faceBorder,
                                            plate ? 'bg-floor-sunk text-floor-ink-muted' : 'bg-canvas-sunk text-ink-muted',
                                        )}
                                    >
                                        <span aria-hidden="true">+{overflow}</span>
                                        <span className="sr-only">{t('sessions.quorum_more_members', { count: overflow })}</span>
                                    </span>
                                </li>
                            ) : null}
                        </ul>
                    ) : (
                        <p className={cn('text-sm', plate ? 'text-floor-ink-faint' : 'text-ink-faint')}>
                            {t('sessions.quorum_no_members_present')}
                        </p>
                    )}

                    <p className={cn(plate ? 'text-xs text-floor-ink-muted' : 'text-sm text-ink-muted')}>{requirement}</p>
                </div>
            </div>
        </>
    );

    if (plate) {
        return <section className={cn('min-w-0 px-4 py-3.5', className)}>{content}</section>;
    }

    return (
        <Panel as="section" raised={raised} className={cn('px-5 py-4', className)}>
            {content}
        </Panel>
    );
}
