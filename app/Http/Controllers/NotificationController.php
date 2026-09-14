<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $user = $this->requireUser($request);
        $this->authorize('viewAny', Notification::class);

        $unreadOnly = $request->boolean('unread');

        $query = $user->notifications();

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $notifications = $query
            ->paginate($request->integer('per_page', 20))
            ->withQueryString()
            ->through(fn (Notification $notification): array => $this->transform($notification));

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'notifications' => $notifications,
                'unread_count' => $user->unreadNotifications()->count(),
            ]);
        }

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filters' => [
                'unread' => $unreadOnly,
            ],
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $this->authorize('viewAny', Notification::class);

        $items = $user->notifications()
            ->limit(10)
            ->get()
            ->map(fn (Notification $notification): array => $this->transform($notification))
            ->values()
            ->all();

        return response()->json([
            'notifications' => $items,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $notification);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'notification' => $this->transform($notification->fresh() ?? $notification),
                'unread_count' => $this->requireUser($request)->unreadNotifications()->count(),
            ]);
        }

        return back();
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $user = $this->requireUser($request);
        $this->authorize('viewAny', Notification::class);

        $user->unreadNotifications()->update(['read_at' => now()]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['unread_count' => 0]);
        }

        return back()->with('success', 'notifications.all_marked_read');
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Notification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        return [
            'id' => $notification->getKey(),
            'type' => $notification->type,
            'category' => $notification->category ?? ($data['category'] ?? null),
            'priority' => $notification->priority ?? ($data['priority'] ?? 'normal'),
            'action_url' => $notification->action_url ?? ($data['action_url'] ?? null),
            'title_key' => $data['title_key'] ?? null,
            'title_params' => $data['title_params'] ?? [],
            'body_key' => $data['body_key'] ?? null,
            'body_params' => $data['body_params'] ?? [],
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
