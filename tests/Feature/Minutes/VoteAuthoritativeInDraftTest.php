<?php

use App\Enums\UserRole;
use App\Enums\VoteChoice;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\Transcript;
use App\Models\User;
use App\Models\Vote;
use App\Services\AI\LegislativeMinutesGenerator;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);

    config(['sentria.ai.api_key' => null]);
});

function voteDraftActor(): User
{
    return User::factory()->create([
        'email' => 'secretariat-vote-draft@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);
}

it('reflects official vote counts exactly in AI draft minutes', function (): void {
    $secretariat = voteDraftActor();

    $session = LegislativeSession::factory()->adjourned()->create([
        'secretary_id' => $secretariat->getKey(),
    ]);

    $item = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'title' => 'Budget Ordinance No. 2026-01',
        'requires_vote' => true,
    ]);

    foreach ([VoteChoice::Yes, VoteChoice::Yes] as $choice) {
        Vote::factory()->create([
            'session_id' => $session->getKey(),
            'agenda_item_id' => $item->getKey(),
            'choice' => $choice->value,
            'voting_round' => 1,
        ]);
    }

    Vote::factory()->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $item->getKey(),
        'choice' => VoteChoice::No->value,
        'voting_round' => 1,
    ]);

    Vote::factory()->create([
        'session_id' => $session->getKey(),
        'agenda_item_id' => $item->getKey(),
        'choice' => VoteChoice::Abstain->value,
        'voting_round' => 1,
    ]);

    Transcript::factory()->create([
        'session_id' => $session->getKey(),
        'full_text' => 'The transcript incorrectly claims YES: 99 NO: 0 ABSTAIN: 0.',
    ]);

    $minutes = app(LegislativeMinutesGenerator::class)->draftFromSession($secretariat, $session);

    expect($minutes->content)->toContain('YES: 2 NO: 1 ABSTAIN: 1 INHIBIT: 0')
        ->and($minutes->content)->not->toContain('## Transcript Excerpt')
        ->and($minutes->content)->not->toContain('YES: 99')
        ->and($minutes->ai_metadata['vote_tallies'][0]['yes'])->toBe(2)
        ->and($minutes->ai_metadata['vote_tallies'][0]['no'])->toBe(1)
        ->and($minutes->ai_metadata['vote_tallies'][0]['abstain'])->toBe(1)
        ->and($minutes->ai_metadata['vote_tallies'][0]['inhibit'])->toBe(0);

    Transcript::query()->where('session_id', $session->getKey())->update([
        'full_text' => 'Updated transcript still claims YES: 50 NO: 50 ABSTAIN: 0.',
    ]);

    $regenerated = app(LegislativeMinutesGenerator::class)->draftFromSession($secretariat, $session->fresh());

    preg_match('/## Official Vote Results \(authoritative\)(.*?)(?:\n## |\z)/s', (string) $regenerated->content, $voteSection);

    expect($voteSection[1] ?? '')->toContain('YES: 2 NO: 1 ABSTAIN: 1 INHIBIT: 0')
        ->and($voteSection[1] ?? '')->not->toContain('YES: 50')
        ->and($regenerated->ai_metadata['vote_tallies'][0]['yes'])->toBe(2);
});
