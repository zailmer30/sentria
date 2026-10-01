<?php

namespace App\Http\Requests\Admin;

use App\Enums\PlatePattern;
use App\Services\Branding\PlatePalette;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['accent', 'plate'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => $this->normalizeHex($value)]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $mimes = implode(',', config('sentria.branding.logo_mimes', ['jpeg', 'jpg', 'png', 'webp']));
        $maxKb = (int) config('sentria.branding.logo_max_kilobytes', 2048);

        return [
            'name' => ['required', 'string', 'max:200'],
            'short_name' => ['required', 'string', 'max:40'],
            'locality' => ['required', 'string', 'max:200'],
            'accent' => ['required', 'string', 'regex:/^#[0-9A-F]{6}$/'],
            'plate' => [
                'sometimes',
                'required',
                'string',
                'regex:/^#[0-9A-F]{6}$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && ! app(PlatePalette::class)->isReadable($value)) {
                        $fail(__('settings.branding.plate_too_light'));
                    }
                },
            ],
            'plate_pattern' => ['sometimes', 'required', Rule::enum(PlatePattern::class)],
            'logo' => ['nullable', 'file', 'image', 'mimes:'.$mimes, 'max:'.$maxKb],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }

    private function normalizeHex(string $value): string
    {
        $trimmed = strtoupper(trim($value));

        if ($trimmed !== '' && ! str_starts_with($trimmed, '#')) {
            $trimmed = '#'.$trimmed;
        }

        if (preg_match('/^#([0-9A-F]{3})$/', $trimmed, $short) === 1) {
            $digits = $short[1];
            $trimmed = sprintf('#%s%s%s%s%s%s', $digits[0], $digits[0], $digits[1], $digits[1], $digits[2], $digits[2]);
        }

        return $trimmed;
    }
}
