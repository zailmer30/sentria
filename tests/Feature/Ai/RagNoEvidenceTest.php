<?php

use App\Contracts\AI\RAGService;
use App\Enums\UserRole;
use App\Models\AiCitation;
use App\Models\User;
use App\Support\AI\InsufficientEvidence;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);

    config(['sentria.ai.api_key' => null]);
});

function noEvidenceActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-ai-none@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('returns the insufficient-evidence phrase with zero citations for nonsense questions', function (): void {
    $member = noEvidenceActor(UserRole::BoardMember);

    $result = app(RAGService::class)->ask(
        $member,
        'xyzzy-plugh-quux-nonsense-token-not-in-any-record',
    );

    expect($result->content)->toBe(InsufficientEvidence::PHRASE)
        ->and($result->insufficientEvidence)->toBeTrue()
        ->and($result->citations)->toBeEmpty()
        ->and(AiCitation::query()->count())->toBe(0);

    $this->actingAs($member)
        ->postJson(route('ai.ask'), [
            'question' => 'xyzzy-plugh-quux-nonsense-token-not-in-any-record',
        ])
        ->assertOk()
        ->assertJsonPath('message.content', InsufficientEvidence::PHRASE)
        ->assertJsonPath('message.insufficient_evidence', true)
        ->assertJsonPath('message.citations', []);
});
