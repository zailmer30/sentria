<?php

use App\Enums\SessionGuestStatus;
use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\SessionGuest;
use App\Models\User;
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

function invitedGuestMinutesActor(): User
{
    return User::factory()->create([
        'email' => 'secretariat-guest-minutes-'.fake()->unique()->numerify('####').'@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);
}

it('writes every invited guest into the draft and omits the section when there are none', function (): void {
    $secretariat = invitedGuestMinutesActor();
    $dash = "\u{2014}";

    $empty = LegislativeSession::factory()->adjourned()->create([
        'secretary_id' => $secretariat->getKey(),
    ]);

    $withoutGuests = (string) app(LegislativeMinutesGenerator::class)
        ->draftFromSession($secretariat, $empty->fresh())
        ->content;

    expect($withoutGuests)->not->toContain(LegislativeMinutesGenerator::INVITED_GUESTS_HEADING);

    $session = LegislativeSession::factory()->adjourned()->create([
        'secretary_id' => $secretariat->getKey(),
    ]);

    SessionGuest::factory()->create([
        'session_id' => $session->getKey(),
        'name' => 'Juan Cruz',
        'organization' => 'City Legal',
        'speaking_topic' => null,
        'status' => SessionGuestStatus::DidNotAppear->value,
        'created_at' => now()->subMinutes(3),
    ]);
    SessionGuest::factory()->create([
        'session_id' => $session->getKey(),
        'name' => 'Maria Santos',
        'organization' => 'Provincial Health Office',
        'speaking_topic' => 'Rabies program update',
        'status' => SessionGuestStatus::Present->value,
        'created_at' => now()->subMinutes(4),
    ]);
    SessionGuest::factory()->create([
        'session_id' => $session->getKey(),
        'name' => 'Elena Reyes',
        'organization' => null,
        'speaking_topic' => 'Rabies program update',
        'status' => SessionGuestStatus::Present->value,
        'created_at' => now()->subMinutes(2),
    ]);
    SessionGuest::factory()->create([
        'session_id' => $session->getKey(),
        'name' => 'Pedro Luna',
        'organization' => null,
        'speaking_topic' => null,
        'status' => SessionGuestStatus::Invited->value,
        'created_at' => now()->subMinute(),
    ]);

    $first = app(LegislativeMinutesGenerator::class)->draftFromSession($secretariat, $session->fresh());
    $content = (string) $first->content;

    $attendanceAt = strpos($content, '## Attendance');
    $guestsAt = strpos($content, LegislativeMinutesGenerator::INVITED_GUESTS_HEADING);
    $proceedingsAt = strpos($content, '## Proceedings');

    expect($attendanceAt)->toBeInt()
        ->and($guestsAt)->toBeInt()
        ->and($proceedingsAt)->toBeInt()
        ->and($attendanceAt)->toBeLessThan($guestsAt)
        ->and($guestsAt)->toBeLessThan($proceedingsAt)
        ->and($content)->toContain("- Maria Santos {$dash} Present (Provincial Health Office) {$dash} Rabies program update")
        ->and($content)->toContain("- Juan Cruz {$dash} Did not appear (City Legal)")
        ->and($content)->toContain("- Elena Reyes {$dash} Present {$dash} Rabies program update")
        ->and($content)->toContain("- Pedro Luna {$dash} Invited")
        ->and($content)->not->toContain('checked in')
        ->and(strpos($content, 'Maria Santos'))->toBeLessThan(strpos($content, 'Juan Cruz'))
        ->and(strpos($content, 'Juan Cruz'))->toBeLessThan(strpos($content, 'Elena Reyes'))
        ->and(strpos($content, 'Elena Reyes'))->toBeLessThan(strpos($content, 'Pedro Luna'));

    SessionGuest::factory()->create([
        'session_id' => $session->getKey(),
        'name' => 'Ana Bautista',
        'status' => SessionGuestStatus::Invited->value,
    ]);

    expect($first->fresh()->content)->not->toContain('Ana Bautista');

    $regenerated = (string) app(LegislativeMinutesGenerator::class)
        ->draftFromSession($secretariat, $session->fresh())
        ->content;

    expect($regenerated)->toContain("- Ana Bautista {$dash} Invited");
});
