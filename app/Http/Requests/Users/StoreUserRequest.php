<?php

namespace App\Http\Requests\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $canAssign = $this->user()?->can('assign', Role::class) ?? false;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'name_suffix' => ['nullable', 'string', 'max:30'],
            'honorific' => ['nullable', 'string', 'max:30'],
            'display_name' => ['nullable', 'string', 'max:200'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            'employee_number' => ['nullable', 'string', 'max:50'],
            'position_title' => ['nullable', 'string', 'max:150'],
            'district' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'locale' => ['required', 'string', Rule::in(['en', 'fil'])],
            'is_active' => ['required', 'boolean'],
            'is_seated_member' => ['required', 'boolean'],
            'avatar' => $this->avatarRules(),
            'role_ids' => [$canAssign ? 'nullable' : 'prohibited', 'array'],
            'role_ids.*' => ['ulid', 'exists:roles,id'],
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
