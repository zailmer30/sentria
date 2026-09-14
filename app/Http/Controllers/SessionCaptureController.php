<?php

namespace App\Http\Controllers;

use App\Http\Requests\Chamber\StoreChamberChunkRequest;
use App\Http\Requests\Chamber\StoreChamberHeartbeatRequest;
use App\Models\LegislativeSession;
use App\Services\Sessions\ChamberCaptureSettings;
use App\Services\Sessions\ChamberRecordingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Browser-side chamber capture.
 *
 * The signed-in operator's tab is the recorder: an AudioWorklet runs the same
 * voice-activity detection the Python daemon runs and posts the same WAV
 * chunks here. Everything downstream of {@see ChamberRecordingService::acceptChunk()}
 * is shared, so a sitting can be recorded from either producer without the
 * transcript knowing the difference.
 */
class SessionCaptureController extends Controller
{
    public function __construct(
        private readonly ChamberRecordingService $recording,
        private readonly ChamberCaptureSettings $captureSettings,
    ) {}

    public function show(LegislativeSession $session): Response
    {
        $this->authorize('manageRecording', $session);

        return Inertia::render('Sessions/Capture', [
            'session' => [
                'id' => $session->getKey(),
                'session_number' => $session->session_number,
                'title' => $session->title,
                'status' => $session->status->getValue(),
                'status_label' => $session->status->label(),
                'venue' => $session->venue,
                'recording_enabled' => (bool) $session->recording_enabled,
            ],
            'capture' => $this->recording->captureState($session),
            'tuning' => $this->tuning(),
            'preferred_device' => $this->captureSettings->device(),
        ]);
    }

    public function state(LegislativeSession $session): JsonResponse
    {
        $this->authorize('manageRecording', $session);

        return response()->json($this->recording->captureState($session));
    }

    public function heartbeat(StoreChamberHeartbeatRequest $request, LegislativeSession $session): JsonResponse
    {
        $this->authorize('manageRecording', $session);

        $this->recording->recordHeartbeat($request->validated());

        return response()->json(['ok' => true]);
    }

    public function chunks(StoreChamberChunkRequest $request, LegislativeSession $session): JsonResponse
    {
        $this->authorize('manageRecording', $session);

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

    /**
     * @return array<string, int|float>
     */
    private function tuning(): array
    {
        return [
            'sample_rate' => (int) config('sentria.chamber.sample_rate', 16000),
            'vad_rms' => (float) config('sentria.chamber.vad_rms', 0.012),
            'min_speech_ms' => (int) config('sentria.chamber.min_speech_ms', 400),
            'silence_end_ms' => (int) config('sentria.chamber.silence_end_ms', 600),
            'max_utterance_ms' => (int) config('sentria.chamber.max_utterance_ms', 25000),
        ];
    }
}
