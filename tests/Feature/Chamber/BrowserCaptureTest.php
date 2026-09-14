<?php

use App\Enums\UserRole;
use App\Events\TranscriptSegmentReceived;
use App\Models\LegislativeSession;
use App\Models\Transcript;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);

    Storage::fake('local');
    config([
        'sentria.transcription.driver' => 'fake',
        'sentria.ai.api_key' => null,
        'sentria.chamber.min_chunk_bytes' => 32,
        'sentria.chamber.min_duration_ms' => 400,
    ]);
});

function browserCaptureOperator(): User
{
    return User::factory()->create([
        'email' => 'secretariat-browser-capture@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);
}

function browserCaptureSession(): array
{
    $session = LegislativeSession::factory()->inSession()->create([
        'recording_enabled' => true,
        'capture_mode' => 'mixer_mix',
        'actual_start_at' => now()->subMinutes(5),
    ]);

    $transcript = Transcript::query()->create([
        'session_id' => $session->getKey(),
        'source' => 'chamber_channels',
        'status' => 'processing',
        'language' => 'und',
        'channel_map_snapshot' => [],
        'segments' => [],
        'started_at' => $session->actual_start_at,
    ]);

    return compact('session', 'transcript');
}

it('renders the recording console with the tuning the worklet needs', function (): void {
    ['session' => $session] = browserCaptureSession();

    $this->actingAs(browserCaptureOperator())
        ->get(route('sessions.capture.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Sessions/Capture')
            ->where('session.id', $session->getKey())
            ->where('capture.capture', 'record')
            ->where('capture.feed', 'mixer_mix')
            ->where('tuning.sample_rate', 16000)
            ->where('tuning.vad_rms', 0.012)
            ->where('tuning.min_speech_ms', 400)
            ->where('tuning.silence_end_ms', 600)
            ->where('tuning.max_utterance_ms', 25000)
        );
});

it('keeps the browser capture state pinned to the session in the page', function (): void {
    ['session' => $session] = browserCaptureSession();

    // A newer sitting would win `liveSession()`, but the operator's tab must
    // keep recording the sitting it was opened on.
    LegislativeSession::factory()->inSession()->create([
        'recording_enabled' => true,
        'capture_mode' => 'mixer_mix',
        'actual_start_at' => now(),
    ]);

    $this->actingAs(browserCaptureOperator())
        ->getJson(route('sessions.capture.state', $session))
        ->assertOk()
        ->assertJsonPath('session_id', $session->getKey())
        ->assertJsonPath('channels.0.channel_index', 1)
        ->assertJsonPath('channels.0.next_seq', 0);
});

it('transcribes a chunk uploaded from the browser', function (): void {
    Event::fake([TranscriptSegmentReceived::class]);

    ['session' => $session, 'transcript' => $transcript] = browserCaptureSession();
    $operator = browserCaptureOperator();

    $this->actingAs($operator)
        ->post(route('sessions.capture.chunks', $session), [
            'channel_index' => 1,
            'seq' => 0,
            'started_at_ms' => 1000,
            'ended_at_ms' => 2500,
            'audio' => UploadedFile::fake()->createWithContent('chunk.wav', str_repeat('browser-audio', 8)),
        ])
        ->assertStatus(202)
        ->assertJsonPath('skipped', false);

    $transcript->refresh();

    expect($transcript->segments ?? [])->not->toBeEmpty();

    Event::assertDispatched(TranscriptSegmentReceived::class);

    $this->actingAs($operator)
        ->getJson(route('sessions.capture.state', $session))
        ->assertOk()
        ->assertJsonPath('channels.0.next_seq', 1);
});

it('shares the sequence cursor between the browser and the capture daemon', function (): void {
    ['session' => $session] = browserCaptureSession();
    $operator = browserCaptureOperator();

    $machine = User::factory()->inactive()->create([
        'email' => 'chamber-capture-browser@sentria.local',
        'is_seated_member' => false,
    ]);
    $token = $machine->createToken('chamber-capture', ['chamber:capture'])->plainTextToken;

    $this->withToken($token)
        ->post('/api/sessions/'.$session->getKey().'/chamber/chunks', [
            'channel_index' => 1,
            'seq' => 0,
            'started_at_ms' => 1000,
            'ended_at_ms' => 2500,
            'audio' => UploadedFile::fake()->createWithContent('daemon.wav', str_repeat('daemon-audio', 8)),
        ])
        ->assertStatus(202)
        ->assertJsonPath('skipped', false);

    // The browser picks the cursor up on its next state poll, so an operator
    // taking over mid-sitting does not replay a sequence the daemon already used.
    $this->actingAs($operator)
        ->getJson(route('sessions.capture.state', $session))
        ->assertOk()
        ->assertJsonPath('channels.0.next_seq', 1);

    $this->actingAs($operator)
        ->post(route('sessions.capture.chunks', $session), [
            'channel_index' => 1,
            'seq' => 0,
            'started_at_ms' => 1000,
            'ended_at_ms' => 2500,
            'audio' => UploadedFile::fake()->createWithContent('replay.wav', str_repeat('replay-audio', 8)),
        ])
        ->assertStatus(202)
        ->assertJsonPath('reason', 'duplicate');
});

it('accepts a heartbeat so the settings level meter sees the browser', function (): void {
    ['session' => $session] = browserCaptureSession();
    $operator = browserCaptureOperator();

    $this->actingAs($operator)
        ->postJson(route('sessions.capture.heartbeat', $session), [
            'device' => 'Scarlett 2i2 USB',
            'channel_rms' => ['1' => 0.04],
            'listening_inputs' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $this->actingAs($operator)
        ->getJson(route('settings.chamber-channels.levels'))
        ->assertOk()
        ->assertJsonPath('online', true)
        ->assertJsonPath('device', 'Scarlett 2i2 USB');
});

it('refuses browser capture to users who cannot manage the recording', function (): void {
    ['session' => $session] = browserCaptureSession();

    $member = User::factory()->seatedMember()->create([
        'email' => 'member-browser-capture@sentria.test',
        'is_active' => true,
    ])->assignRole(UserRole::BoardMember->value);

    $this->actingAs($member)
        ->get(route('sessions.capture.show', $session))
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('sessions.capture.chunks', $session), [
            'channel_index' => 1,
            'seq' => 0,
            'started_at_ms' => 1000,
            'ended_at_ms' => 2500,
            'audio' => UploadedFile::fake()->createWithContent('nope.wav', str_repeat('nope', 32)),
        ])
        ->assertForbidden();
});
