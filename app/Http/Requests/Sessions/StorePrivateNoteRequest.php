<?php

namespace App\Http\Requests\Sessions;

use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\PrivateNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrivateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('createForNotable', [
            PrivateNote::class,
            (string) $this->input('notable_type'),
            (string) $this->input('notable_id'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notable_type' => ['required', 'string', Rule::in([LegislativeSession::class, Document::class])],
            'notable_id' => ['required', 'ulid'],
            'body' => ['required', 'string', 'max:5000'],
            'page_number' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
