<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'name_suffix' => ['nullable', 'string', 'max:30'],
            'honorific' => ['nullable', 'string', 'max:30'],
            'display_name' => ['nullable', 'string', 'max:200'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->getKey()),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'locale' => ['required', 'string', Rule::in(['en', 'fil'])],
            'avatar' => $this->avatarRules(),
            'remove_avatar' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<string>
     */
    private function avatarRules(): array
    {
        $mimes = implode(',', config('sentria.users.avatar_mimes', ['jpeg', 'jpg', 'png', 'webp']));
        $maxKb = (int) config('sentria.users.avatar_max_kilobytes', 2048);

        return ['nullable', 'file', 'image', 'mimes:'.$mimes, 'max:'.$maxKb];
    }
}
