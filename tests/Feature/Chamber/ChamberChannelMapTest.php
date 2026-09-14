<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ChamberChannel;
use App\Models\User;
use App\Services\Sessions\ChamberCaptureSettings;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function chamberAdmin(): User
{
    return User::factory()->create([
        'email' => 'admin-chamber@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::SystemAdministrator->value);
}

function chamberSecretariat(): User
{
    return User::factory()->create([
        'email' => 'secretariat-chamber@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);
}

it('lets secretariat set the recording device used by the capture computer', function (): void {
    $secretariat = chamberSecretariat();

    $this->actingAs($secretariat)
        ->put(route('settings.chamber-channels.device'), [
            'device_index' => 4,
            'device_name' => 'Focusrite USB ASIO',
        ])
        ->assertRedirect();

    expect(app(ChamberCaptureSettings::class)->device())->toMatchArray([
        'index' => 4,
        'name' => 'Focusrite USB ASIO',
    ]);

    $this->actingAs($secretariat)
        ->put(route('settings.chamber-channels.device'), [
            'device_index' => null,
            'device_name' => null,
        ])
        ->assertRedirect();

    expect(app(ChamberCaptureSettings::class)->device())->toBeNull();
});

it('lets secretariat choose a recording device by name only', function (): void {
    $secretariat = chamberSecretariat();

    $this->actingAs($secretariat)
        ->put(route('settings.chamber-channels.device'), [
            'device_index' => null,
            'device_name' => 'Headset (Galaxy Buds FE) (Bluetooth)',
        ])
        ->assertRedirect();

    expect(app(ChamberCaptureSettings::class)->device())->toMatchArray([
        'index' => null,
        'name' => 'Headset (Galaxy Buds FE) (Bluetooth)',
    ]);
});

it('forbids board members from choosing the recording device', function (): void {
    $member = User::factory()->create([
        'email' => 'member-chamber-device@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole(UserRole::BoardMember->value);

    $this->actingAs($member)
        ->put(route('settings.chamber-channels.device'), [
            'device_index' => 1,
            'device_name' => 'Built-in Microphone',
        ])
        ->assertForbidden();
});

it('lets secretariat set the default capture mode', function (): void {
    $secretariat = chamberSecretariat();

    $this->actingAs($secretariat)
        ->put(route('settings.chamber-channels.feed'), [
            'default_capture_mode' => 'per_seat',
        ])
        ->assertRedirect();

    expect(app(ChamberCaptureSettings::class)->defaultFeed()->value)->toBe('per_seat');
});

it('lets secretariat save a channel map including a gallery channel', function (): void {
    $secretariat = chamberSecretariat();
    $member = User::factory()->seatedMember()->create([
        'email' => 'seat-one@sentria.test',
        'is_active' => true,
        'display_name' => 'Hon. Seat One',
    ])->assignRole(UserRole::BoardMember->value);

    $this->actingAs($secretariat)
        ->put(route('settings.chamber-channels.update'), [
            'channels' => [
                [
                    'channel_index' => 1,
                    'user_id' => $member->getKey(),
                    'label' => 'Seat 1',
                    'is_active' => true,
                ],
                [
                    'channel_index' => 8,
                    'user_id' => null,
                    'label' => 'Gallery / resource',
                    'is_active' => true,
                ],
            ],
        ])
        ->assertRedirect();

    expect(ChamberChannel::query()->count())->toBe(2)
        ->and(ChamberChannel::query()->where('channel_index', 1)->first()?->user_id)->toBe($member->getKey())
        ->and(ChamberChannel::query()->whereNull('user_id')->first()?->label)->toBe('Gallery / resource');
});

it('lets secretariat read live microphone levels', function (): void {
    $secretariat = chamberSecretariat();

    $this->actingAs($secretariat)
        ->getJson(route('settings.chamber-channels.levels'))
        ->assertOk()
        ->assertJsonPath('online', false);

    Cache::put(
        'chamber.heartbeat',
        [
            'device' => 'Test interface',
            'channel_rms' => ['1' => 0.04, '2' => 0.001],
            'disk_free_bytes' => null,
            'listening_inputs' => 2,
            'devices' => [
                ['index' => 0, 'name' => 'Test interface', 'input_count' => 2, 'is_default' => true],
            ],
            'received_at' => now()->toIso8601String(),
        ],
        now()->addMinutes(2),
    );

    $this->actingAs($secretariat)
        ->getJson(route('settings.chamber-channels.levels'))
        ->assertOk()
        ->assertJsonPath('online', true)
        ->assertJsonPath('channel_rms.1', 0.04)
        ->assertJsonPath('listening_inputs', 2)
        ->assertJsonPath('devices.0.name', 'Test interface');
});

it('forbids board members from reading microphone levels', function (): void {
    $member = User::factory()->create([
        'email' => 'member-chamber-levels@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole(UserRole::BoardMember->value);

    $this->actingAs($member)
        ->getJson(route('settings.chamber-channels.levels'))
        ->assertForbidden();
});

it('forbids board members from editing the chamber map', function (): void {
    $member = User::factory()->create([
        'email' => 'member-chamber@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole(UserRole::BoardMember->value);

    $this->actingAs($member)
        ->get(route('settings.chamber-channels.edit'))
        ->assertForbidden();
});

it('renders the chamber mapping page for administrators', function (): void {
    $this->actingAs(chamberAdmin())
        ->get(route('settings.chamber-channels.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/ChamberChannels')
            ->has('channels')
            ->has('members')
            ->has('can.update')
            ->has('capture_base_url')
            ->has('recording_device')
            ->where('default_capture_mode', 'mixer_mix'));
});

it('lets secretariat open chamber microphone setup', function (): void {
    $this->actingAs(chamberSecretariat())
        ->get(route('settings.chamber-channels.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/ChamberChannels')
            ->where('can.update', true));
});

it('lets secretariat download a recording starter that includes capture.py', function (): void {
    $response = $this->actingAs(chamberSecretariat())
        ->post(route('settings.chamber-channels.starter'));

    $response->assertOk()->assertDownload('sentria-chamber-recording.zip');

    $zip = new ZipArchive;
    expect($zip->open($response->getFile()->getPathname()))->toBeTrue()
        ->and($zip->getFromName('capture.py'))->not->toBeFalse()
        ->and($zip->getFromName('requirements.txt'))->toContain('sounddevice')
        ->and($zip->getFromName('start.bat'))->toContain('SENTRIA_URL')
        ->and($zip->getFromName('start.bat'))->toContain('http://localhost')
        ->and($zip->getFromName('start.bat'))->toContain('bootstrap-python.ps1')
        ->and($zip->getFromName('bootstrap-python.ps1'))->toContain('python.org/ftp/python')
        ->and($zip->getFromName('bootstrap-python.ps1'))->toContain('WindowsApps');
    $zip->close();

    $machine = User::query()->where('email', config('sentria.chamber.machine_email'))->first();

    expect($machine)->not->toBeNull()
        ->and($machine->tokens()->where('name', 'chamber-capture')->count())->toBe(1)
        ->and(AuditLog::query()->where('event', 'chamber.capture.starter_issued')->exists())->toBeTrue();
});

it('replaces the previous capture token when a starter is downloaded again', function (): void {
    $secretariat = chamberSecretariat();

    $this->actingAs($secretariat)->post(route('settings.chamber-channels.starter'))->assertOk();

    $machine = User::query()->where('email', config('sentria.chamber.machine_email'))->first();
    $firstId = $machine?->tokens()->value('id');

    $this->actingAs($secretariat)->post(route('settings.chamber-channels.starter'))->assertOk();

    expect($machine?->fresh()->tokens()->count())->toBe(1)
        ->and($machine?->fresh()->tokens()->value('id'))->not->toBe($firstId);
});

it('forbids board members from downloading the recording starter', function (): void {
    $member = User::factory()->create([
        'email' => 'member-chamber-starter@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole(UserRole::BoardMember->value);

    $this->actingAs($member)
        ->post(route('settings.chamber-channels.starter'))
        ->assertForbidden();
});
