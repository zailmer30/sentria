<?php

namespace App\Models;

use App\Enums\SessionGuestStatus;
use Database\Factories\SessionGuestFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A person invited to one sitting to speak or give insight. Not a user,
 * and not part of the seated roll.
 *
 * @property string $session_id
 * @property string $name
 * @property string|null $organization
 * @property string|null $speaking_topic
 * @property string $status
 * @property string|null $recorded_by
 */
#[Table('session_guests')]
#[UseFactory(SessionGuestFactory::class)]
class SessionGuest extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<SessionGuestFactory> */
    use HasFactory;

    use HasUlids;

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<LegislativeSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class, 'session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * One line in the generated minutes. Organization sits in parentheses so
     * a topic alone cannot be read as an organization.
     */
    public function minutesLine(): string
    {
        $dash = "\u{2014}";
        $status = SessionGuestStatus::from($this->status)->label();
        $line = '- '.$this->name.' '.$dash.' '.$status;
        $organization = trim((string) $this->organization);

        if ($organization !== '') {
            $line .= ' ('.$organization.')';
        }

        $topic = trim((string) $this->speaking_topic);

        if ($topic !== '') {
            $line .= ' '.$dash.' '.$topic;
        }

        return $line;
    }
}
