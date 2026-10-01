<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\LegislativeSession;
use App\Models\Minutes;
use App\Models\User;
use App\Services\AI\LegislativeMinutesGenerator;
use App\Services\AI\MinutesDiscussionSummarizer;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);
});

function pdfActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-minutes-pdf@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

function pdfMinutes(string $status, ?string $content, ?string $sessionNumber = null): Minutes
{
    $session = LegislativeSession::factory()->create([
        ...($sessionNumber !== null ? ['session_number' => $sessionNumber] : []),
        'title' => '10th Regular Session',
        'venue' => 'Sanggunian Session Hall',
        'scheduled_start_at' => '2025-09-11 02:13:00',
        'actual_start_at' => null,
        'adjourned_at' => null,
    ]);

    return Minutes::factory()->create([
        'session_id' => $session->getKey(),
        'status' => $status,
        'content' => $content,
        'prepared_by' => User::factory(),
    ]);
}

function minutesWithDiscussion(): string
{
    return implode("\n", [
        '# Minutes — 10th Regular Session',
        '',
        '> '.LegislativeMinutesGenerator::DRAFT_BANNER,
        '',
        '## Proceedings',
        '- 10:13–11:00 6. Reference of Business',
        '  - '.MinutesDiscussionSummarizer::PREFIX.' Members asked about the funding request for medical assistance.',
        '',
    ]);
}

function minutesPdfText(TestResponse $response): string
{
    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    $path = tempnam(sys_get_temp_dir(), 'minutes-pdf');
    file_put_contents($path, $response->getContent());
    $text = shell_exec('pdftotext -layout '.escapeshellarg((string) $path).' -');
    @unlink((string) $path);

    return is_string($text) ? $text : '';
}

it('downloads a pdf that includes the discussion under the agenda item', function (): void {
    $secretariat = pdfActor(UserRole::Secretariat);
    $minutes = pdfMinutes('session-completed', minutesWithDiscussion(), 'RS-10-2025');

    $text = minutesPdfText(
        test()->actingAs($secretariat)->get(route('minutes.pdf', $minutes)),
    );

    expect($text)->toContain('10TH REGULAR SESSION')
        ->and($text)->toContain('Republic of the Philippines')
        ->and($text)->toContain('DRAFT')
        ->and($text)->toContain('Reference of Business')
        ->and($text)->toContain(MinutesDiscussionSummarizer::PREFIX)
        ->and($text)->toContain('funding request for medical assistance')
        ->and($text)->not->toContain(LegislativeMinutesGenerator::DRAFT_BANNER)
        ->and($text)->toContain('RS-10-2025')
        ->and($text)->toContain('Page 1 of 1');

    expect(AuditLog::query()->where('event', 'minutes.download')->exists())->toBeTrue();
});

it('marks an ai draft and omits the draft mark once minutes are final', function (): void {
    $secretariat = pdfActor(UserRole::Secretariat);
    $draft = pdfMinutes('ai-draft', minutesWithDiscussion());
    $final = pdfMinutes('final-minutes', minutesWithDiscussion());

    $draftText = minutesPdfText(test()->actingAs($secretariat)->get(route('minutes.pdf', $draft)));
    $finalText = minutesPdfText(test()->actingAs($secretariat)->get(route('minutes.pdf', $final)));

    expect($draftText)->toContain('DRAFT')
        ->and($draftText)->toContain(LegislativeMinutesGenerator::DRAFT_BANNER)
        ->and($finalText)->not->toContain('DRAFT')
        ->and($finalText)->toContain(MinutesDiscussionSummarizer::PREFIX);
});

it('hides the download until there is minutes text and refuses an empty pdf', function (): void {
    $secretariat = pdfActor(UserRole::Secretariat);
    $minutes = pdfMinutes('session-completed', null);

    test()->actingAs($secretariat)
        ->get(route('minutes.show', $minutes))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.download', false));

    test()->actingAs($secretariat)
        ->get(route('minutes.pdf', $minutes))
        ->assertNotFound();
});

it('forbids a user who cannot view the minutes from downloading the pdf', function (): void {
    $publicUser = pdfActor(UserRole::PublicUser);
    $minutes = pdfMinutes('session-completed', minutesWithDiscussion());

    test()->actingAs($publicUser)
        ->get(route('minutes.pdf', $minutes))
        ->assertForbidden();
});
