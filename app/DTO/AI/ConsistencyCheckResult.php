<?php

namespace App\DTO\AI;

final readonly class ConsistencyCheckResult
{
    public const DISCLAIMER = 'AI-assisted review. Human verification required.';

    /**
     * @param  list<ConsistencyFinding>  $findings
     */
    public function __construct(
        public array $findings,
        public string $disclaimer = self::DISCLAIMER,
        public bool $isAiAssisted = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'disclaimer' => $this->disclaimer,
            'is_ai_assisted' => $this->isAiAssisted,
            'findings' => array_map(
                static fn (ConsistencyFinding $finding): array => $finding->toArray(),
                $this->findings,
            ),
        ];
    }
}
