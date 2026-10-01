<?php

namespace App\Policies;

use App\Models\LegislativeSession;
use App\Models\User;
use App\States\Session\Archived;
use App\States\Session\Finalized;

class LegislativeSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sessions.viewAny');
    }

    public function view(User $user, LegislativeSession $session): bool
    {
        return $user->can('sessions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sessions.create');
    }

    public function update(User $user, LegislativeSession $session): bool
    {
        return $user->can('sessions.update');
    }

    public function schedule(User $user, LegislativeSession $session): bool
    {
        return $user->can('sessions.schedule');
    }

    public function prepareAgenda(User $user, LegislativeSession $session): bool
    {
        return $user->can('agenda.manage');
    }

    public function start(User $user, LegislativeSession $session): bool
    {
        return $user->can('sessions.start');
    }

    public function suspend(User $user, LegislativeSession $session): bool
    {
        return $user->can('sessions.suspend');
    }

    public function resume(User $user, LegislativeSession $session): bool
    {
        return $user->can('sessions.suspend');
    }

    public function adjourn(User $user, LegislativeSession $session): bool
    {
        return $user->can('sessions.adjourn');
    }

    public function recordAttendance(User $user, LegislativeSession $session): bool
    {
        return $user->can('attendance.record');
    }

    public function openVoting(User $user, LegislativeSession $session): bool
    {
        return $user->can('voting.open') && $user->can('view', $session);
    }

    public function closeVoting(User $user, LegislativeSession $session): bool
    {
        return $user->can('voting.close') && $user->can('view', $session);
    }

    public function castVote(User $user, LegislativeSession $session): bool
    {
        return $user->can('voting.cast') && $user->can('view', $session);
    }

    /**
     * Project or clear what the hall board shows. The clerk drives the sitting
     * from the console; the chair may still pin a measure from the floor.
     */
    public function controlHallDisplay(User $user, LegislativeSession $session): bool
    {
        return $user->can('view', $session)
            && ($user->can('agenda.manage') || $user->can('voting.open'));
    }

    public function viewTranscript(User $user, LegislativeSession $session): bool
    {
        return $user->can('transcripts.view') && $user->can('view', $session);
    }

    public function manageTranscript(User $user, LegislativeSession $session): bool
    {
        return $user->can('transcripts.manage') && $user->can('view', $session);
    }

    public function transcribeSession(User $user, LegislativeSession $session): bool
    {
        return ($user->can('transcripts.manage') || $user->can('ai.transcribe'))
            && $user->can('view', $session);
    }

    public function manageRecording(User $user, LegislativeSession $session): bool
    {
        return $user->can('view', $session)
            && ($user->can('transcripts.manage') || $user->can('settings.update'));
    }

    /**
     * Sitting switch between vote-after-each-item and discuss-the-heading-then-vote.
     */
    public function updateVotingMode(User $user, LegislativeSession $session): bool
    {
        return $user->can('view', $session)
            && ($user->can('agenda.manage') || $user->can('voting.open'));
    }

    /**
     * Live minutes the clerk types during the sitting. Stored on the session
     * and folded into the system-generated draft after adjournment.
     */
    public function recordMinutes(User $user, LegislativeSession $session): bool
    {
        if (! $user->can('minutes.edit') || ! $user->can('view', $session)) {
            return false;
        }

        return ! ($session->status instanceof Finalized)
            && ! ($session->status instanceof Archived);
    }

    /**
     * Ephemeral floor chat. Live only while the sitting is in session or recess.
     */
    public function useChat(User $user, LegislativeSession $session): bool
    {
        return $user->can('session-chat.use')
            && $this->view($user, $session)
            && $user->is_active
            && $session->chatIsLive();
    }
}
