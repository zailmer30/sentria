<?php

namespace App\Http\Requests\Transcripts;

use App\Models\LegislativeSession;
use Illuminate\Foundation\Http\FormRequest;

class StoreTranscriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('session');

        return $session instanceof LegislativeSession
            && ($this->user()?->can('transcribeSession', $session) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'audio' => [
                'required',
                'file',
                'max:'.(int) config('sentria.documents.max_upload_size_kb', 51200),
                'mimes:wav,mp3,m4a,mp4,webm',
            ],
            'agenda_item_id' => ['nullable', 'ulid', 'exists:agenda_items,id'],
        ];
    }
}
