<?php

use App\Services\AI\ElevenLabsTranscriptionService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function elevenLabsWav(): string
{
    $path = tempnam(sys_get_temp_dir(), 'wav');
    file_put_contents($path, str_repeat('audio', 32));

    return $path;
}

/**
 * @return array<string, string>
 */
function elevenLabsForm(Request $request): array
{
    $fields = [];

    foreach ($request->data() as $part) {
        if (! is_array($part) || ! isset($part['name'], $part['contents']) || ! is_string($part['contents'])) {
            continue;
        }

        $fields[(string) $part['name']] = $part['contents'];
    }

    return $fields;
}

it('maps Scribe words into timestamped segments without locking auto language', function (): void {
    $path = elevenLabsWav();

    Http::fake([
        'https://api.elevenlabs.io/v1/speech-to-text' => Http::response([
            'language_code' => 'fil',
            'language_probability' => 0.96,
            'text' => 'Magandang umaga. I move that we proceed.',
            'audio_duration_secs' => 2.4,
            'words' => [
                ['text' => 'Magandang', 'type' => 'word', 'start' => 0.1, 'end' => 0.6, 'logprob' => -0.2],
                ['text' => ' ', 'type' => 'spacing', 'start' => 0.6, 'end' => 0.65, 'logprob' => 0],
                ['text' => 'umaga.', 'type' => 'word', 'start' => 0.65, 'end' => 1.1, 'logprob' => -0.15],
                ['text' => 'I', 'type' => 'word', 'start' => 2.0, 'end' => 2.1, 'logprob' => -0.1],
                ['text' => ' ', 'type' => 'spacing', 'start' => 2.1, 'end' => 2.15, 'logprob' => 0],
                ['text' => 'move', 'type' => 'word', 'start' => 2.15, 'end' => 2.4, 'logprob' => -0.1],
            ],
        ], 200),
    ]);

    $result = (new ElevenLabsTranscriptionService('xi-test'))->transcribeSessionAudio(
        $path,
        'audio/wav',
        ['Appropriations Ordinance'],
        'auto',
        ['source' => 'upload'],
    );

    unlink($path);

    expect($result->language)->toBe('tl')
        ->and($result->provider)->toBe('elevenlabs')
        ->and($result->model)->toBe('scribe_v2')
        ->and($result->segments)->toHaveCount(2)
        ->and($result->segments[0]['text'])->toBe('Magandang umaga.')
        ->and($result->segments[1]['text'])->toBe('I move')
        ->and($result->segments[1]['language'])->toBe('tl');

    Http::assertSent(function ($request): bool {
        $data = elevenLabsForm($request);

        return $request->url() === 'https://api.elevenlabs.io/v1/speech-to-text'
            && $request->hasHeader('xi-api-key', 'xi-test')
            && ($data['model_id'] ?? null) === 'scribe_v2'
            && ($data['diarize'] ?? null) === 'true'
            && ($data['tag_audio_events'] ?? null) === 'true'
            && ($data['no_verbatim'] ?? null) === 'false'
            && ! array_key_exists('language_code', $data)
            && ! str_contains($request->body(), 'keyterms');
    });
});

it('sends diarization for mixer live chunks and splits speaker turns', function (): void {
    $path = elevenLabsWav();

    Http::fake([
        'https://api.elevenlabs.io/v1/speech-to-text' => Http::response([
            'language_code' => 'en',
            'text' => 'I move. I second.',
            'audio_duration_secs' => 2.0,
            'words' => [
                ['text' => 'I', 'type' => 'word', 'start' => 0.0, 'end' => 0.2, 'logprob' => -0.05, 'speaker_id' => 'speaker_0'],
                ['text' => ' ', 'type' => 'spacing', 'start' => 0.2, 'end' => 0.25, 'speaker_id' => 'speaker_0'],
                ['text' => 'move.', 'type' => 'word', 'start' => 0.25, 'end' => 0.7, 'logprob' => -0.05, 'speaker_id' => 'speaker_0'],
                ['text' => 'I', 'type' => 'word', 'start' => 0.9, 'end' => 1.1, 'logprob' => -0.04, 'speaker_id' => 'speaker_1'],
                ['text' => ' ', 'type' => 'spacing', 'start' => 1.1, 'end' => 1.15, 'speaker_id' => 'speaker_1'],
                ['text' => 'second.', 'type' => 'word', 'start' => 1.15, 'end' => 1.8, 'logprob' => -0.04, 'speaker_id' => 'speaker_1'],
            ],
        ], 200),
    ]);

    $result = (new ElevenLabsTranscriptionService('xi-test'))->transcribeSessionAudio(
        $path,
        'audio/wav',
        [],
        'auto',
        ['source' => 'chamber_mix'],
    );

    unlink($path);

    expect($result->segments)->toHaveCount(2)
        ->and($result->segments[0]['text'])->toBe('I move.')
        ->and($result->segments[1]['text'])->toBe('I second.');

    Http::assertSent(function ($request): bool {
        $data = elevenLabsForm($request);

        return ($data['diarize'] ?? null) === 'true'
            && ($data['tag_audio_events'] ?? null) === 'false';
    });
});

it('does not send diarization or audio events for chamber live chunks', function (): void {
    $path = elevenLabsWav();

    Http::fake([
        'https://api.elevenlabs.io/v1/speech-to-text' => Http::response([
            'language_code' => 'en',
            'text' => 'I second.',
            'audio_duration_secs' => 1.0,
            'words' => [
                ['text' => 'I second.', 'type' => 'word', 'start' => 0.0, 'end' => 1.0, 'logprob' => -0.05, 'speaker_id' => 'speaker_0'],
            ],
        ], 200),
    ]);

    $result = (new ElevenLabsTranscriptionService('xi-test'))->transcribeSessionAudio(
        $path,
        'audio/wav',
        [],
        'auto',
        ['source' => 'chamber'],
    );

    unlink($path);

    expect($result->segments[0]['speaker'])->toBeNull();

    Http::assertSent(function ($request): bool {
        $data = elevenLabsForm($request);

        return ($data['diarize'] ?? null) === 'false'
            && ($data['tag_audio_events'] ?? null) === 'false';
    });
});

it('splits speakers and keeps audio events on file upload', function (): void {
    $path = elevenLabsWav();

    Http::fake([
        'https://api.elevenlabs.io/v1/speech-to-text' => Http::response([
            'language_code' => 'fil',
            'text' => 'Pagong tan-a. Oh, Juliana. [sniffs]',
            'audio_duration_secs' => 4.0,
            'words' => [
                ['text' => 'Pagong', 'type' => 'word', 'start' => 0.2, 'end' => 0.8, 'logprob' => -0.1, 'speaker_id' => 'speaker_0'],
                ['text' => ' ', 'type' => 'spacing', 'start' => 0.8, 'end' => 0.85, 'speaker_id' => 'speaker_0'],
                ['text' => 'tan-a.', 'type' => 'word', 'start' => 0.85, 'end' => 1.4, 'logprob' => -0.1, 'speaker_id' => 'speaker_0'],
                ['text' => 'Oh,', 'type' => 'word', 'start' => 2.0, 'end' => 2.3, 'logprob' => -0.08, 'speaker_id' => 'speaker_1'],
                ['text' => ' ', 'type' => 'spacing', 'start' => 2.3, 'end' => 2.35, 'speaker_id' => 'speaker_1'],
                ['text' => 'Juliana.', 'type' => 'word', 'start' => 2.35, 'end' => 3.1, 'logprob' => -0.08, 'speaker_id' => 'speaker_1'],
                ['text' => '(sniffs)', 'type' => 'audio_event', 'start' => 3.2, 'end' => 3.8, 'speaker_id' => 'speaker_1'],
            ],
        ], 200),
    ]);

    $result = (new ElevenLabsTranscriptionService('xi-test'))->transcribeSessionAudio(
        $path,
        'audio/wav',
        [],
        'auto',
        ['source' => 'upload'],
    );

    unlink($path);

    expect($result->segments)->toHaveCount(2)
        ->and($result->segments[0]['speaker'])->toBe('Speaker 0')
        ->and($result->segments[0]['text'])->toBe('Pagong tan-a.')
        ->and($result->segments[1]['speaker'])->toBe('Speaker 1')
        ->and($result->segments[1]['text'])->toBe('Oh, Juliana. [sniffs]');
});

it('sends agenda keyterms only when enabled', function (): void {
    $path = elevenLabsWav();

    Http::fake([
        'https://api.elevenlabs.io/v1/speech-to-text' => Http::response([
            'language_code' => 'ceb',
            'text' => 'Malinaw.',
            'audio_duration_secs' => 1.0,
            'words' => [
                ['text' => 'Malinaw.', 'type' => 'word', 'start' => 0.0, 'end' => 1.0, 'logprob' => -0.05],
            ],
        ], 200),
    ]);

    $result = (new ElevenLabsTranscriptionService(
        apiKey: 'xi-test',
        sendKeyterms: true,
    ))->transcribeSessionAudio($path, 'audio/wav', ['Budget Ordinance'], 'auto');

    unlink($path);

    expect($result->language)->toBe('ceb');

    Http::assertSent(fn ($request): bool => str_contains($request->body(), 'Budget Ordinance'));
});
