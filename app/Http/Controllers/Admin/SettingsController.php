<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->requireUser($request);
        abort_unless($user->can('settings.viewAny') || $user->can('settings.chamber') || $user->can('transcripts.manage'), 403);

        $areas = [
            [
                'key' => 'branding',
                'label' => 'settings.areas.branding',
                'description' => 'settings.areas.branding_desc',
                'href' => '/settings/branding',
                'available' => $user->can('settings.viewAny'),
            ],
            [
                'key' => 'users',
                'label' => 'settings.areas.users',
                'description' => 'settings.areas.users_desc',
                'href' => '/users',
                'available' => $user->can('users.viewAny'),
            ],
            [
                'key' => 'roles',
                'label' => 'settings.areas.roles',
                'description' => 'settings.areas.roles_desc',
                'href' => '/roles',
                'available' => $user->can('roles.viewAny'),
            ],
            [
                'key' => 'committees',
                'label' => 'settings.areas.committees',
                'description' => 'settings.areas.committees_desc',
                'href' => '/committees',
                'available' => $user->can('committees.viewAny'),
            ],
            [
                'key' => 'ai',
                'label' => 'settings.areas.ai',
                'description' => 'settings.areas.ai_desc',
                'href' => '/ai',
                'available' => $user->can('ai.use'),
            ],
            [
                'key' => 'storage',
                'label' => 'settings.areas.storage',
                'description' => 'settings.areas.storage_desc',
                'href' => '/admin/monitoring',
                'available' => $user->can('settings.viewAny'),
            ],
            [
                'key' => 'backup',
                'label' => 'settings.areas.backup',
                'description' => 'settings.areas.backup_desc',
                'href' => '/admin/monitoring#backup',
                'available' => $user->can('settings.viewAny'),
            ],
            [
                'key' => 'portal',
                'label' => 'settings.areas.portal',
                'description' => 'settings.areas.portal_desc',
                'href' => '/publications',
                'available' => $user->can('publications.viewAny'),
            ],
            [
                'key' => 'chamber',
                'label' => 'settings.areas.chamber',
                'description' => 'settings.areas.chamber_desc',
                'href' => '/settings/chamber-channels',
                'available' => $user->can('settings.viewAny') || $user->can('settings.chamber') || $user->can('transcripts.manage'),
            ],
            [
                'key' => 'monitoring',
                'label' => 'settings.areas.monitoring',
                'description' => 'settings.areas.monitoring_desc',
                'href' => '/admin/monitoring',
                'available' => $user->can('settings.viewAny'),
            ],
        ];

        return Inertia::render('Admin/Settings/Index', [
            'areas' => $areas,
        ]);
    }
}
