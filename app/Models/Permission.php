<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = ['name', 'guard_name', 'module', 'description'];
}
