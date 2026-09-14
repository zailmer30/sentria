<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\UserAvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $search = trim((string) $request->query('search', ''));
        $active = $request->query('active');

        $users = User::query()
            ->with('roles:id,name,label')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('display_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->when($active === '1' || $active === '0', fn ($query) => $query->where('is_active', $active === '1'))
            ->orderBy('display_name')
            ->paginate(20)
            ->withQueryString();

        $actor = $this->requireUser($request);

        return Inertia::render('Users/Index', [
            'users' => $users->through(fn (User $user): array => $this->userRow($user)),
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'active' => in_array($active, ['0', '1'], true) ? $active : null,
            ],
            'can' => [
                'create' => $actor->can('create', User::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        $actor = $this->requireUser($request);

        return Inertia::render('Users/Create', [
            'roles' => $this->roleOptions(),
            'can' => [
                'assign' => $actor->can('assign', Role::class),
            ],
        ]);
    }

    public function store(StoreUserRequest $request, UserAvatarService $avatars): RedirectResponse
    {
        $validated = $request->validated();
        $actor = $this->requireUser($request);

        $user = DB::transaction(function () use ($validated, $actor): User {
            $firstName = $validated['first_name'];
            $lastName = $validated['last_name'];
            $displayName = $validated['display_name'] ?? null;

            $user = User::query()->create([
                'first_name' => $firstName,
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $lastName,
                'name_suffix' => $validated['name_suffix'] ?? null,
                'honorific' => $validated['honorific'] ?? null,
                'display_name' => $displayName ?: trim("{$firstName} {$lastName}"),
                'email' => $validated['email'],
                'password' => $validated['password'],
                'employee_number' => $validated['employee_number'] ?? null,
                'position_title' => $validated['position_title'] ?? null,
                'district' => $validated['district'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'locale' => $validated['locale'],
                'is_active' => $validated['is_active'],
                'is_seated_member' => $validated['is_seated_member'],
            ]);

            $user->forceFill(['password_changed_at' => now()])->save();

            if ($actor->can('assign', Role::class) && array_key_exists('role_ids', $validated)) {
                $this->syncRoles($user, $validated['role_ids'] ?? []);
            }

            return $user;
        });

        $this->syncAvatar($user, $request->file('avatar'), false, $avatars);

        return redirect()
            ->route('users.show', $user)
            ->with('success', 'users.created');
    }

    public function show(Request $request, User $user): Response
    {
        $this->authorize('view', $user);

        $user->load('roles:id,name,label');
        $actor = $this->requireUser($request);

        return Inertia::render('Users/Show', [
            'user' => $this->userDetail($user),
            'can' => [
                'update' => $actor->can('update', $user),
                'delete' => $actor->can('delete', $user),
            ],
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        $user->load('roles:id,name,label');
        $actor = $this->requireUser($request);

        return Inertia::render('Users/Edit', [
            'user' => $this->userDetail($user),
            'roles' => $this->roleOptions(),
            'can' => [
                'assign' => $actor->can('assign', Role::class),
                'delete' => $actor->can('delete', $user),
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UserAvatarService $avatars): RedirectResponse
    {
        $validated = $request->validated();
        $actor = $this->requireUser($request);

        DB::transaction(function () use ($validated, $actor, $user): void {
            $firstName = $validated['first_name'];
            $lastName = $validated['last_name'];
            $displayName = $validated['display_name'] ?? null;

            $payload = [
                'first_name' => $firstName,
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $lastName,
                'name_suffix' => $validated['name_suffix'] ?? null,
                'honorific' => $validated['honorific'] ?? null,
                'display_name' => $displayName ?: trim("{$firstName} {$lastName}"),
                'email' => $validated['email'],
                'employee_number' => $validated['employee_number'] ?? null,
                'position_title' => $validated['position_title'] ?? null,
                'district' => $validated['district'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'locale' => $validated['locale'],
                'is_active' => $validated['is_active'],
                'is_seated_member' => $validated['is_seated_member'],
            ];

            if (! empty($validated['password'])) {
                $payload['password'] = $validated['password'];
            }

            $user->update($payload);

            if (! empty($validated['password'])) {
                $user->forceFill(['password_changed_at' => now()])->save();
            }

            if ($actor->can('assign', Role::class) && array_key_exists('role_ids', $validated)) {
                $this->syncRoles($user, $validated['role_ids'] ?? []);
            }
        });

        $this->syncAvatar($user, $request->file('avatar'), $request->boolean('remove_avatar'), $avatars);

        return redirect()
            ->route('users.show', $user)
            ->with('success', 'users.updated');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'users.deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function userRow(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'display_name' => $user->display_name ?? $user->fullName(),
            'avatar_url' => $user->avatarUrl(),
            'email' => $user->email,
            'is_active' => $user->is_active,
            'is_seated_member' => $user->is_seated_member,
            'roles' => $user->roles->map(fn (Role $role): array => [
                'id' => $role->getKey(),
                'name' => $role->name,
                'label' => $role->label ?: $role->name,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userDetail(User $user): array
    {
        return [
            ...$this->userRow($user),
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'name_suffix' => $user->name_suffix,
            'honorific' => $user->honorific,
            'employee_number' => $user->employee_number,
            'position_title' => $user->position_title,
            'district' => $user->district,
            'phone' => $user->phone,
            'locale' => $user->locale ?? 'en',
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'role_ids' => $user->roles->pluck('id')->values()->all(),
        ];
    }

    /**
     * @return list<array{id: string, name: string, label: string}>
     */
    private function roleOptions(): array
    {
        return Role::query()
            ->orderBy('precedence')
            ->orderBy('label')
            ->get(['id', 'name', 'label'])
            ->map(fn (Role $role): array => [
                'id' => $role->getKey(),
                'name' => $role->name,
                'label' => $role->label ?: $role->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $roleIds
     */
    private function syncRoles(User $user, array $roleIds): void
    {
        $names = Role::query()
            ->whereIn('id', $roleIds)
            ->pluck('name')
            ->all();

        $user->syncRoles($names);
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
