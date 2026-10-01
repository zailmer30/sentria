<?php

use App\Enums\UserRole;
use App\Events\SessionChatInboxUpdated;
use App\Events\SessionChatMessageSent;
use App\Models\LegislativeSession;
use App\Models\SessionConversation;
use App\Models\SessionMessage;
use App\Models\User;
use App\States\Session\Suspended;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function chatActor(UserRole $role, string $suffix, bool $seated = false): User
{
    return User::factory()->create([
        'email' => "{$role->value}-chat-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => $seated,
        'display_name' => "{$role->label()} {$suffix}",
    ])->assignRole($role->value);
}

function liveChatSession(?User $secretary = null): LegislativeSession
{
    return LegislativeSession::factory()->inSession()->create([
        'secretary_id' => $secretary?->getKey(),
    ]);
}

it('lets a member direct-message the designated secretary and another clerk', function (): void {
    $member = chatActor(UserRole::BoardMember, 'dm', true);
    $secretary = chatActor(UserRole::Secretariat, 'named');
    $otherClerk = chatActor(UserRole::Secretariat, 'other');
    $session = liveChatSession($secretary);

    $this->actingAs($member)
        ->postJson(route('sessions.chat.direct', $session), ['user_id' => $secretary->getKey()])
        ->assertOk()
        ->assertJsonPath('type', 'direct')
        ->assertJsonPath('title', $secretary->display_name);

    $this->actingAs($member)
        ->postJson(route('sessions.chat.direct', $session), ['user_id' => $otherClerk->getKey()])
        ->assertOk()
        ->assertJsonPath('title', $otherClerk->display_name);

    $this->actingAs($member)
        ->getJson(route('sessions.chat.index', $session))
        ->assertOk()
        ->assertJsonCount(2, 'conversations');
});

it('keeps member-to-member threads private from a third member and a clerk who is not in them', function (): void {
    Event::fake([SessionChatMessageSent::class, SessionChatInboxUpdated::class]);

    $alice = chatActor(UserRole::BoardMember, 'alice', true);
    $bob = chatActor(UserRole::BoardMember, 'bob', true);
    $cara = chatActor(UserRole::BoardMember, 'cara', true);
    $clerk = chatActor(UserRole::Secretariat, 'idle');
    $session = liveChatSession($clerk);

    $thread = $this->actingAs($alice)
        ->postJson(route('sessions.chat.direct', $session), ['user_id' => $bob->getKey()])
        ->assertOk()
        ->json();

    $conversationId = $thread['id'];

    $this->actingAs($alice)
        ->postJson(route('sessions.chat.messages', [$session, $conversationId]), [
            'body' => 'Caucus only.',
        ])
        ->assertCreated();

    $this->actingAs($cara)
        ->getJson(route('sessions.chat.show', [$session, $conversationId]))
        ->assertForbidden();

    $this->actingAs($cara)
        ->postJson(route('sessions.chat.messages', [$session, $conversationId]), [
            'body' => 'Hijack',
        ])
        ->assertForbidden();

    $this->actingAs($clerk)
        ->getJson(route('sessions.chat.show', [$session, $conversationId]))
        ->assertForbidden();

    $this->actingAs($bob)
        ->getJson(route('sessions.chat.show', [$session, $conversationId]))
        ->assertOk()
        ->assertJsonPath('messages.0.body', 'Caucus only.');
});

it('requires at least three people to create a group and hides it from non-members', function (): void {
    $creator = chatActor(UserRole::BoardMember, 'host', true);
    $member = chatActor(UserRole::BoardMember, 'guest', true);
    $chair = chatActor(UserRole::PresidingOfficer, 'chair', true);
    $outsider = chatActor(UserRole::BoardMember, 'out', true);
    $session = liveChatSession();

    $this->actingAs($creator)
        ->postJson(route('sessions.chat.groups', $session), [
            'name' => 'Too small',
            'participant_ids' => [$member->getKey()],
        ])
        ->assertUnprocessable();

    $group = $this->actingAs($creator)
        ->postJson(route('sessions.chat.groups', $session), [
            'name' => 'Floor huddle',
            'participant_ids' => [$member->getKey(), $chair->getKey()],
        ])
        ->assertCreated()
        ->json();

    expect($group['type'])->toBe('group')
        ->and($group['title'])->toBe('Floor huddle');

    $this->actingAs($outsider)
        ->getJson(route('sessions.chat.show', [$session, $group['id']]))
        ->assertForbidden();

    $this->actingAs($member)
        ->getJson(route('sessions.chat.show', [$session, $group['id']]))
        ->assertOk();
});

it('rejects legal reviewers and the public from chat endpoints', function (): void {
    $legal = chatActor(UserRole::LegalTechnicalReviewer, 'legal');
    $public = chatActor(UserRole::PublicUser, 'public');
    $member = chatActor(UserRole::BoardMember, 'ok', true);
    $session = liveChatSession();

    $this->actingAs($legal)
        ->getJson(route('sessions.chat.index', $session))
        ->assertForbidden();

    $this->actingAs($public)
        ->getJson(route('sessions.chat.index', $session))
        ->assertForbidden();

    $this->actingAs($member)
        ->postJson(route('sessions.chat.direct', $session), ['user_id' => $legal->getKey()])
        ->assertForbidden();
});

it('allows chat during recess and forbids it when scheduled or adjourned', function (): void {
    $member = chatActor(UserRole::BoardMember, 'when', true);
    $clerk = chatActor(UserRole::Secretariat, 'when');
    $scheduled = LegislativeSession::factory()->scheduled()->create();
    $adjourned = LegislativeSession::factory()->adjourned()->create();
    $recess = LegislativeSession::factory()->inSession()->create([
        'status' => Suspended::$name,
    ]);

    $this->actingAs($member)
        ->getJson(route('sessions.chat.index', $scheduled))
        ->assertForbidden();

    $this->actingAs($member)
        ->getJson(route('sessions.chat.index', $adjourned))
        ->assertForbidden();

    $this->actingAs($member)
        ->postJson(route('sessions.chat.direct', $recess), ['user_id' => $clerk->getKey()])
        ->assertOk();

    $this->actingAs($member)
        ->postJson(route('sessions.chat.messages', [$recess, SessionConversation::query()->first()->getKey()]), [
            'body' => 'During recess.',
        ])
        ->assertCreated();
});

it('does not leak chat bodies onto the hall dashboard', function (): void {
    $member = chatActor(UserRole::BoardMember, 'hall', true);
    $clerk = chatActor(UserRole::Secretariat, 'hall');
    $session = liveChatSession($clerk);

    $thread = $this->actingAs($member)
        ->postJson(route('sessions.chat.direct', $session), ['user_id' => $clerk->getKey()])
        ->json();

    $secret = 'WHISPER-BODY-NOT-FOR-THE-HALL';

    $this->actingAs($member)
        ->postJson(route('sessions.chat.messages', [$session, $thread['id']]), [
            'body' => $secret,
        ])
        ->assertCreated();

    $this->actingAs($member)
        ->get(route('sessions.floor.dashboard', $session))
        ->assertOk()
        ->assertDontSee($secret, false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Dashboard')
            ->missing('conversations')
            ->missing('chat'));
});

it('purges conversations 24 hours after adjournment and leaves fresher ones', function (): void {
    $old = LegislativeSession::factory()->adjourned()->create([
        'adjourned_at' => now()->subHours(25),
    ]);
    $fresh = LegislativeSession::factory()->adjourned()->create([
        'adjourned_at' => now()->subHours(2),
    ]);
    $creator = chatActor(UserRole::BoardMember, 'purge', true);

    $stale = SessionConversation::factory()->group('Stale huddle')->create([
        'session_id' => $old->getKey(),
        'created_by' => $creator->getKey(),
    ]);
    SessionMessage::factory()->create([
        'conversation_id' => $stale->getKey(),
        'user_id' => $creator->getKey(),
        'body' => 'Gone tomorrow.',
    ]);

    $kept = SessionConversation::factory()->group('Still here')->create([
        'session_id' => $fresh->getKey(),
        'created_by' => $creator->getKey(),
    ]);

    $this->artisan('sentria:purge-session-chat')
        ->assertSuccessful();

    expect(SessionConversation::query()->find($stale->getKey()))->toBeNull()
        ->and(SessionMessage::query()->where('conversation_id', $stale->getKey())->exists())->toBeFalse()
        ->and(SessionConversation::query()->find($kept->getKey()))->not->toBeNull();
});

it('lists the designated secretary first among clerks', function (): void {
    $member = chatActor(UserRole::BoardMember, 'dir', true);
    $secretary = chatActor(UserRole::Secretariat, 'first');
    $other = chatActor(UserRole::Secretariat, 'second');
    $session = liveChatSession($secretary);

    $clerks = $this->actingAs($member)
        ->getJson(route('sessions.chat.directory', $session))
        ->assertOk()
        ->json('clerks');

    expect($clerks[0]['id'])->toBe($secretary->getKey())
        ->and($clerks[0]['is_designated_secretary'])->toBeTrue()
        ->and(collect($clerks)->pluck('id'))->toContain($other->getKey());
});

it('reuses a single direct thread for an unordered pair in a sitting', function (): void {
    $alice = chatActor(UserRole::BoardMember, 'pair-a', true);
    $bob = chatActor(UserRole::BoardMember, 'pair-b', true);
    $session = liveChatSession();

    $first = $this->actingAs($alice)
        ->postJson(route('sessions.chat.direct', $session), ['user_id' => $bob->getKey()])
        ->json('id');

    $second = $this->actingAs($bob)
        ->postJson(route('sessions.chat.direct', $session), ['user_id' => $alice->getKey()])
        ->json('id');

    expect($second)->toBe($first)
        ->and(SessionConversation::query()->where('session_id', $session->getKey())->count())->toBe(1);
});

it('records last_read_at on participants so a seen receipt can attach to the latest message', function (): void {
    $alice = chatActor(UserRole::BoardMember, 'seen-a', true);
    $bob = chatActor(UserRole::BoardMember, 'seen-b', true);
    $session = liveChatSession();

    $thread = $this->actingAs($alice)
        ->postJson(route('sessions.chat.direct', $session), ['user_id' => $bob->getKey()])
        ->assertOk()
        ->json();

    $sent = $this->actingAs($alice)
        ->postJson(route('sessions.chat.messages', [$session, $thread['id']]), [
            'body' => 'Please confirm.',
        ])
        ->assertCreated()
        ->json();

    expect($sent['created_at'])->not->toBeEmpty();

    $read = $this->actingAs($bob)
        ->postJson(route('sessions.chat.read', [$session, $thread['id']]))
        ->assertOk()
        ->json();

    expect($read['ok'])->toBeTrue()
        ->and($read['last_read_at'])->not->toBeEmpty();

    $shown = $this->actingAs($alice)
        ->getJson(route('sessions.chat.show', [$session, $thread['id']]))
        ->assertOk()
        ->json();

    $bobRow = collect($shown['participants'])->firstWhere('id', $bob->getKey());

    expect($bobRow['last_read_at'])->not->toBeNull()
        ->and($shown['messages'][0]['created_at'])->not->toBeNull();
});
