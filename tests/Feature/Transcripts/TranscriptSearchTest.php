<?php

use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\Transcript;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);
});

it('finds transcript segments by query and returns timestamps', function (): void {
    $member = User::factory()->create([
        'is_active' => true,
        'is_seated_member' => true,
    ])->assignRole(UserRole::BoardMember->value);

    $session = LegislativeSession::factory()->create();

    Transcript::factory()->create([
        'session_id' => $session->getKey(),
        'segments' => [
            [
                'index' => 0,
                'start' => 12.5,
                'end' => 20.0,
                'speaker' => 'Speaker 1',
                'text' => 'We discussed the provincial health budget allocation.',
                'confidence' => 0.91,
            ],
            [
                'index' => 1,
                'start' => 45.0,
                'end' => 55.0,
                'speaker' => 'Speaker 2',
                'text' => 'The committee report was noted without objection.',
                'confidence' => 0.88,
            ],
        ],
        'full_text' => 'We discussed the provincial health budget allocation. The committee report was noted without objection.',
    ]);

    $response = $this->actingAs($member)
        ->getJson(route('sessions.transcript.search', [
            'session' => $session,
            'query' => 'health budget',
        ]))
        ->assertOk();

    expect($response->json('results'))->toHaveCount(1)
        ->and($response->json('results.0.start'))->toBe(12.5)
        ->and($response->json('results.0.text'))->toContain('health budget')
        ->and($response->json('results.0.jump_url'))->toContain('/transcript');
});
