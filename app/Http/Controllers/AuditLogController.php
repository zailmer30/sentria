<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->requireUser($request);
        abort_unless($user->can('audit.viewAny'), 403);

        $logs = AuditLog::query()
            ->with('user:id,display_name,email')
            ->latest('sequence')
            ->paginate(50);

        return Inertia::render('Audit/Index', [
            'logs' => $logs->through(fn (AuditLog $log): array => [
                'id' => $log->getKey(),
                'sequence' => $log->sequence,
                'event' => $log->event,
                'category' => $log->category,
                'message' => $log->message,
                'actor_label' => $log->actor_label,
                'actor_role' => $log->actor_role,
                'is_ai_actor' => (bool) $log->is_ai_actor,
                'occurred_at' => $log->occurred_at?->toIso8601String(),
                'user' => $log->user !== null ? [
                    'display_name' => $log->user->display_name,
                    'email' => $log->user->email,
                ] : null,
            ]),
            'can' => [
                'verify' => $user->can('audit.verify'),
            ],
        ]);
    }
}
