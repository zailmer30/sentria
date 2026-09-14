<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use OwenIt\Auditing\Models\Audit as BaseAudit;

/**
 * owen-it/laravel-auditing model diffs, keyed by ULID. This complements —
 * never replaces — the hash-chained `audit_logs` trail.
 */
class Audit extends BaseAudit
{
    use HasUlids;
}
