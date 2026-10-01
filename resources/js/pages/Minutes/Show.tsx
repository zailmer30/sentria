import { AiContent } from '@/components/ai/AiContent';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/components/ui/page-header';
import { Panel, PanelBody, PanelHead, PanelTitle } from '@/components/ui/panel';
import { Provenance, ProvenanceField } from '@/components/ui/provenance';
import { StatusChip, toneForState } from '@/components/ui/status';
import { Toolbar } from '@/components/ui/toolbar';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';

type VoteTally = {
    agenda_item_id: string | null;
    title: string;
    voting_round: number;
    yes: number;
    no: number;
    abstain: number;
    inhibit?: number;
};

type AiSuggestion = {
    id: string;
    text: string;
    type?: string;
    confirmed: boolean;
};

type AiActionItem = {
    id: string;
    text: string;
    confirmed: boolean;
};

type MinutesDetail = {
    id: string;
    status: string;
    status_label: string;
    revision: number;
    content: string | null;
    ai_draft: string | null;
    ai_banner: string;
    ai_metadata: {
        vote_tallies?: VoteTally[];
        suggestions?: AiSuggestion[];
        action_items?: AiActionItem[];
    };
    session: { id: string; session_number: string; title: string } | null;
    preparer: string | null;
    reviewer: string | null;
    approver: string | null;
    has_ai_draft: boolean;
    is_ai_draft_status: boolean;
    allows_draft_generation?: boolean;
};

type Props = {
    minutes: MinutesDetail;
    can: Record<string, boolean>;
};

export default function MinutesShow({ minutes, can }: Props) {
    const { t } = useTranslations();
    const voteTallies = minutes.ai_metadata.vote_tallies ?? [];
    const suggestions = minutes.ai_metadata.suggestions ?? [];
    const actionItems = minutes.ai_metadata.action_items ?? [];

    function transition(path: string) {
        router.post(`/minutes/${minutes.id}/${path}`, {}, { preserveScroll: true });
    }

    function confirmSuggestion(id: string, type: 'motion' | 'action_item') {
        router.post(`/minutes/${minutes.id}/confirm-suggestion`, { suggestion_id: id, type }, { preserveScroll: true });
    }

    const showAiBanner = minutes.has_ai_draft || minutes.is_ai_draft_status;

    return (
        <AppLayout title={minutes.session?.title ?? t('minutes.title')}>
            <div className="mx-auto max-w-4xl space-y-6">
                <PageHeader
                    title={minutes.session?.title ?? t('minutes.title')}
                    description={t('minutes.revision', { number: minutes.revision })}
                    status={<StatusChip tone={toneForState(minutes.status)}>{minutes.status_label}</StatusChip>}
                    provenance={
                        minutes.session?.session_number ? (
                            <Provenance bare>
                                <ProvenanceField label={t('minutes.session')}>{minutes.session.session_number}</ProvenanceField>
                            </Provenance>
                        ) : undefined
                    }
                    actions={
                        <Toolbar label={t('minutes.title')}>
                            {can.generateDraft ? (
                                <Button variant="secondary" size="sm" onClick={() => transition('generate-draft')}>
                                    {minutes.has_ai_draft ? t('minutes.regenerate_draft') : t('minutes.generate_draft')}
                                </Button>
                            ) : null}
                            {can.acceptDraft ? (
                                <Button variant="secondary" size="sm" onClick={() => transition('accept-draft')}>
                                    {t('minutes.accept_draft')}
                                </Button>
                            ) : null}
                            {can.update ? (
                                <Button variant="secondary" size="sm" asChild>
                                    <Link href={`/minutes/${minutes.id}/edit`}>{t('minutes.edit')}</Link>
                                </Button>
                            ) : null}
                            {can.review ? (
                                <Button variant="secondary" size="sm" onClick={() => transition('review')}>
                                    {t('minutes.mark_reviewed')}
                                </Button>
                            ) : null}
                            {can.approve ? (
                                <Button variant="secondary" size="sm" onClick={() => transition('approve')}>
                                    {t('minutes.approve')}
                                </Button>
                            ) : null}
                            {can.finalize ? (
                                <Button size="sm" onClick={() => transition('finalize')}>
                                    {t('minutes.finalize')}
                                </Button>
                            ) : null}
                            {can.archive ? (
                                <Button variant="secondary" size="sm" onClick={() => transition('archive')}>
                                    {t('minutes.archive')}
                                </Button>
                            ) : null}
                            {can.download ? (
                                <Button variant="secondary" size="sm" asChild>
                                    <a href={`/minutes/${minutes.id}/pdf`}>{t('minutes.download_pdf')}</a>
                                </Button>
                            ) : null}
                        </Toolbar>
                    }
                />

                {showAiBanner ? (
                    <AiContent>
                        <p className="font-medium">{minutes.ai_banner}</p>
                    </AiContent>
                ) : null}

                {can.draftLocked ? (
                    <p className="text-sm text-ink-muted">{t('minutes.regenerate_locked')}</p>
                ) : null}

                {voteTallies.length > 0 ? (
                    <Panel>
                        <PanelHead sunk>
                            <PanelTitle>{t('minutes.official_vote_tallies')}</PanelTitle>
                        </PanelHead>
                        <PanelBody>
                            <ul className="space-y-2 text-sm">
                                {voteTallies.map((tally) => (
                                    <li key={`${tally.agenda_item_id}-${tally.voting_round}`}>
                                        <span className="font-medium text-ink">{tally.title}</span>
                                        <span className="ml-2 font-mono text-xs text-ink-muted">
                                            YES: {tally.yes} NO: {tally.no} ABSTAIN: {tally.abstain} INHIBIT:{' '}
                                            {tally.inhibit ?? 0}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </PanelBody>
                    </Panel>
                ) : null}

                {suggestions.length > 0 ? (
                    <Panel>
                        <PanelHead sunk>
                            <PanelTitle>{t('minutes.ai_suggested_motions')}</PanelTitle>
                        </PanelHead>
                        <PanelBody className="p-0">
                            <AiContent showBadge={false}>
                                <ul className="space-y-3">
                                    {suggestions.map((suggestion) => (
                                        <li
                                            key={suggestion.id}
                                            className="flex flex-wrap items-start justify-between gap-2 text-sm"
                                        >
                                            <div>
                                                <span className="font-mono text-2xs font-medium tracking-[0.04em] text-[var(--color-machine-ink)] uppercase">
                                                    {t('minutes.ai_suggested_label')}
                                                </span>{' '}
                                                {suggestion.text}
                                            </div>
                                            {!suggestion.confirmed && can.update ? (
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    onClick={() => confirmSuggestion(suggestion.id, 'motion')}
                                                >
                                                    {t('minutes.confirm_suggestion')}
                                                </Button>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                            </AiContent>
                        </PanelBody>
                    </Panel>
                ) : null}

                {actionItems.length > 0 ? (
                    <Panel>
                        <PanelHead sunk>
                            <PanelTitle>{t('minutes.ai_action_items')}</PanelTitle>
                        </PanelHead>
                        <PanelBody className="p-0">
                            <AiContent showBadge={false}>
                                <ul className="space-y-3">
                                    {actionItems.map((item) => (
                                        <li key={item.id} className="flex flex-wrap items-start justify-between gap-2 text-sm">
                                            <div>
                                                <span className="font-mono text-2xs font-medium tracking-[0.04em] text-[var(--color-machine-ink)] uppercase">
                                                    {t('minutes.ai_suggested_label')}
                                                </span>{' '}
                                                {item.text}
                                            </div>
                                            {!item.confirmed && can.update ? (
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    onClick={() => confirmSuggestion(item.id, 'action_item')}
                                                >
                                                    {t('minutes.confirm_suggestion')}
                                                </Button>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                            </AiContent>
                        </PanelBody>
                    </Panel>
                ) : null}

                <Panel>
                    <PanelBody>
                        <article className="prose prose-sm max-w-none whitespace-pre-wrap text-ink">
                            {minutes.content ?? t('minutes.no_content')}
                        </article>
                    </PanelBody>
                </Panel>
            </div>
        </AppLayout>
    );
}
