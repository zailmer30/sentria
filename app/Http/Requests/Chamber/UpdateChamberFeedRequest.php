<?php

namespace App\Http\Requests\Chamber;

use App\Enums\ChamberFeed;
use App\Models\ChamberChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChamberFeedRequest extends FormRequest
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
            'default_capture_mode' => ['required', 'string', Rule::enum(ChamberFeed::class)],
        ];
    }
}
