<?php

use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\Notification as NotificationModel;
use App\Models\User;
use App\Notifications\CommitteeMemberAppointed;
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

function inboxActor(UserRole $role, string $suffix = 'inbox'): User
{
    return User::factory()->create([
        'email' => "{$role->value}-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

function seedInboxNotification(User $user, bool $read = false): NotificationModel
{
    $committee = Committee::factory()->create();
    $notification = new CommitteeMemberAppointed($committee, 'member');

    $user->notifyNow($notification);

    $record = $user->notifications()->whereKey($notification->id)->firstOrFail();

    if ($read) {
        $record->markAsRead();
    }

    return $record->fresh();
}

it('lets an authenticated staff user view their notification inbox', function (): void {
    $user = inboxActor(UserRole::Secretariat);
    seedInboxNotification($user);

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.title_key', 'notifications.member_appointed_title'));
});

it('shares unread notification count with authenticated staff', function (): void {
    $user = inboxActor(UserRole::BoardMember, 'count');
    seedInboxNotification($user);
    seedInboxNotification($user, read: true);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unread_count', 1));
});

it('lets the owner mark a notification as read', function (): void {
    $user = inboxActor(UserRole::Secretariat, 'mark');
    $record = seedInboxNotification($user);

    $this->actingAs($user)
        ->patch(route('notifications.read', $record), [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('unread_count', 0);

    expect($record->fresh()->read_at)->not->toBeNull();
});

it('forbids marking another users notification as read', function (): void {
    $owner = inboxActor(UserRole::Secretariat, 'owner');
    $intruder = inboxActor(UserRole::BoardMember, 'intruder');
    $record = seedInboxNotification($owner);

    $this->actingAs($intruder)
        ->patch(route('notifications.read', $record))
        ->assertForbidden();
});

it('marks all notifications as read', function (): void {
    $user = inboxActor(UserRole::Secretariat, 'all');
    seedInboxNotification($user);
    seedInboxNotification($user);

    $this->actingAs($user)
        ->post(route('notifications.read-all'))
        ->assertRedirect()
        ->assertSessionHas('success', 'notifications.all_marked_read');

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('denies guests access to the notification inbox', function (): void {
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
});

it('returns recent notifications as json for the bell', function (): void {
    $user = inboxActor(UserRole::CommitteeChair, 'bell');
    seedInboxNotification($user);

    $this->actingAs($user)
        ->getJson(route('notifications.recent'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonCount(1, 'notifications');
});
