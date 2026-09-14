<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Support\Carbon;

/**
 * Canonical hash-chain payload builder shared by AuditLogger and audit:verify-chain.
 */
class AuditChainHasher
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>
     */
    public function buildPayload(
        string $event,
        string $category,
        ?string $auditableType,
        ?string $auditableId,
        ?string $userId,
        ?string $actorLabel,
        ?string $actorRole,
        bool $isAiActor,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $route,
        ?string $method,
        ?array $oldValues,
        ?array $newValues,
        ?array $context,
        ?string $message,
        ?string $previousHash,
        Carbon|string|int $occurredAt,
    ): array {
        $occurredAtUnix = match (true) {
            is_int($occurredAt) => $occurredAt,
            $occurredAt instanceof Carbon => $occurredAt->copy()->utc()->startOfSecond()->getTimestamp(),
            default => Carbon::parse($occurredAt)->utc()->startOfSecond()->getTimestamp(),
        };

        return [
            'event' => $event,
            'category' => $category,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'user_id' => $userId,
            'actor_label' => $actorLabel,
            'actor_role' => $actorRole,
            'is_ai_actor' => $isAiActor,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'route' => $route,
            'method' => $method,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'context' => $context,
            'message' => $message,
            'previous_hash' => $previousHash,
            'occurred_at' => $occurredAtUnix,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function hashFromLog(AuditLog $log): string
    {
        return $this->hash($this->payloadFromLog($log));
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadFromLog(AuditLog $log): array
    {
        return $this->buildPayload(
            event: $log->event,
            category: $log->category,
            auditableType: $this->nullableString($log->auditable_type),
            auditableId: $this->nullableString($log->auditable_id),
            userId: $this->nullableString($log->user_id),
            actorLabel: $this->nullableString($log->actor_label),
            actorRole: $this->nullableString($log->actor_role),
            isAiActor: (bool) $log->is_ai_actor,
            ipAddress: $this->nullableString($log->ip_address),
            userAgent: $this->nullableString($log->user_agent),
            route: $this->nullableString($log->route),
            method: $this->nullableString($log->method),
            oldValues: $log->old_values,
            newValues: $log->new_values,
            context: $log->context,
            message: $this->nullableString($log->message),
            previousHash: $this->nullableString($log->previous_hash),
            occurredAt: $log->occurred_at_epoch
                ?? Carbon::parse($log->getRawOriginal('occurred_at'))->utc()->startOfSecond()->getTimestamp(),
        );
    }

    private function nullableString(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }
}
