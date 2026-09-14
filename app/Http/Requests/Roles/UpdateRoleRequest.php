<?php

namespace App\Http\Requests\Roles;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role && ($this->user()?->can('update', $role) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'precedence' => ['required', 'integer', 'min:0', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
