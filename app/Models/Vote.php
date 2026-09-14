<?php

namespace App\Models;

use App\Enums\VoteChoice;
use Database\Factories\VoteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * A cast ballot. Rows are immutable: a change while the round is open is a
 * new row, never an edit. The counted choice is the member's latest ballot
 * in that round. The database enforces append-only with a trigger; this
 * model refuses the mutation earlier.
 */
#[UseFactory(VoteFactory::class)]
class Vote extends Model
{
    /** @use HasFactory<VoteFactory> */
    use HasFactory;

    use HasUlids;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cast_at' => 'datetime',
            'voting_round' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('Votes are append-only. Record a new ballot instead of amending one.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('Votes are append-only and cannot be deleted.');
        });
    }

    /** @return BelongsTo<LegislativeSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class, 'session_id');
    }

    /** @return BelongsTo<AgendaItem, $this> */
    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class);
    }

    /** @return BelongsTo<Motion, $this> */
    public function motion(): BelongsTo
    {
        return $this->belongsTo(Motion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * One ballot per member: the latest row by cast time, then id.
     *
     * @param  Collection<int, Vote>  $votes
     * @return Collection<int, Vote>
     */
    public static function latestPerMember(Collection $votes): Collection
    {
        return $votes
            ->groupBy(fn (Vote $vote): string => (string) $vote->user_id)
            ->map(function (Collection $ballots): Vote {
                /** @var Vote $latest */
                $latest = $ballots
                    ->sort(function (Vote $left, Vote $right): int {
                        $time = ($right->cast_at <=> $left->cast_at);

                        if ($time !== 0) {
                            return $time;
                        }

                        return $right->getKey() <=> $left->getKey();
                    })
                    ->first();

                return $latest;
            })
            ->values();
    }

    /**
     * @param  Collection<int, Vote>  $votes
     * @return array{yes: int, no: int, abstain: int, inhibit: int, total: int}
     */
    public static function tallyLatest(Collection $votes): array
    {
        $current = self::latestPerMember($votes);
        $yes = $current->where('choice', VoteChoice::Yes->value)->count();
        $no = $current->where('choice', VoteChoice::No->value)->count();
        $abstain = $current->where('choice', VoteChoice::Abstain->value)->count();
        $inhibit = $current->where('choice', VoteChoice::Inhibit->value)->count();

        return [
            'yes' => $yes,
            'no' => $no,
            'abstain' => $abstain,
            'inhibit' => $inhibit,
            'total' => $yes + $no + $abstain + $inhibit,
        ];
    }
}
