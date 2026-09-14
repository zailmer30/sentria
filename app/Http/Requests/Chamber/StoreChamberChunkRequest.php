<?php

namespace App\Http\Requests\Chamber;

use Illuminate\Foundation\Http\FormRequest;

class StoreChamberChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channel_index' => ['required', 'integer', 'min:1', 'max:128'],
            'seq' => ['required', 'integer', 'min:0'],
            'started_at_ms' => ['required', 'integer', 'min:0'],
            'ended_at_ms' => ['required', 'integer', 'gte:started_at_ms'],
            'audio' => ['required', 'file', 'max:20480', 'mimetypes:audio/wav,audio/x-wav,audio/mpeg,audio/mp4,audio/x-m4a,audio/webm,video/webm,application/octet-stream'],
        ];
    }
}
