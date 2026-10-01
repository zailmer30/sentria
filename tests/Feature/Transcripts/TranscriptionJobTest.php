<?php

use App\Contracts\AI\TranscriptionService;
use App\DTO\AI\TranscriptionResult;
use App\Enums\UserRole;
use App\Events\TranscriptSegmentReceived;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\Transcript;
use App\Models\TranscriptSegmentEdit;
use App\Models\User;
use App\Services\Sessions\TranscriptService;
use App\States\Session\InSession;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);

    Storage::fake('local');
    config([
        'sentria.transcription.driver' => 'fake',
        'sentria.ai.api_key' => null,
    ]);
});

function transcriptSecretariat(): User
{
    return User::factory()->create([
        'email' => 'secretariat-transcript@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => false,
    ])->assignRole(UserRole::Secretariat->value);
}

function transcriptSessionWithAgenda(): array
{
    $secretariat = transcriptSecretariat();

    $session = LegislativeSession::factory()->create([
        'session_number' => 'TS-401',
        'title' => 'Transcription Test Session',
        'status' => InSession::$name,
        'actual_start_at' => now(),
    ]);

    $item = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'title' => 'Budget Appropriations Ordinance',
        'item_number' => '3',
        'position' => 1,
        'status' => 'in-progress',
    ]);

    return compact('secretariat', 'session', 'item');
}

it('processes uploaded audio into a searchable transcript with agenda association', function (): void {
    ['secretariat' => $secretariat, 'session' => $session, 'item' => $item] = transcriptSessionWithAgenda();

    $audioContents = 'fake-session-audio-for-transcription-test';
    $upload = UploadedFile::fake()->createWithContent('session.wav', $audioContents);

    $this->actingAs($secretariat)
        ->post(route('sessions.transcript.store', $session), [
            'audio' => $upload,
            'agenda_item_id' => $item->getKey(),
        ])
        ->assertRedirect(route('sessions.transcript.show', $session));

    $transcript = Transcript::query()->where('session_id', $session->getKey())->first();

    expect($transcript)->not->toBeNull()
        ->and($transcript->agenda_item_id)->toBe($item->getKey())
        ->and($transcript->status)->toBe('completed')
        ->and($transcript->segments)->toBeArray()->not->toBeEmpty()
        ->and($transcript->full_text)->not->toBeEmpty();

    $firstSegment = $transcript->segments[0];
    expect($firstSegment)->toHaveKeys(['index', 'start', 'end', 'text', 'speaker', 'original_text', 'original_speaker'])
        ->and($firstSegment['text'])->toContain('Budget Appropriations Ordinance')
        ->and($firstSegment['original_text'])->toBe($firstSegment['text'])
        ->and($firstSegment['original_speaker'])->toBe($firstSegment['speaker']);

    /** @var TranscriptService $service */
    $service = app(TranscriptService::class);
    $results = $service->search($transcript, 'Budget Appropriations');

    expect($results)->not->toBeEmpty()
        ->and($results[0]['start'])->toBeFloat()
        ->and($results[0]['text'])->toContain('Budget Appropriations');
});

it('rejects a non-audio upload', function (): void {
    ['secretariat' => $secretariat, 'session' => $session] = transcriptSessionWithAgenda();

    $this->actingAs($secretariat)
        ->from(route('sessions.transcript.show', $session))
        ->post(route('sessions.transcript.store', $session), [
            'audio' => UploadedFile::fake()->create('notes.pdf', 40, 'application/pdf'),
        ])
        ->assertRedirect(route('sessions.transcript.show', $session))
        ->assertSessionHasErrors('audio');

    expect(Transcript::query()->where('session_id', $session->getKey())->exists())->toBeFalse();
});

it('accepts m4a session audio', function (): void {
    ['secretariat' => $secretariat, 'session' => $session] = transcriptSessionWithAgenda();

    $this->actingAs($secretariat)
        ->post(route('sessions.transcript.store', $session), [
            'audio' => UploadedFile::fake()->create('clip.m4a', 80, 'audio/mp4'),
        ])
        ->assertRedirect(route('sessions.transcript.show', $session));

    expect(Transcript::query()->where('session_id', $session->getKey())->exists())->toBeTrue();
});

it('transcribes an upload onto the live chamber transcript so the page can show it', function (): void {
    ['secretariat' => $secretariat, 'session' => $session] = transcriptSessionWithAgenda();

    $chamber = Transcript::factory()->create([
        'session_id' => $session->getKey(),
        'source' => 'chamber_channels',
        'status' => 'processing',
        'full_text' => 'Live line',
        'segments' => [
            ['index' => 0, 'start' => 0.0, 'end' => 1.0, 'text' => 'Live line', 'speaker' => null],
        ],
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.transcript.store', $session), [
            'audio' => UploadedFile::fake()->createWithContent('session.wav', 'fake-session-audio-for-transcription-test'),
        ])
        ->assertRedirect(route('sessions.transcript.show', $session));

    expect(Transcript::query()->where('session_id', $session->getKey())->count())->toBe(1);

    $chamber->refresh();

    expect($chamber->source)->toBe('chamber_channels')
        ->and($chamber->status)->toBe('completed')
        ->and($chamber->full_text)->not->toBeEmpty()
        ->and($chamber->full_text)->not->toBe('Live line')
        ->and($chamber->segments[0]['original_text'])->toBe($chamber->segments[0]['text'])
        ->and($chamber->segmentEdits()->count())->toBe(0);
});

it('surfaces a transcription failure on the transcript page', function (): void {
    ['secretariat' => $secretariat, 'session' => $session] = transcriptSessionWithAgenda();

    $this->app->bind(TranscriptionService::class, fn () => new class implements TranscriptionService
    {
        public function transcribeSessionAudio(
            string $absolutePath,
            string $mime,
            array $agendaKeywords = [],
            string $languageMode = 'auto',
            array $options = [],
        ): TranscriptionResult {
            unset($absolutePath, $mime, $agendaKeywords, $languageMode, $options);

            throw new RuntimeException('ElevenLabs transcription request failed: invalid API key');
        }
    });

    $this->actingAs($secretariat)
        ->post(route('sessions.transcript.store', $session), [
            'audio' => UploadedFile::fake()->createWithContent('session.wav', 'fake-session-audio-for-transcription-test'),
        ])
        ->assertRedirect(route('sessions.transcript.show', $session));

    $transcript = Transcript::query()->where('session_id', $session->getKey())->first();

    expect($transcript)->not->toBeNull()
        ->and($transcript->status)->toBe('failed')
        ->and($transcript->processing_error)->toContain('invalid API key');

    $this->actingAs($secretariat)
        ->get(route('sessions.transcript.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Transcript')
            ->where('transcript.status', 'failed')
            ->where('transcript.processing_error', $transcript->processing_error)
        );
});

it('shares the low-confidence threshold with the transcript page', function (): void {
    ['secretariat' => $secretariat, 'session' => $session] = transcriptSessionWithAgenda();

    $this->actingAs($secretariat)
        ->get(route('sessions.transcript.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Transcript')
            ->where('ai.transcription_low_confidence', 0.4)
        );
});

it('replaces machine originals and discards prior edits on a new upload', function (): void {
    ['secretariat' => $secretariat, 'session' => $session] = transcriptSessionWithAgenda();

    $transcript = Transcript::factory()->create([
        'session_id' => $session->getKey(),
        'source' => 'live_stt',
        'status' => 'completed',
        'full_text' => 'Old machine line.',
        'segments' => [
            [
                'index' => 0,
                'start' => 0.0,
                'end' => 1.0,
                'text' => 'Edited official line.',
                'original_text' => 'Old machine line.',
                'speaker' => 'Speaker 1',
                'original_speaker' => 'Speaker 1',
                'original_speaker_id' => null,
                'original_attributed' => true,
            ],
        ],
    ]);

    TranscriptSegmentEdit::query()->create([
        'transcript_id' => $transcript->getKey(),
        'segment_index' => 0,
        'field' => 'text',
        'old_value' => 'Old machine line.',
        'new_value' => 'Edited official line.',
        'user_id' => $secretariat->getKey(),
        'created_at' => now(),
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.transcript.store', $session), [
            'audio' => UploadedFile::fake()->createWithContent('session.wav', 'fake-session-audio-for-transcription-test'),
        ])
        ->assertRedirect(route('sessions.transcript.show', $session));

    $transcript->refresh();

    expect($transcript->segmentEdits()->count())->toBe(0)
        ->and($transcript->segments[0]['original_text'])->toBe($transcript->segments[0]['text'])
        ->and($transcript->segments[0]['text'])->not->toBe('Edited official line.');
});

it('stamps original STT fields on live segments without broadcasting them', function (): void {
    $session = LegislativeSession::factory()->create([
        'status' => InSession::$name,
    ]);
    $transcript = Transcript::factory()->create([
        'session_id' => $session->getKey(),
        'source' => 'chamber_channels',
        'status' => 'processing',
        'segments' => [],
        'full_text' => '',
    ]);

    Event::fake([TranscriptSegmentReceived::class]);

    /** @var TranscriptService $service */
    $service = app(TranscriptService::class);
    $service->appendLiveSegment($transcript, [
        'index' => 42,
        'start' => 1.0,
        'end' => 2.5,
        'text' => 'Hello from the floor.',
        'speaker' => 'Hon. Member One',
        'speaker_id' => $transcript->created_by,
        'attributed' => true,
        'confidence' => 0.91,
    ]);

    $fresh = $transcript->fresh();
    $stored = $fresh->segments[0];

    expect($stored['text'])->toBe('Hello from the floor.')
        ->and($stored['original_text'])->toBe('Hello from the floor.')
        ->and($stored['original_speaker'])->toBe('Hon. Member One')
        ->and($stored['original_attributed'])->toBeTrue();

    Event::assertDispatched(TranscriptSegmentReceived::class, function (TranscriptSegmentReceived $event): bool {
        return $event->segment['text'] === 'Hello from the floor.'
            && ! array_key_exists('original_text', $event->segment);
    });
});
