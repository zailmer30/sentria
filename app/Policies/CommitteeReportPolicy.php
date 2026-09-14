<?php

namespace App\Policies;

use App\Models\Committee;
use App\Models\CommitteeReport;
use App\Models\User;

class CommitteeReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reports.viewAny');
    }

    public function view(User $user, CommitteeReport $report): bool
    {
        return $user->can('reports.view');
    }

    public function create(User $user): bool
    {
        return $user->can('reports.create');
    }

    public function submitForReview(User $user, CommitteeReport $report): bool
    {
        return $user->can('reports.submitForReview') && $report->status === 'draft';
    }

    public function returnToDraft(User $user, CommitteeReport $report): bool
    {
        return $user->can('reports.submit')
            && $report->status === 'chair-review'
            && $this->mayActAsChair($user, $report);
    }

    public function submit(User $user, CommitteeReport $report): bool
    {
        return $user->can('reports.submit')
            && $report->status === 'chair-review'
            && $this->mayActAsChair($user, $report);
    }

    public function adopt(User $user, CommitteeReport $report): bool
    {
        return $user->can('reports.adopt') && $report->status === 'submitted';
    }

    private function mayActAsChair(User $user, CommitteeReport $report): bool
    {
        if ($user->hasRole('system-administrator')) {
            return true;
        }

        $committee = $report->committee;

        if (! $committee instanceof Committee) {
            $report->loadMissing('committee');
            $committee = $report->committee;
        }

        if (! $committee instanceof Committee) {
            return $user->can('reports.submit');
        }

        return $committee->memberships()
            ->where('user_id', $user->getKey())
            ->where('position', 'chair')
            ->where('is_active', true)
            ->exists()
            || $user->hasRole('committee-chair');
    }
}
