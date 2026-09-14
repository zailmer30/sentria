<?php

namespace App\Console\Commands;

use App\Models\AgendaItem;
use App\Models\FloorRecognitionRequest;
use App\Models\LegislativeSession;
use App\Models\Motion;
use App\Models\Vote;
use App\States\Session\InSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reset a live sitting back to a clean in-session start: first agenda item on
 * the floor, no open ballot, no projected document, no test votes/motions.
 */
class ResetLiveSessionFloorCommand extends Command
{
    protected $signature = 'sessions:reset-live-floor
                            {session? : Session ULID (defaults to the latest in-session sitting)}
                            {--keep-motions : Leave motions in place}';

    protected $description = 'Reset live session floor state to a clean post-start sitting';

    public function handle(): int
    {
        $sessionId = $this->argument('session');

        $session = $sessionId
            ? LegislativeSession::query()->whereKey($sessionId)->first()
            : LegislativeSession::query()
                ->where('status', InSession::$name)
                ->orderByDesc('actual_start_at')
                ->first();

        if ($session === null) {
            $this->error('No in-session sitting found.');

            return self::FAILURE;
        }

        if (! $session->status instanceof InSession) {
            $this->error("Session {$session->getKey()} is not in-session (status: {$session->status}).");

            return self::FAILURE;
        }

        $this->info("Resetting: {$session->title} ({$session->getKey()})");

        DB::transaction(function () use ($session): void {
            $attrs = $session->getAttributes();

            if (array_key_exists('hall_display_stage', $attrs)) {
                $payload = [
                    'hall_display_stage' => 'item',
                    'hall_display_agenda_item_id' => null,
                ];

                if (array_key_exists('hall_display_view', $attrs)) {
                    $payload['hall_display_view'] = null;
                }

                $session->forceFill($payload)->save();
            }

            AgendaItem::query()
                ->where('session_id', $session->getKey())
                ->update([
                    'voting_open_at' => null,
                    'voting_opened_at' => null,
                    'voting_closed_at' => null,
                    'silent_voting_rounds' => null,
                    'status' => 'pending',
                    'started_at' => null,
                    'completed_at' => null,
                ]);

            // Votes table is append-only — never delete ballots.
            if (! $this->option('keep-motions')) {
                Motion::query()
                    ->where('session_id', $session->getKey())
                    ->whereNotIn('status', ['withdrawn', 'carried', 'lost', 'ruled_out'])
                    ->update([
                        'status' => 'withdrawn',
                        'disposed_at' => now(),
                    ]);
            }

            FloorRecognitionRequest::query()
                ->where('session_id', $session->getKey())
                ->whereIn('status', ['pending', 'recognized'])
                ->update([
                    'status' => 'cancelled',
                    'resolved_at' => now(),
                ]);

            $first = AgendaItem::query()
                ->where('session_id', $session->getKey())
                ->orderBy('position')
                ->first();

            if ($first instanceof AgendaItem) {
                $first->forceFill([
                    'status' => 'in-progress',
                    'started_at' => now()->subMinutes(12),
                ])->save();
            }
        });

        $session->refresh();
        $items = $session->agendaItems()->orderBy('position')->get();

        $this->table(
            ['#', 'Title', 'Status', 'Voting'],
            $items->map(fn (AgendaItem $item): array => [
                $item->item_number,
                $item->title,
                $item->status,
                $item->voting_open_at ? 'open' : 'closed',
            ])->all(),
        );

        $this->line('Hall display: '.json_encode($session->hallDisplayState()));
        $this->line('Votes kept (append-only): '.Vote::query()->where('session_id', $session->getKey())->count());
        $this->line('Open motions: '.Motion::query()->where('session_id', $session->getKey())->whereNotIn('status', ['withdrawn', 'carried', 'lost', 'ruled_out'])->count());
        $this->info('Done. Reload the secretariat console and session dashboard.');

        return self::SUCCESS;
    }
}
