<?php

use App\Enums\Confidentiality;
use App\Enums\DashboardRange;
use App\Enums\DocumentType;
use App\Enums\ProcessingStatus;
use App\Enums\UserRole;
use App\Models\CommitteeReferral;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\LegislativeSession;
use App\Models\Publication;
use App\Models\User;
use App\Services\Dashboard\DashboardMetricsService;
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

function dashboardActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-metrics@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'first_name' => 'Joselito',
        'display_name' => 'Joselito Fernandez',
    ])->assignRole($role->value);
}

it('maps a legacy 90-day bookmark onto the year-to-date window', function (): void {
    expect(DashboardRange::fromRequest('90d'))->toBe(DashboardRange::Year)
        ->and(DashboardRange::fromRequest('ytd'))->toBe(DashboardRange::Year)
        ->and(DashboardRange::Year->since()->format('m-d'))->toBe('01-01');
});

it('assembles the secretariat desk figures from visible records', function (): void {
    $user = dashboardActor(UserRole::Secretariat);

    Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'author_id' => $user->getKey(),
        'confidentiality' => Confidentiality::Internal->value,
        'status' => 'submitted',
        'submitted_at' => now()->subDays(2),
    ]);

    Document::factory()->ofType(DocumentType::ProposedResolution)->create([
        'author_id' => $user->getKey(),
        'confidentiality' => Confidentiality::Restricted->value,
        'status' => 'approved',
        'submitted_at' => now()->subDays(10),
    ]);

    $ocrReady = Document::factory()->create([
        'author_id' => $user->getKey(),
        'status' => 'secretariat-review',
        'submitted_at' => now()->subDay(),
    ]);

    DocumentVersion::factory()->create([
        'document_id' => $ocrReady->getKey(),
        'is_current' => true,
        'processing_status' => ProcessingStatus::Completed,
        'uploaded_by' => $user->getKey(),
    ]);

    CommitteeReferral::factory()->overdue()->create();

    Publication::factory()->published()->create();

    LegislativeSession::factory()->scheduled()->create([
        'scheduled_start_at' => now()->addDays(3)->setTime(9, 0),
        'title' => '43rd Regular Session',
        'type' => 'regular',
    ]);

    $metrics = app(DashboardMetricsService::class)->for($user, DashboardRange::Month);

    expect($metrics)
        ->toHaveKeys(['documents', 'sittings', 'processing', 'publications', 'referrals'])
        ->and($metrics['documents']['total'])->toBeGreaterThanOrEqual(3)
        ->and($metrics['documents']['restricted'])->toBe(1)
        ->and($metrics['documents']['enacted_ytd'])->toBeGreaterThanOrEqual(1)
        ->and(collect($metrics['documents']['composition'])->pluck('key')->all())->toBe([
            'ordinances',
            'resolutions',
            'minutes',
            'committee_reports',
        ])
        ->and($metrics['documents']['standings'])->toHaveCount(4)
        ->and($metrics['documents']['recent'])->not->toBeEmpty()
        ->and($metrics['documents']['intake'][0])->toHaveKeys(['bucket', 'count', 'filed', 'published'])
        ->and($metrics['sittings'])->toHaveCount(1)
        ->and($metrics['sittings'][0]['title'])->toBe('43rd Regular Session');

    $ocr = collect($metrics['processing'])->firstWhere('key', 'ocr');
    expect($ocr)->not->toBeNull()
        ->and($ocr['done'])->toBeGreaterThanOrEqual(1);
});

it('renders the redesigned dashboard for the secretariat', function (): void {
    $user = dashboardActor(UserRole::Secretariat);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('givenName', 'Joselito')
            ->where('role', UserRole::Secretariat->value)
            ->has('metrics.documents.standings')
            ->has('metrics.documents.composition')
            ->has('metrics.documents.recent')
            ->has('actions')
            ->where('rangeOptions.2.value', 'ytd')
            ->where('rangeOptions.2.label', 'Ytd')
        );
});

it('omits document widgets from the public dashboard', function (): void {
    $user = dashboardActor(UserRole::PublicUser);

    $metrics = app(DashboardMetricsService::class)->for($user, DashboardRange::Month);

    expect($metrics)->not->toHaveKey('documents')
        ->and($metrics)->not->toHaveKey('sittings');
});
