<?php

namespace App\Policies;

use App\Models\CommitteeReferral;
use App\Models\User;

class CommitteeReferralPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('referrals.viewAny');
    }

    public function view(User $user, CommitteeReferral $referral): bool
    {
        return $user->can('referrals.view');
    }

    public function create(User $user): bool
    {
        return $user->can('referrals.manage');
    }

    public function update(User $user, CommitteeReferral $referral): bool
    {
        return $user->can('referrals.manage');
    }
}
