<?php

use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Notifications\SessionScheduled;
use App\States\Session\AgendaPrepared;
use App\States\Session\Draft;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function sessionScheduledNotifyActor(UserRole $role, string $suffix = 'sched', array $overrides = []): User
{
    return User::factory()->create([
        'email' => "{$role->value}-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        ...$overrides,
    ])->assignRole($role->value);
}

it('notifies board members and the presiding officer when a session is scheduled', function (): void {
    Notification::fake();

    $secretariat = sessionScheduledNotifyActor(UserRole::Secretariat, 'actor');
    $member = sessionScheduledNotifyActor(UserRole::BoardMember, 'member');
    $presiding = sessionScheduledNotifyActor(UserRole::PresidingOfficer, 'po');
    $public = sessionScheduledNotifyActor(UserRole::PublicUser, 'public');

    $session = LegislativeSession::factory()->create([
        'status' => AgendaPrepared::$name,
        'presiding_officer_id' => $presiding->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.schedule', $session))
        ->assertRedirect();

    Notification::assertSentTo($member, SessionScheduled::class, function (SessionScheduled $notification, array $channels): bool {
        return in_array('mail', $channels, true)
            && in_array('database', $channels, true)
            && in_array('broadcast', $channels, true);
    });
    Notification::assertSentTo($presiding, SessionScheduled::class);
    Notification::assertNotSentTo($secretariat, SessionScheduled::class);
    Notification::assertNotSentTo($public, SessionScheduled::class);
});

it('does not notify an inactive board member when a session is scheduled', function (): void {
    Notification::fake();

    $secretariat = sessionScheduledNotifyActor(UserRole::Secretariat, 'inactive-actor');
    $inactive = sessionScheduledNotifyActor(UserRole::BoardMember, 'inactive-member', ['is_active' => false]);

    $session = LegislativeSession::factory()->create(['status' => AgendaPrepared::$name]);

    $this->actingAs($secretariat)
        ->post(route('sessions.schedule', $session))
        ->assertRedirect();

    Notification::assertNotSentTo($inactive, SessionScheduled::class);
});

it('notifies the assigned presiding officer even when they do not hold that role', function (): void {
    Notification::fake();

    $secretariat = sessionScheduledNotifyActor(UserRole::Secretariat, 'assigned-actor');
    $chair = sessionScheduledNotifyActor(UserRole::CommitteeChair, 'assigned-po');

    $session = LegislativeSession::factory()->create([
        'status' => AgendaPrepared::$name,
        'presiding_officer_id' => $chair->getKey(),
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.schedule', $session))
        ->assertRedirect();

    Notification::assertSentTo($chair, SessionScheduled::class);
});

it('sends session scheduled mail with a session link', function (): void {
    Notification::fake();

    $secretariat = sessionScheduledNotifyActor(UserRole::Secretariat, 'mail-actor');
    $member = sessionScheduledNotifyActor(UserRole::BoardMember, 'mail-member');

    $session = LegislativeSession::factory()->create([
        'status' => AgendaPrepared::$name,
        'title' => '12th Regular Session',
        'session_number' => 'RS-2026-00012',
        'venue' => 'Session Hall',
    ]);

    $this->actingAs($secretariat)
        ->post(route('sessions.schedule', $session))
        ->assertRedirect();

    Notification::assertSentTo($member, SessionScheduled::class, function (SessionScheduled $notification) use ($member, $session): bool {
        $mail = $notification->toMail($member);

        return $mail instanceof MailMessage
            && $notification->actionUrl() === route('sessions.show', $session, absolute: false);
    });
});

it('does not notify board members when a draft agenda is prepared', function (): void {
    Notification::fake();

    $secretariat = sessionScheduledNotifyActor(UserRole::Secretariat, 'agenda-actor');
    $member = sessionScheduledNotifyActor(UserRole::BoardMember, 'agenda-member');

    $session = LegislativeSession::factory()->create(['status' => Draft::$name]);

    $this->actingAs($secretariat)
        ->post(route('sessions.prepare-agenda', $session))
        ->assertRedirect();

    Notification::assertNotSentTo($member, SessionScheduled::class);
    Notification::assertNotSentTo($secretariat, SessionScheduled::class);
});
