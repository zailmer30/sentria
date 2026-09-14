<?php

use App\Enums\Confidentiality;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function indexActor(): User
{
    return User::factory()->create([
        'email' => 'secretariat-index@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);
}

it('scopes the register to awaiting action when that card is selected', function (): void {
    $secretariat = indexActor();
    $awaiting = Document::factory()->create([
        'author_id' => $secretariat->getKey(),
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);
    Document::factory()->registered()->create([
        'author_id' => $secretariat->getKey(),
        'submitted_at' => now(),
    ]);

    $this->actingAs($secretariat)
        ->get(route('documents.index', ['scope' => 'awaiting']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/Index')
            ->where('filters.scope', 'awaiting')
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $awaiting->getKey())
            ->where('summary.matching', 2)
            ->where('summary.awaiting_action', 1));
});

it('scopes the register to restricted filings when that card is selected', function (): void {
    $secretariat = indexActor();
    $restricted = Document::factory()->create([
        'author_id' => $secretariat->getKey(),
        'confidentiality' => Confidentiality::Restricted->value,
        'submitted_at' => now(),
    ]);
    Document::factory()->create([
        'author_id' => $secretariat->getKey(),
        'confidentiality' => Confidentiality::Public->value,
        'submitted_at' => now(),
    ]);

    $this->actingAs($secretariat)
        ->get(route('documents.index', ['scope' => 'restricted']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/Index')
            ->where('filters.scope', 'restricted')
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $restricted->getKey())
            ->where('summary.restricted', 1));
});

it('scopes the register to filings from the last 30 days', function (): void {
    $secretariat = indexActor();
    $recent = Document::factory()->create([
        'author_id' => $secretariat->getKey(),
        'submitted_at' => now()->subDays(2),
    ]);
    Document::factory()->create([
        'author_id' => $secretariat->getKey(),
        'submitted_at' => now()->subDays(40),
    ]);

    $this->actingAs($secretariat)
        ->get(route('documents.index', ['scope' => 'recent']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Documents/Index')
            ->where('filters.scope', 'recent')
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $recent->getKey())
            ->where('summary.recent', 1));
});
