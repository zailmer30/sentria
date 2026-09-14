<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'first_name', 'middle_name', 'last_name', 'name_suffix', 'honorific', 'display_name',
    'email', 'password', 'employee_number', 'position_title', 'district', 'phone',
    'avatar_path', 'locale', 'is_active', 'is_seated_member',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements Auditable, HasLocalePreference
{
    use AuditableTrait;
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUlids;
    use Notifiable;
    use SoftDeletes;

    /** @var array<int, string> */
    protected array $auditExclude = ['password', 'remember_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_seated_member' => 'boolean',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    public function preferredLocale(): string
    {
        return is_string($this->locale) && $this->locale !== '' ? $this->locale : 'en';
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([
            $this->honorific,
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->name_suffix,
        ])));
    }

    public function avatarUrl(): ?string
    {
        $path = $this->attributes['avatar_path'] ?? null;

        if ($path === null || $path === '') {
            return null;
        }

        // Root-relative so CSP img-src 'self' allows the image regardless of
        // whether the browser opened localhost, 127.0.0.1, or another host.
        $url = '/storage/'.ltrim(str_replace('\\', '/', (string) $path), '/');
        $version = $this->updated_at?->getTimestamp();

        return $version ? "{$url}?v={$version}" : $url;
    }

    /**
     * Laravel and several packages expect a `name` attribute on the user.
     */
    public function getNameAttribute(): string
    {
        return $this->display_name ?? $this->fullName();
    }

    /**
     * Overridden so notifications resolve to the ULID-keyed model.
     *
     * @return MorphMany<Notification, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')->latest();
    }

    /** @return HasMany<CommitteeMember, $this> */
    public function committeeMemberships(): HasMany
    {
        return $this->hasMany(CommitteeMember::class);
    }

    /** @return HasMany<Document, $this> */
    public function authoredDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'author_id');
    }

    /** @return HasMany<SessionAttendance, $this> */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(SessionAttendance::class);
    }

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /** @return HasMany<PrivateNote, $this> */
    public function privateNotes(): HasMany
    {
        return $this->hasMany(PrivateNote::class);
    }

    /** @return HasMany<DocumentAnnotation, $this> */
    public function documentAnnotations(): HasMany
    {
        return $this->hasMany(DocumentAnnotation::class);
    }

    /** @return HasMany<Bookmark, $this> */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    /** @return HasMany<AiConversation, $this> */
    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }
}
