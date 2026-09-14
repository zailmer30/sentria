<?php

namespace App\Http\Requests\Transcripts;

use App\Models\LegislativeSession;
use App\Models\Transcript;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CorrectTranscriptSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transcript = $this->route('transcript');

        return $transcript instanceof Transcript
            && ($this->user()?->can('correct', $transcript) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:10000'],
            'speaker_id' => ['nullable', 'ulid', Rule::exists('users', 'id')->where('is_seated_member', true)],
            'gallery' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('gallery') && $this->filled('speaker_id')) {
                $validator->errors()->add('speaker_id', __('transcripts.speaker_gallery_conflict'));

                return;
            }

            if (! $this->filled('speaker_id')) {
                return;
            }

            $transcript = $this->route('transcript');

            if (! $transcript instanceof Transcript) {
                return;
            }

            $session = $transcript->session ?? LegislativeSession::query()->find($transcript->session_id);

            if ($session === null) {
                return;
            }

            $roster = $session->attendance()->pluck('user_id')->filter()->all();
            $speakerId = $this->string('speaker_id')->toString();

            if ($roster !== [] && ! in_array($speakerId, $roster, true)) {
                $validator->errors()->add('speaker_id', __('transcripts.speaker_not_on_roster'));
            }
        });
    }
}
