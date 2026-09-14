<?php

namespace App\Http\Requests\Sessions;

use App\Enums\ChamberFeed;
use App\Models\LegislativeSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSessionRecordingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('session');

        return $session instanceof LegislativeSession
            && ($this->user()?->can('manageRecording', $session) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recording_enabled' => ['sometimes', 'boolean'],
            'capture_mode' => ['sometimes', 'string', Rule::enum(ChamberFeed::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->exists('recording_enabled') && ! $this->filled('capture_mode')) {
                $validator->errors()->add('recording_enabled', __('validation.required'));
            }
        });
    }
}
