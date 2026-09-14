<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateProfilePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\UserAvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $this->requireUser($request);
        $user->load('roles:id,name,label');

        return Inertia::render('Profile/Edit', [
            'user' => $this->profilePayload($user),
        ]);
    }

    public function update(UpdateProfileRequest $request, UserAvatarService $avatars): RedirectResponse
    {
        $user = $this->requireUser($request);
        $validated = $request->validated();

        $firstName = $validated['first_name'];
        $lastName = $validated['last_name'];
        $displayName = $validated['display_name'] ?? null;

        $user->update([
            'first_name' => $firstName,
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $lastName,
            'name_suffix' => $validated['name_suffix'] ?? null,
            'honorific' => $validated['honorific'] ?? null,
            'display_name' => $displayName ?: trim("{$firstName} {$lastName}"),
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'locale' => $validated['locale'],
        ]);

        $this->syncAvatar($user, $request->file('avatar'), $request->boolean('remove_avatar'), $avatars);

        return redirect()
            ->route('profile.edit')
            ->with('success', 'profile.updated');
    }

    public function updatePassword(UpdateProfilePasswordRequest $request): RedirectResponse
    {
        $user = $this->requireUser($request);
        $validated = $request->validated();

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'password_changed_at' => now(),
        ])->save();

        return redirect()
            ->route('profile.edit')
            ->with('success', 'profile.password_updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'name_suffix' => $user->name_suffix,
            'honorific' => $user->honorific,
            'display_name' => $user->display_name ?? $user->fullName(),
            'email' => $user->email,
            'employee_number' => $user->employee_number,
            'position_title' => $user->position_title,
            'district' => $user->district,
            'phone' => $user->phone,
            'locale' => $user->locale ?? 'en',
            'avatar_url' => $user->avatarUrl(),
            'roles' => $user->roles->map(fn (Role $role): array => [
                'id' => $role->getKey(),
                'name' => $role->name,
                'label' => $role->label ?: $role->name,
            ])->values()->all(),
        ];
    }

    private function syncAvatar(User $user, mixed $file, bool $remove, UserAvatarService $avatars): void
    {
        if ($file instanceof UploadedFile) {
            $avatars->store($user, $file);

            return;
        }

        if ($remove) {
            $avatars->remove($user);
        }
    }
}
