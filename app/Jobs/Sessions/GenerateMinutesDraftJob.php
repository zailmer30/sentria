<?php

namespace App\Jobs\Sessions;

use App\Contracts\AI\MinutesGenerationService;
use App\Models\LegislativeSession;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateMinutesDraftJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $sessionId,
    ) {}

    public function handle(MinutesGenerationService $generator): void
    {
        $session = LegislativeSession::query()->find($this->sessionId);

        if ($session === null) {
            return;
        }

        $actor = $this->resolveActor($session);

        if ($actor === null || ! $actor->can('minutes.generateDraft')) {
            return;
        }

        $generator->draftFromSession($actor, $session);
    }

    private function resolveActor(LegislativeSession $session): ?User
    {
        if ($session->secretary_id !== null) {
            $secretary = User::query()->find($session->secretary_id);

            if ($secretary !== null && $secretary->can('minutes.generateDraft')) {
                return $secretary;
            }
        }

        return User::query()
            ->permission('minutes.generateDraft')
            ->where('is_active', true)
            ->orderBy('created_at')
            ->first();
    }
}
