<?php

namespace App\Services\AI;

use App\Contracts\AI\ChatCompletionService;
use App\DTO\AI\ChatMessage;
use App\DTO\AI\ChatResult;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiCompatibleChatService implements ChatCompletionService
{
    public function complete(string $system, array $messages, array $options = []): ChatResult
    {
        $apiKey = config('sentria.ai.api_key');
        $baseUrl = rtrim((string) config('sentria.ai.base_url', 'https://api.openai.com/v1'), '/');
        $model = $this->modelName();

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ...array_map(
                    static fn (ChatMessage $message): array => [
                        'role' => $message->role,
                        'content' => $message->content,
                    ],
                    $messages,
                ),
            ],
            'temperature' => (float) ($options['temperature'] ?? 0.2),
        ];

        if (($options['mode'] ?? 'rag') === 'summarize') {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = Http::withToken((string) $apiKey)
                ->timeout(120)
                ->post("{$baseUrl}/chat/completions", $payload)
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException($this->chatFailureMessage($exception, $baseUrl, $model), 0, $exception);
        }

        $content = (string) ($response->json('choices.0.message.content') ?? '');
        $usage = $response->json('usage') ?? [];

        return new ChatResult(
            content: $content,
            model: (string) ($response->json('model') ?? $model),
            inputTokens: (int) ($usage['prompt_tokens'] ?? 0),
            outputTokens: (int) ($usage['completion_tokens'] ?? 0),
        );
    }

    public function modelName(): string
    {
        return (string) config('sentria.ai.chat_model', 'gpt-4o-mini');
    }

    private function chatFailureMessage(RequestException $exception, string $baseUrl, string $model): string
    {
        $response = $exception->response;
        $providerMessage = $response?->json('error.message');
        $status = $response?->status();
        $detail = is_string($providerMessage) && $providerMessage !== ''
            ? $providerMessage
            : $exception->getMessage();

        return sprintf(
            'Chat completion request failed (%s via %s%s): %s',
            $model,
            $baseUrl,
            is_int($status) ? ", HTTP {$status}" : '',
            $detail,
        );
    }
}
