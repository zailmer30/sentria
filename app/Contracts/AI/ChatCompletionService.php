<?php

namespace App\Contracts\AI;

use App\DTO\AI\ChatMessage;
use App\DTO\AI\ChatResult;

interface ChatCompletionService
{
    /**
     * @param  list<ChatMessage>  $messages
     * @param  array<string, mixed>  $options
     */
    public function complete(string $system, array $messages, array $options = []): ChatResult;
}
