<?php

namespace App\Http\Requests\Chamber;

use App\Models\ChamberChannel;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChamberDeviceRequest extends FormRequest
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
            'device_index' => ['nullable', 'integer', 'min:0', 'max:512'],
            'device_name' => ['nullable', 'string', 'max:180'],
        ];
    }
}
