<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Committees\StoreCommitteeRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\CommitteeReferral;
use App\Models\CommitteeReport;
use App\Models\Document;
use App\Models\User;
use App\Services\Documents\DocumentAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CommitteeController extends Controller
{
    public function __construct(private readonly DocumentAccessService $access) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Committee::class);

        $committees = Committee::query()
            ->withCount(['memberships', 'referrals', 'reports'])
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Committees/Index', [
            'committees' => $committees->through(fn (Committee $c): array => [
                ...DocumentResource::committee($c),
                'members_count' => $c->memberships_count,
                'referrals_count' => $c->referrals_count,
                'reports_count' => $c->reports_count,
            ]),
            'can' => [
                'create' => $request->user()?->can('create', Committee::class) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Committee::class);

        return Inertia::render('Committees/Create');
    }

    public function store(StoreCommitteeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $committee = Committee::query()->create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'code' => $validated['code'] ?: null,
            'type' => $validated['type'],
            'mandate' => $validated['mandate'] ?: null,
            'established_on' => $validated['established_on'] ?: null,
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('committees.show', $committee)
            ->with('success', 'committees.created');
    }

    public function show(Request $request, Committee $committee): Response
    {
        $this->authorize('view', $committee);

        $user = $this->requireUser($request);

        $committee->load([
            'memberships.user',
            'referrals.document.currentVersion',
            'referrals.document.author',
            'referrals.document.committee',
            'referrals.referrer',
            'reports.submitter',
        ]);

        $canManageMembers = $user->can('manageMembers', $committee);
        $canCreateReferral = $user->can('create', CommitteeReferral::class);
        $canCreateReport = $user->can('create', CommitteeReport::class);

        return Inertia::render('Committees/Show', [
            'committee' => [
                ...DocumentResource::committee($committee),
                'members' => $committee->memberships->map(fn (CommitteeMember $member): array => [
                    'id' => $member->getKey(),
                    'user' => $member->user?->display_name,
                    'position' => $member->position,
                    'is_active' => $member->is_active,
                    'appointed_on' => $member->appointed_on?->toDateString(),
                ])->values()->all(),
                'referrals' => $committee->referrals->map(
                    fn (CommitteeReferral $referral): array => DocumentResource::referral($referral)
                )->values()->all(),
                'reports' => $committee->reports->map(
                    fn (CommitteeReport $report): array => DocumentResource::report($report, $user)
                )->values()->all(),
            ],
            'can' => [
                'manageMembers' => $canManageMembers,
                'createReferral' => $canCreateReferral,
                'updateReferral' => $canCreateReferral,
                'createReport' => $canCreateReport,
                'submitReportForReview' => $user->can('reports.submitForReview'),
                'submitReport' => $user->can('reports.submit'),
                'adoptReport' => $user->can('reports.adopt'),
            ],
            'appointableUsers' => $canManageMembers ? $this->appointableUsers($committee) : [],
            'referableDocuments' => $canCreateReferral ? $this->referableDocuments($committee, $user) : [],
        ]);
    }

    /**
     * @return list<array{id: string, display_name: string}>
     */
    private function appointableUsers(Committee $committee): array
    {
        $memberIds = $committee->memberships->pluck('user_id');
        $seatedRoles = [
            UserRole::PresidingOfficer->value,
            UserRole::BoardMember->value,
            UserRole::CommitteeChair->value,
            UserRole::CommitteeMember->value,
        ];

        return User::query()
            ->where('is_active', true)
            ->whereNotIn('id', $memberIds)
            ->where(function (Builder $query) use ($seatedRoles): void {
                $query->where('is_seated_member', true)
                    ->orWhereHas('roles', function (Builder $roles) use ($seatedRoles): void {
                        $roles->whereIn('name', $seatedRoles);
                    });
            })
            ->orderBy('display_name')
            ->get(['id', 'display_name'])
            ->map(fn (User $candidate): array => [
                'id' => $candidate->getKey(),
                'display_name' => $candidate->display_name ?? $candidate->fullName(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, title: string, reference_number: string|null}>
     */
    private function referableDocuments(Committee $committee, User $user): array
    {
        $query = Document::query()
            ->whereNull('archived_at')
            ->whereDoesntHave('referrals', function (Builder $referrals) use ($committee): void {
                $referrals->where('committee_id', $committee->getKey())
                    ->whereNull('completed_at');
            })
            ->orderByDesc('submitted_at')
            ->limit(100);

        $this->access->scopeVisibleTo($query, $user);

        return $query->get(['id', 'title', 'reference_number'])
            ->map(fn (Document $document): array => [
                'id' => $document->getKey(),
                'title' => $document->title,
                'reference_number' => $document->reference_number,
            ])
            ->values()
            ->all();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'committee';
        $slug = $base;
        $suffix = 2;

        while (Committee::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
