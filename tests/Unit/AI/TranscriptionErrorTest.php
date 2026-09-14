<?php

use App\Services\AI\TranscriptionError;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('prefers the provider error body over the HTTP wrapper', function (): void {
    Http::fake([
        '*' => Http::response(['error' => ['message' => 'Invalid API key']], 401),
    ]);

    try {
        Http::get('https://stt.test/v1')->throw();
        expect(false)->toBeTrue();
    } catch (RequestException $exception) {
        $wrapped = new RuntimeException('ElevenLabs transcription request failed: '.$exception->getMessage(), 0, $exception);

        expect(TranscriptionError::message($wrapped))->toBe('HTTP 401: Invalid API key');
    }
});

it('redacts credentials from error text', function (): void {
    $exception = new RuntimeException('Request failed with xi-api-key=sk_live_secret_value in header');

    expect(TranscriptionError::message($exception))->not->toContain('sk_live_secret_value')
        ->and(TranscriptionError::message($exception))->toContain('[redacted]');
});
