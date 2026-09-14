<?php

namespace App\Http\Controllers;

use App\Http\Requests\Committees\StoreCommitteeMemberRequest;
use App\Models\Committee;
use App\Models\User;
use App\Notifications\CommitteeMemberAppointed;
use App\Services\Notifications\InAppNotifier;
use Illuminate\Http\RedirectResponse;

class CommitteeMemberController extends Controller
{
    public function __construct(private readonly InAppNotifier $notifier) {}

    public function store(StoreCommitteeMemberRequest $request, Committee $committee): RedirectResponse
    {
        $validated = $request->validated();

        $committee->memberships()->create([
            'user_id' => $validated['user_id'],
            'position' => $validated['position'],
            'appointed_on' => $validated['appointed_on'] ?: now()->toDateString(),
            'is_active' => true,
        ]);

        $appointed = User::query()->find($validated['user_id']);

        if ($appointed !== null) {
            $this->notifier->send(
                $appointed,
                new CommitteeMemberAppointed($committee, $validated['position']),
                $request->user(),
            );
        }

        return redirect()
            ->route('committees.show', $committee)
            ->with('success', 'committees.member_appointed');
    }
}
