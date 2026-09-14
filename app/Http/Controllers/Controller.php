<?php

namespace App\Http\Controllers;

use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\Motion;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    protected function requireUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    protected function agendaItemForSession(LegislativeSession $session, string $agendaItemId): AgendaItem
    {
        $item = AgendaItem::query()
            ->where('session_id', $session->getKey())
            ->whereKey($agendaItemId)
            ->first();

        abort_unless($item instanceof AgendaItem, 404);

        return $item;
    }

    protected function motionForSession(LegislativeSession $session, string $motionId): Motion
    {
        $motion = Motion::query()
            ->where('session_id', $session->getKey())
            ->whereKey($motionId)
            ->first();

        abort_unless($motion instanceof Motion, 404);

        return $motion;
    }
}
