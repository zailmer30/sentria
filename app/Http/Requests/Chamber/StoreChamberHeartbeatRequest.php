<?php

namespace App\Http\Requests\Chamber;

use Illuminate\Foundation\Http\FormRequest;

class StoreChamberHeartbeatRequest extends FormRequest
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
            'device' => ['nullable', 'string', 'max:120'],
            'disk_free_bytes' => ['nullable', 'integer', 'min:0'],
            'channel_rms' => ['nullable', 'array'],
            'channel_rms.*' => ['numeric'],
            'listening_inputs' => ['nullable', 'integer', 'min:1', 'max:128'],
            'devices' => ['nullable', 'array', 'max:64'],
            'devices.*.index' => ['nullable', 'integer', 'min:0'],
            'devices.*.name' => ['required_with:devices', 'string', 'max:180'],
            'devices.*.input_count' => ['required_with:devices', 'integer', 'min:1', 'max:128'],
            'devices.*.is_default' => ['nullable', 'boolean'],
        ];
    }
}
