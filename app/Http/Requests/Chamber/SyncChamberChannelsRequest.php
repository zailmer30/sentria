<?php

namespace App\Http\Requests\Chamber;

use App\Models\ChamberChannel;
use Illuminate\Foundation\Http\FormRequest;

class SyncChamberChannelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', ChamberChannel::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channels' => ['present', 'array'],
            'channels.*.id' => ['nullable', 'ulid'],
            'channels.*.channel_index' => ['required', 'integer', 'min:1', 'max:128', 'distinct'],
            'channels.*.user_id' => ['nullable', 'ulid', 'exists:users,id'],
            'channels.*.label' => ['nullable', 'string', 'max:120'],
            'channels.*.is_active' => ['required', 'boolean'],
        ];
    }
}
