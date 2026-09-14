<?php

namespace App\Console\Commands;

use App\Services\Sessions\ChamberCaptureTokenService;
use Illuminate\Console\Command;

class IssueChamberCaptureTokenCommand extends Command
{
    protected $signature = 'sentria:chamber-token {--name=chamber-capture : Token name}';

    protected $description = 'Create or rotate a Sanctum token for the chamber capture daemon';

    public function handle(ChamberCaptureTokenService $tokens): int
    {
        $token = $tokens->issue((string) $this->option('name'));

        $this->info('Chamber capture token created. Store it only on the capture PC.');
        $this->line($token);

        return self::SUCCESS;
    }
}
