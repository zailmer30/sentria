<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = ['name', 'guard_name', 'label', 'description', 'is_system', 'precedence'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'precedence' => 'integer',
        ];
    }
}
