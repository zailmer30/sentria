<?php

namespace App\Services\AI;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Throwable;

class TranscriptionError
{
    public static function message(Throwable $exception): string
    {
        $fromHttp = self::httpBody($exception);

        $raw = $fromHttp !== '' ? $fromHttp : trim($exception->getMessage());

        if ($raw === '' && $exception->getPrevious() instanceof Throwable) {
            return self::message($exception->getPrevious());
        }

        $cleaned = preg_replace(
            '/(?i)(?:api[_-]?key|authorization|bearer|xi-api-key|sk-)["\'\s:=]*[^\s,;\'"]+/',
            '[redacted]',
            $raw,
        ) ?? $raw;

        $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned);

        return $cleaned === '' ? 'Transcription failed.' : Str::limit($cleaned, 500);
    }

    private static function httpBody(Throwable $exception): string
    {
        $current = $exception;

        while ($current instanceof Throwable) {
            if ($current instanceof RequestException) {
                $payload = $current->response->json();

                if (is_array($payload)) {
                    $extracted = self::extractDetail($payload);

                    if ($extracted !== '') {
                        $status = $current->response->status();

                        return $status >= 400 ? "HTTP {$status}: {$extracted}" : $extracted;
                    }
                }

                $body = trim((string) $current->response->body());

                if ($body !== '') {
                    $status = $current->response->status();

                    return $status >= 400
                        ? 'HTTP '.$status.': '.Str::limit($body, 240)
                        : Str::limit($body, 240);
                }
            }

            $current = $current->getPrevious();
        }

        return '';
    }

    /**
     * @param  array<mixed>  $payload
     */
    private static function extractDetail(array $payload): string
    {
        foreach (['detail', 'message', 'error', 'msg'] as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_array($value)) {
                $nested = $value['message'] ?? $value['detail'] ?? $value['msg'] ?? null;

                if (is_string($nested) && trim($nested) !== '') {
                    return trim($nested);
                }
            }
        }

        return '';
    }
}
