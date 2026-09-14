<?php

use App\Events\TranscriptSegmentReceived;
use App\Models\ChamberChannel;
use App\Models\LegislativeSession;
use App\Models\Transcript;
use App\Models\User;
use App\Services\Sessions\ChamberCaptureSettings;
use App\States\Session\Adjourned;
use App\States\Session\Suspended;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

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

function captureTokenUser(): array
{
    $user = User::factory()->inactive()->create([
        'email' => 'chamber-capture-test@sentria.local',
        'display_name' => 'Chamber Capture',
        'is_seated_member' => false,
    ]);

    $token = $user->createToken('chamber-capture', ['chamber:capture'])->plainTextToken;

    return [$user, $token];
}

function liveSessionWithSeats(): array
{
    $one = User::factory()->seatedMember()->create([
        'email' => 'member-one-capture@sentria.test',
        'display_name' => 'Hon. Member One',
        'is_active' => true,
    ]);
    $two = User::factory()->seatedMember()->create([
        'email' => 'member-two-capture@sentria.test',
        'display_name' => 'Hon. Member Two',
        'is_active' => true,
    ]);

    ChamberChannel::factory()->create([
        'channel_index' => 1,
        'user_id' => $one->getKey(),
        'label' => 'Seat 1',
    ]);
    ChamberChannel::factory()->create([
        'channel_index' => 2,
        'user_id' => $two->getKey(),
        'label' => 'Seat 2',
    ]);

    $session = LegislativeSession::factory()->inSession()->create([
        'recording_enabled' => true,
        'capture_mode' => 'per_seat',
        'actual_start_at' => now()->subMinutes(5),
    ]);

    $transcript = Transcript::query()->create([
        'session_id' => $session->getKey(),
        'source' => 'chamber_channels',
        'status' => 'processing',
        'language' => 'und',
        'channel_map_snapshot' => [
            [
                'channel_index' => 1,
                'user_id' => $one->getKey(),
                'label' => 'Seat 1',
                'display_name' => $one->display_name,
                'speaker' => $one->display_name,
            ],
            [
                'channel_index' => 2,
                'user_id' => $two->getKey(),
                'label' => 'Seat 2',
                'display_name' => $two->display_name,
                'speaker' => $two->display_name,
            ],
        ],
        'segments' => [],
        'started_at' => $session->actual_start_at,
    ]);

    return compact('session', 'transcript', 'one', 'two');
}

function chunkFile(string $name, string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

it('requires the chamber capture token ability', function (): void {
    ['session' => $session] = liveSessionWithSeats();
    $user = User::factory()->create(['is_active' => true]);
    $token = $user->createToken('wrong', ['documents:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/chamber/recording')
        ->assertForbidden();

    $this->withToken($token)
        ->post('/api/sessions/'.$session->getKey().'/chamber/chunks', [
            'channel_index' => 1,
            'seq' => 0,
            'started_at_ms' => 0,
            'ended_at_ms' => 1000,
            'audio' => chunkFile('a.wav', str_repeat('x', 64)),
        ])
        ->assertForbidden();
});

it('returns daemon state for a live session', function (): void {
    ['session' => $session] = liveSessionWithSeats();
    [, $token] = captureTokenUser();

    $this->withToken($token)
        ->getJson('/api/chamber/recording')
        ->assertOk()
        ->assertJsonPath('session_id', $session->getKey())
        ->assertJsonPath('capture', 'record')
        ->assertJsonPath('feed', 'per_seat')
        ->assertJsonPath('recording_enabled', true)
        ->assertJsonPath('device', null);
});

it('tells the capture computer which recording device to open', function (): void {
    ['session' => $session] = liveSessionWithSeats();
    [, $token] = captureTokenUser();

    app(ChamberCaptureSettings::class)->setDevice(4, 'Focusrite USB ASIO');

    $this->withToken($token)
        ->getJson('/api/chamber/recording')
        ->assertOk()
        ->assertJsonPath('session_id', $session->getKey())
        ->assertJsonPath('device.index', 4)
        ->assertJsonPath('device.name', 'Focusrite USB ASIO');
});

it('transcribes overlapping chunks onto different speakers', function (): void {
    Event::fake([TranscriptSegmentReceived::class]);

    ['session' => $session, 'transcript' => $transcript, 'one' => $one, 'two' => $two] = liveSessionWithSeats();
    [, $token] = captureTokenUser();

    $this->withToken($token)
        ->post('/api/sessions/'.$session->getKey().'/chamber/chunks', [
            'channel_index' => 1,
            'seq' => 0,
            'started_at_ms' => 12_000,
            'ended_at_ms' => 18_400,
            'audio' => chunkFile('one.wav', str_repeat('member-one-audio', 8)),
        ])
        ->assertStatus(202);

    $this->withToken($token)
        ->post('/api/sessions/'.$session->getKey().'/chamber/chunks', [
            'channel_index' => 2,
            'seq' => 0,
            'started_at_ms' => 14_100,
            'ended_at_ms' => 16_000,
            'audio' => chunkFile('two.wav', str_repeat('member-two-audio', 8)),
        ])
        ->assertStatus(202);

    $transcript->refresh();
    $segments = $transcript->segments ?? [];

    expect($segments)->not->toBeEmpty();

    $speakers = collect($segments)->pluck('speaker')->unique()->values();

    expect($speakers)->toContain($one->display_name)
        ->and($speakers)->toContain($two->display_name);

    $starts = collect($segments)->pluck('start');
    expect($starts->min())->toBeGreaterThanOrEqual(12.0)
        ->and($starts->contains(fn (float $start): bool => $start >= 14.0))->toBeTrue();

    Event::assertDispatched(TranscriptSegmentReceived::class);
});

it('skips bleed-sized chunks and rejects chunks while suspended or adjourned', function (): void {
    ['session' => $session] = liveSessionWithSeats();
    [, $token] = captureTokenUser();

    $this->withToken($token)
        ->post('/api/sessions/'.$session->getKey().'/chamber/chunks', [
            'channel_index' => 1,
            'seq' => 0,
            'started_at_ms' => 0,
            'ended_at_ms' => 100,
            'audio' => chunkFile('bleed.wav', 'tiny'),
        ])
        ->assertStatus(202)
        ->assertJsonPath('skipped', true);

    $session->update(['status' => Suspended::$name]);

    $this->withToken($token)
        ->post('/api/sessions/'.$session->getKey().'/chamber/chunks', [
            'channel_index' => 1,
            'seq' => 1,
            'started_at_ms' => 1000,
            'ended_at_ms' => 2000,
            'audio' => chunkFile('ok.wav', str_repeat('ok', 64)),
        ])
        ->assertStatus(409);

    $session->update(['status' => Adjourned::$name]);

    $this->withToken($token)
        ->post('/api/sessions/'.$session->getKey().'/chamber/chunks', [
            'channel_index' => 1,
            'seq' => 2,
            'started_at_ms' => 1000,
            'ended_at_ms' => 2000,
            'audio' => chunkFile('later.wav', str_repeat('ok', 64)),
        ])
        ->assertStatus(409);
});

it('accepts a heartbeat from the capture daemon', function (): void {
    [, $token] = captureTokenUser();

    $this->withToken($token)
        ->postJson('/api/chamber/heartbeat', [
            'device' => 'Focusrite',
            'channel_rms' => ['1' => 0.02, '2' => 0.001],
            'disk_free_bytes' => 1_000_000_000,
            'listening_inputs' => 8,
            'devices' => [
                ['index' => 0, 'name' => 'Focusrite', 'input_count' => 8, 'is_default' => true],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);
});

it('accepts mixer-mix chunks without a seat map and leaves speakers unassigned', function (): void {
    Event::fake([TranscriptSegmentReceived::class]);

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

    [, $token] = captureTokenUser();

    $this->withToken($token)
        ->getJson('/api/chamber/recording')
        ->assertOk()
        ->assertJsonPath('feed', 'mixer_mix')
        ->assertJsonPath('channels.0.channel_index', 1)
        ->assertJsonPath('channels.0.label', 'Mix')
        ->assertJsonPath('channels.0.next_seq', 0);

    $this->withToken($token)
        ->post('/api/sessions/'.$session->getKey().'/chamber/chunks', [
            'channel_index' => 1,
            'seq' => 0,
            'started_at_ms' => 1000,
            'ended_at_ms' => 2500,
            'audio' => chunkFile('mix.wav', str_repeat('mixer-audio', 8)),
        ])
        ->assertStatus(202)
        ->assertJsonPath('skipped', false);

    $transcript->refresh();
    $segments = $transcript->segments ?? [];

    expect($segments)->not->toBeEmpty();

    foreach ($segments as $segment) {
        expect($segment['attributed'] ?? true)->toBeFalse()
            ->and($segment['speaker'] ?? null)->toBeNull()
            ->and($segment['speaker_id'] ?? null)->toBeNull();
    }

    Event::assertDispatched(TranscriptSegmentReceived::class);
});

it('transcribes again when the capture computer restarts sequence numbers', function (): void {
    Event::fake([TranscriptSegmentReceived::class]);

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

    [, $token] = captureTokenUser();

    $this->withToken($token)
        ->post('/api/sessions/'.$session->getKey().'/chamber/chunks', [
            'channel_index' => 1,
            'seq' => 0,
            'started_at_ms' => 1000,
            'ended_at_ms' => 2500,
            'audio' => chunkFile('first.wav', str_repeat('first-pass-audio', 8)),
        ])
        ->assertStatus(202)
        ->assertJsonPath('skipped', false);

    $this->withToken($token)
        ->getJson('/api/chamber/recording')
        ->assertOk()
        ->assertJsonPath('channels.0.next_seq', 1);

    $this->withToken($token)
        ->post('/api/sessions/'.$session->getKey().'/chamber/chunks', [
            'channel_index' => 1,
            'seq' => 0,
            'started_at_ms' => 1000,
            'ended_at_ms' => 2500,
            'audio' => chunkFile('replay.wav', str_repeat('replay-audio', 8)),
        ])
        ->assertStatus(202)
        ->assertJsonPath('skipped', true)
        ->assertJsonPath('reason', 'duplicate');

    $this->withToken($token)
        ->post('/api/sessions/'.$session->getKey().'/chamber/chunks', [
            'channel_index' => 1,
            'seq' => 0,
            'started_at_ms' => 48_000,
            'ended_at_ms' => 51_200,
            'audio' => chunkFile('restart.wav', str_repeat('second-pass-audio', 8)),
        ])
        ->assertStatus(202)
        ->assertJsonPath('skipped', false);

    $transcript->refresh();
    $segments = $transcript->segments ?? [];
    $indexes = collect($segments)->pluck('index')->unique();

    expect($segments)->not->toBeEmpty()
        ->and($indexes->count())->toBeGreaterThan(2);

    $this->withToken($token)
        ->getJson('/api/chamber/recording')
        ->assertOk()
        ->assertJsonPath('channels.0.next_seq', 1);
});
