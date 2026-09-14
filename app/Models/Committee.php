<?php

namespace App\Models;

use Database\Factories\CommitteeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property Carbon|null $established_on
 */
#[UseFactory(CommitteeFactory::class)]
class Committee extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<CommitteeFactory> */
    use HasFactory;

    use HasUlids;
    use SoftDeletes;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'established_on' => 'date',
            'dissolved_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<CommitteeMember, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(CommitteeMember::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'committee_members')
            ->withPivot(['position', 'appointed_on', 'ended_on', 'is_active'])
            ->withTimestamps();
    }

    /** @return HasMany<CommitteeReferral, $this> */
    public function referrals(): HasMany
    {
        return $this->hasMany(CommitteeReferral::class);
    }

    /** @return HasMany<CommitteeReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(CommitteeReport::class);
    }

    public function chair(): ?User
    {
        return $this->members()->wherePivot('position', 'chair')->wherePivot('is_active', true)->first();
    }

    /**
     * Active committee members eligible for in-app notifications.
     *
     * @return BelongsToMany<User, $this>
     */
    public function activeMembers(): BelongsToMany
    {
        return $this->members()
            ->wherePivot('is_active', true)
            ->where('users.is_active', true);
    }
}
