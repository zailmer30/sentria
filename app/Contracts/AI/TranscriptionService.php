<?php

namespace App\Contracts\AI;

use App\DTO\AI\TranscriptionResult;

interface TranscriptionService
{
    /**
     * Transcribe session audio into timestamped segments.
     *
     * Official vote results must never be inferred from this output — they
     * always come from the `votes` table.
     *
     * $languageMode `auto` must not lock the provider to the UI locale.
     *
     * $options['source'] `upload` (file ingest), `chamber` (per-seat live
     * chunks; speakers come from the mic map), or `chamber_mix` (mixer mix;
     * diarization splits turns, speakers stay unassigned until the clerk).
     *
     * @param  list<string>  $agendaKeywords
     * @param  array{source?: 'upload'|'chamber'|'chamber_mix'}  $options
     */
    public function transcribeSessionAudio(
        string $absolutePath,
        string $mime,
        array $agendaKeywords = [],
        string $languageMode = 'auto',
        array $options = [],
    ): TranscriptionResult;
}
