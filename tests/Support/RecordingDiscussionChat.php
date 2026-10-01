<?php

namespace Tests\Support;

use App\Contracts\AI\ChatCompletionService;
use App\DTO\AI\ChatResult;
use Throwable;

final class RecordingDiscussionChat implements ChatCompletionService
{
    /** @var list<string> */
    public array $userMessages = [];

    public function __construct(
        private readonly string $content,
        private readonly string $model = 'gpt-4o-mini',
        private readonly ?Throwable $failure = null,
    ) {}

    public function complete(string $system, array $messages, array $options = []): ChatResult
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->userMessages[] = $messages[0]->content ?? '';

        return new ChatResult(
            content: $this->content,
            model: $this->model,
            inputTokens: 12,
            outputTokens: 8,
        );
    }
}
