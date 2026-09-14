<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Append-only hash-chained audit writer. Chain verification via audit:verify-chain.
 */
class AuditLogger
{
    public function __construct(private readonly AuditChainHasher $hasher) {}

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @param  array<string, mixed>|null  $context
     */
    public function record(
        string $event,
        string $category = 'general',
        ?Model $auditable = null,
        ?User $actor = null,
        ?array $old = null,
        ?array $new = null,
        ?array $context = null,
        ?string $message = null,
        bool $isAiActor = false,
    ): AuditLog {
        return DB::transaction(function () use (
            $event, $category, $auditable, $actor, $old, $new, $context, $message, $isAiActor
        ): AuditLog {
            /** @var AuditLog|null $previous */
            $previous = AuditLog::query()->orderByDesc('sequence')->lockForUpdate()->first();

            $occurredAt = now()->utc()->startOfSecond();
            $payload = $this->hasher->buildPayload(
                event: $event,
                category: $category,
                auditableType: $auditable?->getMorphClass(),
                auditableId: $auditable?->getKey(),
                userId: $actor?->getKey(),
                actorLabel: $actor?->display_name,
                actorRole: $actor?->getRoleNames()->first(),
                isAiActor: $isAiActor,
                ipAddress: request()?->ip(),
                userAgent: ($agent = request()?->userAgent()) !== null && $agent !== ''
                    ? Str::limit($agent, 1023, '')
                    : null,
                route: request()?->route()?->getName(),
                method: request()?->method(),
                oldValues: $old,
                newValues: $new,
                context: $context,
                message: $message,
                previousHash: $previous?->hash,
                occurredAt: $occurredAt,
            );

            $hash = $this->hasher->hash($payload);

            return AuditLog::query()->create([
                'event' => $payload['event'],
                'category' => $payload['category'],
                'auditable_type' => $payload['auditable_type'],
                'auditable_id' => $payload['auditable_id'],
                'user_id' => $payload['user_id'],
                'actor_label' => $payload['actor_label'],
                'actor_role' => $payload['actor_role'],
                'is_ai_actor' => $payload['is_ai_actor'],
                'ip_address' => $payload['ip_address'],
                'user_agent' => $payload['user_agent'],
                'route' => $payload['route'],
                'method' => $payload['method'],
                'old_values' => $payload['old_values'],
                'new_values' => $payload['new_values'],
                'context' => $payload['context'],
                'message' => $payload['message'],
                'previous_hash' => $payload['previous_hash'],
                'occurred_at' => $occurredAt,
                'occurred_at_epoch' => $payload['occurred_at'],
                'hash' => $hash,
            ]);
        });
    }
}
