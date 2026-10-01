<?php

namespace App\Console\Commands;

use App\Models\LegislativeSession;
use App\Models\SessionConversation;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;

class PurgeSessionChatCommand extends Command
{
    protected $signature = 'sentria:purge-session-chat';

    protected $description = 'Hard-delete ephemeral floor chat for sittings adjourned more than 24 hours ago.';

    public function handle(AuditLogger $audit): int
    {
        $cutoff = now()->subDay();

        $sessions = LegislativeSession::query()
            ->whereNotNull('adjourned_at')
            ->where('adjourned_at', '<=', $cutoff)
            ->whereHas('conversations')
            ->get();

        $removed = 0;

        foreach ($sessions as $session) {
            $count = $session->conversations()->count();

            SessionConversation::query()
                ->where('session_id', $session->getKey())
                ->delete();

            $removed += $count;

            $audit->record(
                event: 'session-chat.purged',
                category: 'session',
                auditable: $session,
                new: ['conversation_count' => $count],
                message: 'Ephemeral floor chat purged after adjournment.',
            );
        }

        $this->info("Purged {$removed} conversation(s) from {$sessions->count()} sitting(s).");

        return self::SUCCESS;
    }
}
