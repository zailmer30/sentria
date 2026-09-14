<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChamberFeed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chamber\SyncChamberChannelsRequest;
use App\Http\Requests\Chamber\UpdateChamberDeviceRequest;
use App\Http\Requests\Chamber\UpdateChamberFeedRequest;
use App\Models\ChamberChannel;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Sessions\ChamberCaptureSettings;
use App\Services\Sessions\ChamberCaptureStarter;
use App\Services\Sessions\ChamberChannelService;
use App\Services\Sessions\ChamberRecordingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChamberChannelController extends Controller
{
    public function __construct(
        private readonly ChamberChannelService $channels,
        private readonly ChamberCaptureSettings $captureSettings,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(Request $request): Response
    {
        $this->authorize('viewAny', ChamberChannel::class);

        $user = $this->requireUser($request);

        $channels = ChamberChannel::query()
            ->with('member')
            ->orderBy('channel_index')
            ->get()
            ->map(fn (ChamberChannel $channel): array => $this->channels->serialize($channel))
            ->values()
            ->all();

        $members = User::query()
            ->where('is_seated_member', true)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get(['id', 'display_name'])
            ->map(fn (User $member): array => [
                'id' => $member->getKey(),
                'display_name' => $member->display_name,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Settings/ChamberChannels', [
            'channels' => $channels,
            'members' => $members,
            'capture_base_url' => rtrim($request->getSchemeAndHttpHost(), '/'),
            'default_capture_mode' => $this->captureSettings->defaultFeed()->value,
            'recording_device' => $this->captureSettings->device(),
            'can' => [
                'update' => $user->can('update', ChamberChannel::class),
            ],
        ]);
    }

    public function downloadStarter(Request $request, ChamberCaptureStarter $starter): BinaryFileResponse
    {
        $this->authorize('update', ChamberChannel::class);

        $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');
        $path = $starter->zipPath($baseUrl);

        $this->audit->record(
            event: 'chamber.capture.starter_issued',
            category: 'settings',
            actor: $this->requireUser($request),
            context: [
                'base_url' => $baseUrl,
            ],
            message: 'Chamber recording starter downloaded; previous capture key replaced.',
        );

        return response()->download($path, 'sentria-chamber-recording.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    public function levels(ChamberRecordingService $recording): JsonResponse
    {
        $this->authorize('viewAny', ChamberChannel::class);

        $heartbeat = $recording->lastHeartbeat();
        $floor = (float) config('sentria.chamber.vad_rms', 0.012);

        if ($heartbeat === null) {
            return response()->json([
                'online' => false,
                'device' => null,
                'received_at' => null,
                'channel_rms' => (object) [],
                'speech_floor' => $floor,
                'listening_inputs' => null,
                'devices' => [],
            ]);
        }

        $rms = [];

        foreach ($heartbeat['channel_rms'] ?? [] as $index => $value) {
            $rms[(string) $index] = round((float) $value, 6);
        }

        $devices = [];

        foreach ($heartbeat['devices'] ?? [] as $device) {
            if (! is_array($device)) {
                continue;
            }

            $name = isset($device['name']) && is_string($device['name']) ? trim($device['name']) : '';

            if ($name === '') {
                continue;
            }

            $devices[] = [
                'index' => (int) ($device['index'] ?? 0),
                'name' => $name,
                'input_count' => (int) ($device['input_count'] ?? 0),
                'is_default' => (bool) ($device['is_default'] ?? false),
            ];
        }

        return response()->json([
            'online' => true,
            'device' => $heartbeat['device'] ?? null,
            'received_at' => $heartbeat['received_at'] ?? null,
            'channel_rms' => $rms,
            'speech_floor' => $floor,
            'listening_inputs' => isset($heartbeat['listening_inputs']) ? (int) $heartbeat['listening_inputs'] : null,
            'devices' => $devices,
        ]);
    }

    public function update(SyncChamberChannelsRequest $request): RedirectResponse
    {
        $this->channels->sync($request->validated('channels'));

        $this->audit->record(
            event: 'chamber.channels.updated',
            category: 'settings',
            auditable: null,
            actor: $this->requireUser($request),
            context: [
                'channel_count' => count($request->validated('channels')),
            ],
        );

        return back()->with('success', 'chamber.saved');
    }

    public function updateDefaultFeed(UpdateChamberFeedRequest $request): RedirectResponse
    {
        $feed = ChamberFeed::fromMixed($request->validated('default_capture_mode'));
        $this->captureSettings->setDefaultFeed($feed);

        $this->audit->record(
            event: 'chamber.default_capture_mode.updated',
            category: 'settings',
            actor: $this->requireUser($request),
            new: ['default_capture_mode' => $feed->value],
        );

        return back()->with('success', 'chamber.capture_mode_saved');
    }

    public function updateDevice(UpdateChamberDeviceRequest $request): RedirectResponse
    {
        $index = $request->validated('device_index');
        $name = $request->validated('device_name');
        $previous = $this->captureSettings->device();

        $this->captureSettings->setDevice(
            is_numeric($index) ? (int) $index : null,
            is_string($name) && trim($name) !== '' ? trim($name) : null,
        );

        $this->audit->record(
            event: 'chamber.device.updated',
            category: 'settings',
            actor: $this->requireUser($request),
            old: $previous,
            new: $this->captureSettings->device(),
        );

        return back()->with('success', 'chamber.device_saved');
    }
}
