<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sessions\StorePrivateNoteRequest;
use App\Http\Requests\Sessions\UpdatePrivateNoteRequest;
use App\Models\PrivateNote;
use App\Policies\PrivateNotePolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PrivateNoteController extends Controller
{
    public function store(StorePrivateNoteRequest $request): RedirectResponse
    {
        $user = $this->requireUser($request);

        /** @var PrivateNotePolicy $policy */
        $policy = app(PrivateNotePolicy::class);

        abort_unless(
            $policy->createForNotable(
                $user,
                $request->validated('notable_type'),
                $request->validated('notable_id'),
            ),
            403,
        );

        PrivateNote::query()->create([
            'user_id' => $user->getKey(),
            'notable_type' => $request->validated('notable_type'),
            'notable_id' => $request->validated('notable_id'),
            'body' => $request->validated('body'),
            'page_number' => $request->validated('page_number'),
        ]);

        return back()->with('success', 'sessions.note_created');
    }

    public function update(UpdatePrivateNoteRequest $request, PrivateNote $privateNote): RedirectResponse
    {
        abort_unless($privateNote->user_id === $this->requireUser($request)->getKey(), 403);

        $privateNote->update($request->validated());

        return back()->with('success', 'sessions.note_updated');
    }

    public function destroy(Request $request, PrivateNote $privateNote): RedirectResponse
    {
        $this->authorize('delete', $privateNote);

        abort_unless($privateNote->user_id === $this->requireUser($request)->getKey(), 403);

        $privateNote->delete();

        return back()->with('success', 'sessions.note_deleted');
    }
}
