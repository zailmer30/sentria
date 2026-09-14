<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\Audit\AuditChainHasher;
use Illuminate\Console\Command;

class VerifyAuditChainCommand extends Command
{
    protected $signature = 'audit:verify-chain';

    protected $description = 'Verify the append-only audit log hash chain integrity';

    public function handle(AuditChainHasher $hasher): int
    {
        $previousHash = null;
        $count = 0;

        AuditLog::query()->orderBy('sequence')->each(function (AuditLog $log) use ($hasher, &$previousHash, &$count): bool {
            $count++;

            if ($log->previous_hash !== $previousHash) {
                $this->error("Broken chain at sequence {$log->sequence}: previous_hash mismatch.");

                return false;
            }

            $expected = $hasher->hashFromLog($log);

            if (! hash_equals($expected, (string) $log->hash)) {
                $this->error("Hash mismatch at sequence {$log->sequence}.");

                return false;
            }

            $previousHash = $log->hash;

            return true;
        });

        if ($count === 0) {
            $this->info('Audit chain is empty — nothing to verify.');

            return self::SUCCESS;
        }

        /** @var AuditLog|null $lastFailure */
        $lastFailure = null;

        $previousHash = null;
        foreach (AuditLog::query()->orderBy('sequence')->cursor() as $log) {
            if ($log->previous_hash !== $previousHash) {
                $this->error("Broken chain at sequence {$log->sequence}: previous_hash mismatch.");

                return self::FAILURE;
            }

            $expected = $hasher->hashFromLog($log);

            if (! hash_equals($expected, (string) $log->hash)) {
                $this->error("Hash mismatch at sequence {$log->sequence}.");

                return self::FAILURE;
            }

            $previousHash = $log->hash;
        }

        $this->info("Audit chain verified: {$count} entries intact.");

        return self::SUCCESS;
    }
}
