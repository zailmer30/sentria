<?php

namespace App\Http\Controllers;

use App\Enums\DashboardRange;
use App\Models\User;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardMetricsService $metrics) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->requireUser($request);
        $primaryRole = $user->getRoleNames()->first();
        $range = DashboardRange::fromRequest($request->query('range') !== null
            ? (string) $request->query('range')
            : null);

        return Inertia::render('Dashboard', [
            'role' => $primaryRole,
            'headline' => match ($primaryRole) {
                'system-administrator' => 'nav.dashboard_admin',
                'secretariat' => 'nav.dashboard_secretariat',
                'presiding-officer' => 'nav.dashboard_presiding',
                'board-member' => 'nav.dashboard_member',
                'committee-chair' => 'nav.dashboard_chair',
                'committee-member' => 'nav.dashboard_committee',
                'legal-technical-reviewer' => 'nav.dashboard_legal',
                'public-user' => 'nav.dashboard_public',
                default => 'nav.dashboard',
            },
            'givenName' => $user->first_name ?: $user->display_name,
            'range' => $range->value,
            'rangeOptions' => DashboardRange::options(),
            // A closure, so changing the range can re-request just the figures
            // rather than re-running every prop on the page.
            'metrics' => fn (): array => $this->metrics->for($user, $range),
            'actions' => $this->actions($user),
        ]);
    }

    /**
     * What this user can start from here. Permission-gated on the server so the
     * list is never a catalogue of things someone else is allowed to do.
     *
     * @return list<array{key: string, href: string, label: string, description: string}>
     */
    private function actions(User $user): array
    {
        $candidates = [
            ['key' => 'file-document', 'href' => '/documents?submit=1', 'permission' => 'documents.create'],
            ['key' => 'session-floor', 'href' => '/sessions', 'permission' => 'sessions.viewAny'],
            ['key' => 'review-legislation', 'href' => '/ordinances', 'permission' => 'legislation.viewAny'],
            ['key' => 'publish', 'href' => '/publications', 'permission' => 'publications.review'],
            ['key' => 'ask-ai', 'href' => '/ai', 'permission' => 'ai.use'],
            ['key' => 'audit', 'href' => '/audit', 'permission' => 'audit.viewAny'],
        ];

        $allowed = [];

        foreach ($candidates as $candidate) {
            if (! $user->can($candidate['permission'])) {
                continue;
            }

            $allowed[] = [
                'key' => $candidate['key'],
                'href' => $candidate['href'],
                'label' => "dashboard.action.{$candidate['key']}",
                'description' => "dashboard.action.{$candidate['key']}_hint",
            ];
        }

        return array_slice($allowed, 0, 4);
    }
}
