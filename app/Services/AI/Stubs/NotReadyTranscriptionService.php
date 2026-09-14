<?php

namespace App\Services\AI\Stubs;

use App\Contracts\AI\TranscriptionService;
use App\DTO\AI\TranscriptionResult;

class NotReadyTranscriptionService implements TranscriptionService
{
    use ThrowsNotReady;

    /**
     * @param  list<string>  $agendaKeywords
     */
    public function transcribeSessionAudio(
        string $absolutePath,
        string $mime,
        array $agendaKeywords = [],
        string $languageMode = 'auto',
        array $options = [],
    ): TranscriptionResult {
        unset($absolutePath, $mime, $agendaKeywords, $languageMode, $options);

        $this->notReady('4b');
    }
}
