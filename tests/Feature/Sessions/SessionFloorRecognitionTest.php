<?php

use App\Enums\UserRole;
use App\Events\FloorRecognitionUpdated;
use App\Models\AgendaItem;
use App\Models\FloorRecognitionRequest;
use App\Models\LegislativeSession;
use App\Models\Motion;
use App\Models\User;
use App\States\Session\InSession;
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

    Event::fake([FloorRecognitionUpdated::class]);
});

function recognitionActor(UserRole $role, string $suffix): User
{
    return User::factory()->create([
        'email' => "{$role->value}-recognition-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => $role->isSeatedMember(),
    ])->assignRole($role->value);
}

/**
 * @return array{member: User, other: User, chair: User, clerk: User, session: LegislativeSession, item: AgendaItem}
 */
function recognitionSitting(): array
{
    $member = recognitionActor(UserRole::BoardMember, 'mover');
    $other = recognitionActor(UserRole::BoardMember, 'other');
    $chair = recognitionActor(UserRole::PresidingOfficer, 'chair');
    $clerk = recognitionActor(UserRole::Secretariat, 'clerk');

    $session = LegislativeSession::factory()->inSession()->create([
        'seated_member_count' => 12,
        'status' => InSession::$name,
    ]);

    $item = AgendaItem::factory()->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
        'position' => 1,
        'item_number' => '5',
        'title' => 'Calendar of Business',
    ]);

    return compact('member', 'other', 'chair', 'clerk', 'session', 'item');
}

it('lets a board member seek recognition without submitting motion text', function (): void {
    ['member' => $member, 'session' => $session, 'item' => $item] = recognitionSitting();

    $this->actingAs($member)
        ->from(route('sessions.floor.member', $session))
        ->post(route('sessions.recognition.store', $session))
        ->assertRedirect();

    $request = FloorRecognitionRequest::query()->sole();

    expect($request->user_id)->toBe($member->getKey())
        ->and($request->agenda_item_id)->toBe($item->getKey())
        ->and($request->status)->toBe('pending')
        ->and(Motion::query()->count())->toBe(0);

    Event::assertDispatched(
        FloorRecognitionUpdated::class,
        fn (FloorRecognitionUpdated $event): bool => $event->recognized === null
            && count($event->pending) === 1
            && $event->pending[0]['user_id'] === $member->getKey(),
    );

    $this->actingAs($member)
        ->get(route('sessions.floor.member', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/BoardMember')
            ->where('can.seek_recognition', true)
            ->has('recognition.pending', 1)
            ->where('recognition.pending.0.user_id', $member->getKey())
            ->where('recognition.pending.0.can.cancel', true)
            ->where('recognition.pending.0.can.recognize', false)
            ->where('recognition.recognized', null));
});

it('rejects a second pending request from the same member', function (): void {
    ['member' => $member, 'session' => $session] = recognitionSitting();

    $this->actingAs($member)->post(route('sessions.recognition.store', $session))->assertRedirect();
    $this->actingAs($member)
        ->from(route('sessions.floor.member', $session))
        ->post(route('sessions.recognition.store', $session))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(FloorRecognitionRequest::query()->pending()->count())->toBe(1);
});

it('lets a member cancel their own pending request and forbids cancelling another', function (): void {
    ['member' => $member, 'other' => $other, 'session' => $session, 'item' => $item] = recognitionSitting();

    $own = FloorRecognitionRequest::factory()->create([
        'session_id' => $session->getKey(),
        'user_id' => $member->getKey(),
        'agenda_item_id' => $item->getKey(),
        'status' => 'pending',
        'raised_at' => now(),
    ]);

    $this->actingAs($other)
        ->post(route('sessions.recognition.cancel', [$session, $own]))
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('sessions.recognition.cancel', [$session, $own]))
        ->assertRedirect();

    expect($own->fresh()->status)->toBe('cancelled')
        ->and($own->fresh()->resolved_at)->not->toBeNull();
});

it('lets the presiding officer recognize and then record the spoken motion for that member', function (): void {
    ['member' => $member, 'chair' => $chair, 'session' => $session, 'item' => $item] = recognitionSitting();

    $request = FloorRecognitionRequest::factory()->create([
        'session_id' => $session->getKey(),
        'user_id' => $member->getKey(),
        'agenda_item_id' => $item->getKey(),
        'status' => 'pending',
        'raised_at' => now(),
    ]);

    $this->actingAs($member)
        ->post(route('sessions.recognition.recognize', [$session, $request]))
        ->assertForbidden();

    $this->actingAs($chair)
        ->from(route('sessions.floor.member', $session))
        ->post(route('sessions.recognition.recognize', [$session, $request]))
        ->assertRedirect();

    expect($request->fresh()->status)->toBe('recognized')
        ->and($request->fresh()->resolved_at)->toBeNull();

    $this->actingAs($chair)
        ->get(route('sessions.floor.member', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/BoardMember')
            ->where('recognition.recognized.user_id', $member->getKey())
            ->where('can.record_spoken_motion', true)
            ->has('recognition.pending', 0));

    $this->actingAs($chair)
        ->post(route('sessions.motions.store', $session), [
            'agenda_item_id' => $item->getKey(),
            'text' => 'I move that the measure be approved.',
            'type' => 'main',
            'moved_by' => $member->getKey(),
        ])
        ->assertRedirect();

    $motion = Motion::query()->sole();

    expect($motion->moved_by)->toBe($member->getKey())
        ->and($motion->text)->toBe('I move that the measure be approved.')
        ->and($request->fresh()->resolved_at)->not->toBeNull();
});

it('lets secretariat record a spoken motion for the recognized member', function (): void {
    ['member' => $member, 'clerk' => $clerk, 'session' => $session, 'item' => $item] = recognitionSitting();

    FloorRecognitionRequest::factory()->recognized()->create([
        'session_id' => $session->getKey(),
        'user_id' => $member->getKey(),
        'agenda_item_id' => $item->getKey(),
        'raised_at' => now(),
    ]);

    $this->actingAs($clerk)
        ->post(route('sessions.motions.store', $session), [
            'agenda_item_id' => $item->getKey(),
            'text' => 'I move that the matter be referred to committee.',
            'moved_by' => $member->getKey(),
        ])
        ->assertRedirect();

    expect(Motion::query()->sole()->moved_by)->toBe($member->getKey());
});

it('forbids secretariat from recording a motion without a recognized speaker', function (): void {
    ['member' => $member, 'clerk' => $clerk, 'session' => $session, 'item' => $item] = recognitionSitting();

    $this->actingAs($clerk)
        ->post(route('sessions.motions.store', $session), [
            'agenda_item_id' => $item->getKey(),
            'text' => 'I move that the measure be approved.',
            'moved_by' => $member->getKey(),
        ])
        ->assertStatus(422);
});

it('lets the presiding officer dismiss a pending request', function (): void {
    ['member' => $member, 'chair' => $chair, 'session' => $session, 'item' => $item] = recognitionSitting();

    $request = FloorRecognitionRequest::factory()->create([
        'session_id' => $session->getKey(),
        'user_id' => $member->getKey(),
        'agenda_item_id' => $item->getKey(),
        'status' => 'pending',
        'raised_at' => now(),
    ]);

    $this->actingAs($chair)
        ->post(route('sessions.recognition.dismiss', [$session, $request]))
        ->assertRedirect();

    expect($request->fresh()->status)->toBe('dismissed');
});

it('rejects recognizing a second speaker while another has the floor', function (): void {
    ['member' => $member, 'other' => $other, 'chair' => $chair, 'session' => $session, 'item' => $item] = recognitionSitting();

    FloorRecognitionRequest::factory()->recognized()->create([
        'session_id' => $session->getKey(),
        'user_id' => $member->getKey(),
        'agenda_item_id' => $item->getKey(),
        'raised_at' => now()->subMinute(),
    ]);

    $queued = FloorRecognitionRequest::factory()->create([
        'session_id' => $session->getKey(),
        'user_id' => $other->getKey(),
        'agenda_item_id' => $item->getKey(),
        'status' => 'pending',
        'raised_at' => now(),
    ]);

    $this->actingAs($chair)
        ->from(route('sessions.floor.member', $session))
        ->post(route('sessions.recognition.recognize', [$session, $queued]))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($queued->fresh()->status)->toBe('pending');
});

it('exposes the live queue on secretariat and member floors', function (): void {
    ['member' => $member, 'chair' => $chair, 'clerk' => $clerk, 'session' => $session, 'item' => $item] = recognitionSitting();

    FloorRecognitionRequest::factory()->create([
        'session_id' => $session->getKey(),
        'user_id' => $member->getKey(),
        'agenda_item_id' => $item->getKey(),
        'status' => 'pending',
        'raised_at' => now(),
    ]);

    $this->actingAs($clerk)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Secretariat')
            ->has('recognition.pending', 1)
            ->where('recognition.pending.0.can.recognize', false)
            ->where('can.record_spoken_motion', false));

    $this->actingAs($chair)
        ->get(route('sessions.floor.member', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/BoardMember')
            ->has('recognition.pending', 1)
            ->where('recognition.pending.0.can.recognize', true)
            ->where('recognition.pending.0.can.dismiss', true));
});

it('redirects the retired presiding floor to the member workstation', function (): void {
    ['chair' => $chair, 'session' => $session] = recognitionSitting();

    $this->actingAs($chair)
        ->get(route('sessions.floor.presiding', $session))
        ->assertRedirect(route('sessions.floor.member', $session));
});

it('includes pending recognition on the hall dashboard', function (): void {
    ['member' => $member, 'chair' => $chair, 'session' => $session, 'item' => $item] = recognitionSitting();

    FloorRecognitionRequest::factory()->create([
        'session_id' => $session->getKey(),
        'user_id' => $member->getKey(),
        'agenda_item_id' => $item->getKey(),
        'status' => 'pending',
        'raised_at' => now(),
    ]);

    $this->actingAs($chair)
        ->get(route('sessions.floor.dashboard', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Dashboard')
            ->has('recognition.pending', 1)
            ->where('recognition.pending.0.user_id', $member->getKey()));
});
