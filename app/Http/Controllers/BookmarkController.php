<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sessions\StoreBookmarkRequest;
use App\Models\Bookmark;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function store(StoreBookmarkRequest $request): RedirectResponse
    {
        Bookmark::query()->updateOrCreate(
            [
                'user_id' => $this->requireUser($request)->getKey(),
                'bookmarkable_type' => $request->validated('bookmarkable_type'),
                'bookmarkable_id' => $request->validated('bookmarkable_id'),
            ],
            [
                'label' => $request->validated('label'),
            ],
        );

        return back()->with('success', 'sessions.bookmark_created');
    }

    public function destroy(Request $request, Bookmark $bookmark): RedirectResponse
    {
        $this->authorize('delete', $bookmark);

        abort_unless($bookmark->user_id === $this->requireUser($request)->getKey(), 403);

        $bookmark->delete();

        return back()->with('success', 'sessions.bookmark_deleted');
    }
}
