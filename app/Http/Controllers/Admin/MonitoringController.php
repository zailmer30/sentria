<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Backup\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringController extends Controller
{
    public function __invoke(Request $request, BackupService $backup): Response
    {
        $user = $this->requireUser($request);
        abort_unless($user->can('settings.viewAny'), 403);

        $canViewHorizon = $user->hasAnyRole(['system-administrator', 'secretariat']);
        $latestBackup = $backup->latestStatus();
        $lastRestoreTest = $backup->lastRestoreTestAt();

        return Inertia::render('Admin/Monitoring', [
            'status' => [
                'app_env' => (string) config('app.env'),
                'queue_connection' => (string) config('queue.default'),
                'failed_jobs_count' => (int) DB::table('failed_jobs')->count(),
                'ai_enabled' => (bool) config('sentria.ai.enabled'),
                'backup_status' => $latestBackup !== null ? 'ok' : 'not_configured',
            ],
            'backup' => [
                'last_at' => $latestBackup['created_at'] ?? null,
                'last_size_bytes' => $latestBackup['size_bytes'] ?? null,
                'last_path' => $latestBackup['path'] ?? null,
                'last_restore_test_at' => $lastRestoreTest?->toIso8601String(),
                'next_scheduled' => null,
                'rpo_minutes' => (int) config('sentria.backup.rpo_minutes', 60),
                'rto_minutes' => (int) config('sentria.backup.rto_minutes', 240),
            ],
            'recent_logins' => $this->recentLoginRows(),
            'horizon_url' => $canViewHorizon ? url('/horizon') : null,
        ]);
    }

    /**
     * @return list<array{
     *     id: string,
     *     actor_label: string|null,
     *     actor_role: string|null,
     *     ip_address: string|null,
     *     occurred_at: string|null,
     *     user: array{display_name: string, email: string}|null
     * }>
     */
    private function recentLoginRows(): array
    {
        /** @var Collection<int, AuditLog> $auditRows */
        $auditRows = AuditLog::query()
            ->where('event', 'auth.login')
            ->latest('occurred_at')
            ->limit(10)
            ->get();

        if ($auditRows->isNotEmpty()) {
            return array_values($auditRows
                ->map(fn (AuditLog $log): array => $this->loginRowFromAudit($log))
                ->all());
        }

        return array_values(User::query()
            ->whereNotNull('last_login_at')
            ->orderByDesc('last_login_at')
            ->limit(10)
            ->get()
            ->map(fn (User $loginUser): array => $this->loginRowFromUser($loginUser))
            ->all());
    }

    /**
     * @return array{
     *     id: string,
     *     actor_label: string|null,
     *     actor_role: string|null,
     *     ip_address: string|null,
     *     occurred_at: string|null,
     *     user: array{display_name: string, email: string}|null
     * }
     */
    private function loginRowFromAudit(AuditLog $log): array
    {
        return [
            'id' => (string) $log->getKey(),
            'actor_label' => $log->actor_label,
            'actor_role' => $log->actor_role,
            'ip_address' => $log->ip_address,
            'occurred_at' => $this->formatTimestamp($log->occurred_at),
            'user' => null,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     actor_label: string|null,
     *     actor_role: string|null,
     *     ip_address: string|null,
     *     occurred_at: string|null,
     *     user: array{display_name: string, email: string}|null
     * }
     */
    private function loginRowFromUser(User $loginUser): array
    {
        return [
            'id' => (string) $loginUser->getKey(),
            'actor_label' => $loginUser->display_name,
            'actor_role' => null,
            'ip_address' => $loginUser->last_login_ip,
            'occurred_at' => $this->formatTimestamp($loginUser->last_login_at),
            'user' => [
                'display_name' => $loginUser->display_name,
                'email' => $loginUser->email,
            ],
        ];
    }

    private function formatTimestamp(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        return null;
    }
}
