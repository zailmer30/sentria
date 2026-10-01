<?php

namespace App\Services\Sessions;

use App\Enums\SessionGuestStatus;
use App\Models\LegislativeSession;
use App\Models\SessionGuest;
use App\Models\User;
use Illuminate\Support\Collection;

class SessionGuestService
{
    /**
     * @return Collection<int, SessionGuest>
     */
    public function forSession(LegislativeSession $session): Collection
    {
        return $session->guests()->get();
    }

    public function add(
        LegislativeSession $session,
        User $recorder,
        mixed $name,
        mixed $organization = null,
        mixed $speakingTopic = null,
    ): SessionGuest {
        $normalizedName = self::blankToNull($name);
        abort_if($normalizedName === null, 422, 'A guest name is required.');

        return SessionGuest::query()->create([
            'session_id' => $session->getKey(),
            'name' => $normalizedName,
            'organization' => self::blankToNull($organization),
            'speaking_topic' => self::blankToNull($speakingTopic),
            'status' => SessionGuestStatus::Invited->value,
            'recorded_by' => $recorder->getKey(),
        ]);
    }

    public function update(
        SessionGuest $guest,
        User $recorder,
        mixed $name,
        mixed $organization,
        mixed $speakingTopic,
        mixed $status,
    ): SessionGuest {
        $normalizedName = self::blankToNull($name);
        abort_if($normalizedName === null, 422, 'A guest name is required.');
        abort_unless(is_string($status), 422, 'A guest status is required.');

        $guest->fill([
            'name' => $normalizedName,
            'organization' => self::blankToNull($organization),
            'speaking_topic' => self::blankToNull($speakingTopic),
            'status' => SessionGuestStatus::from($status)->value,
            'recorded_by' => $recorder->getKey(),
        ]);
        $guest->save();

        return $guest;
    }

    public function remove(SessionGuest $guest): void
    {
        $guest->delete();
    }

    public static function blankToNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return $trimmed === '' ? null : $trimmed;
    }
}
