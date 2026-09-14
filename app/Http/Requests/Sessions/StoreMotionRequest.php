<?php

namespace App\Http\Requests\Sessions;

use App\Models\Motion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('create', Motion::class) || $user->can('agenda.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agenda_item_id' => ['required', 'ulid', 'exists:agenda_items,id'],
            'text' => ['required', 'string', 'min:3', 'max:5000'],
            'type' => ['sometimes', 'string', Rule::in(['main', 'amendment', 'subsidiary', 'procedural'])],
            'moved_by' => ['sometimes', 'nullable', 'ulid', 'exists:users,id'],
        ];
    }
}
