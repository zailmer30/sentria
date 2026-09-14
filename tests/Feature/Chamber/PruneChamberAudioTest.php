<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

it('prunes old chamber audio and keeps transcript files', function (): void {
    Storage::fake('local');

    Storage::disk('local')->put('sessions/abc/chamber/1/0.wav', 'old');
    Storage::disk('local')->put('sessions/abc/transcripts/keep.wav', 'keep');

    $old = now()->subDays(40)->timestamp;
    touch(Storage::disk('local')->path('sessions/abc/chamber/1/0.wav'), $old);

    config(['sentria.chamber.audio_retention_days' => 30]);

    Artisan::call('sentria:prune-chamber-audio');

    expect(Storage::disk('local')->exists('sessions/abc/chamber/1/0.wav'))->toBeFalse()
        ->and(Storage::disk('local')->exists('sessions/abc/transcripts/keep.wav'))->toBeTrue();
});
