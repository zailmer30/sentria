<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->getKey(),
                    'display_name' => $user->display_name,
                    'email' => $user->email,
                    'locale' => $user->locale,
                    'roles' => $user->getRoleNames()->values()->all(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                    'is_seated_member' => (bool) $user->is_seated_member,
                    'avatar_url' => $user->avatarUrl(),
                    'district' => $user->district,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'notifications' => [
                'unread_count' => fn () => $user !== null && $user->can('notifications.viewAny')
                    ? $user->unreadNotifications()->count()
                    : 0,
            ],
            'locale' => app()->getLocale(),
            'translations' => fn () => $this->translations(app()->getLocale()),
            'organization' => [
                'name' => config('sentria.organization.name'),
                'short_name' => config('sentria.organization.short_name'),
                'locality' => config('sentria.organization.locality'),
            ],
            'ai' => [
                // AI always inherits the authenticated user's permissions.
                // Surfaces must never elevate privileges for AI requests.
                'inherits_user_permissions' => true,
                'enabled' => (bool) config('sentria.ai.enabled'),
                'transcription_low_confidence' => (float) config('sentria.transcription.low_confidence', 0.4),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function translations(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (! is_file($path)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
