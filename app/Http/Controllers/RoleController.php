<?php

namespace App\Http\Controllers;

use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('precedence')
            ->orderBy('label')
            ->get();

        $actor = $this->requireUser($request);

        return Inertia::render('Roles/Index', [
            'roles' => $roles->map(fn (Role $role): array => $this->roleRow($role))->values()->all(),
            'can' => [
                'create' => $actor->can('create', Role::class),
                'manage' => $actor->can('roles.manage'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('Roles/Create', [
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $role = DB::transaction(function () use ($validated): Role {
            $role = Role::query()->create([
                'name' => $validated['name'],
                'guard_name' => 'web',
                'label' => $validated['label'],
                'description' => $validated['description'] ?? null,
                'is_system' => false,
                'precedence' => $validated['precedence'],
            ]);

            $role->syncPermissions($validated['permissions'] ?? []);

            return $role;
        });

        return redirect()
            ->route('roles.edit', $role)
            ->with('success', 'roles.created');
    }

    public function edit(Request $request, Role $role): Response
    {
        $this->authorize('update', $role);

        $role->load('permissions:id,name');

        return Inertia::render('Roles/Edit', [
            'role' => [
                ...$this->roleRow($role),
                'description' => $role->description,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ],
            'permissionGroups' => $this->permissionGroups(),
            'can' => [
                'delete' => $request->user()?->can('delete', $role) ?? false,
            ],
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $role): void {
            $role->update([
                'label' => $validated['label'],
                'description' => $validated['description'] ?? null,
                'precedence' => $validated['precedence'],
            ]);

            $role->syncPermissions($validated['permissions'] ?? []);
        });

        return redirect()
            ->route('roles.edit', $role)
            ->with('success', 'roles.updated');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        if ($role->users()->exists()) {
            return redirect()
                ->route('roles.edit', $role)
                ->with('error', 'roles.delete_has_users');
        }

        $role->delete();

        return redirect()
            ->route('roles.index')
            ->with('success', 'roles.deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function roleRow(Role $role): array
    {
        return [
            'id' => $role->getKey(),
            'name' => $role->name,
            'label' => $role->label ?: $role->name,
            'is_system' => (bool) $role->is_system,
            'precedence' => (int) $role->precedence,
            'permissions_count' => (int) ($role->permissions_count ?? $role->permissions()->count()),
            'users_count' => (int) ($role->users_count ?? $role->users()->count()),
        ];
    }

    /**
     * @return list<array{module: string, permissions: list<array{name: string, description: string|null}>}>
     */
    private function permissionGroups(): array
    {
        return Permission::query()
            ->orderBy('module')
            ->orderBy('name')
            ->get(['name', 'module', 'description'])
            ->groupBy(fn (Permission $permission): string => $permission->module ?: 'general')
            ->map(fn ($permissions, string $module): array => [
                'module' => $module,
                'permissions' => $permissions->map(fn (Permission $permission): array => [
                    'name' => $permission->name,
                    'description' => $permission->description,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
