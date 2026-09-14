<?php

use App\Models\Publication;
use App\States\Publication\Published;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);

    RateLimiter::clear('portal-search');
});

it('throttles excessive public portal search requests', function (): void {
    Publication::factory()->published()->create([
        'status' => Published::$name,
    ]);

    for ($i = 0; $i < 60; $i++) {
        $this->get(route('portal.search', ['keyword' => "term-{$i}"]))->assertOk();
    }

    $this->get(route('portal.search', ['keyword' => 'term-over-limit']))
        ->assertStatus(429);
});
