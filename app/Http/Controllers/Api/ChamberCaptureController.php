<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chamber\StoreChamberChunkRequest;
use App\Http\Requests\Chamber\StoreChamberHeartbeatRequest;
use App\Models\LegislativeSession;
use App\Services\Sessions\ChamberRecordingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ChamberCaptureController extends Controller
{
    public function __construct(
        private readonly ChamberRecordingService $recording,
    ) {}

    public function state(Request $request): JsonResponse
    {
        $this->assertCaptureToken($request);

        return response()->json($this->recording->daemonState());
    }

    public function heartbeat(StoreChamberHeartbeatRequest $request): JsonResponse
    {
        $this->assertCaptureToken($request);
        $this->recording->recordHeartbeat($request->validated());

        return response()->json(['ok' => true]);
    }

    public function storeChunk(StoreChamberChunkRequest $request, LegislativeSession $session): JsonResponse
    {
        $this->assertCaptureToken($request);

        $audio = $request->file('audio');
        abort_unless($audio instanceof UploadedFile, 422);

        $result = $this->recording->acceptChunk(
            $session,
            (int) $request->integer('channel_index'),
            (int) $request->integer('seq'),
            (int) $request->integer('started_at_ms'),
            (int) $request->integer('ended_at_ms'),
            $audio,
        );

        return response()->json($result, 202);
    }

    private function assertCaptureToken(Request $request): void
    {
        $ability = (string) config('sentria.chamber.token_ability', 'chamber:capture');
        $user = $request->user();

        abort_unless($user !== null && $user->tokenCan($ability), 403);
    }
}
