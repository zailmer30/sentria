<?php

use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\User;
use App\States\Session\Adjourned;
use App\States\Session\Scheduled;
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

function authActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-session-auth@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('forbids board members from secretariat session control endpoints', function (): void {
    $member = authActor(UserRole::BoardMember);
    $session = LegislativeSession::factory()->inSession()->create();
    AgendaItem::factory()->procedural('call-to-order', 'Call to Order', 1)->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
    ]);

    $this->actingAs($member)->post(route('sessions.start', $session))->assertForbidden();
    $this->actingAs($member)->post(route('sessions.suspend', $session))->assertForbidden();
    $this->actingAs($member)->post(route('sessions.recess', $session))->assertForbidden();
    $this->actingAs($member)->post(route('sessions.resume', $session))->assertForbidden();
    $this->actingAs($member)->post(route('sessions.adjourn', $session))->assertForbidden();
    $this->actingAs($member)->post(route('sessions.agenda.advance', $session))->assertForbidden();
});

it('allows secretariat to start sessions and advance agenda items', function (): void {
    $secretariat = authActor(UserRole::Secretariat);
    $session = LegislativeSession::factory()->inSession()->create();
    AgendaItem::factory()->procedural('call-to-order', 'Call to Order', 1)->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
    ]);
    AgendaItem::factory()->procedural('roll-call', 'Roll Call', 2)->create([
        'session_id' => $session->getKey(),
        'status' => 'pending',
    ]);

    $this->actingAs($secretariat)->post(route('sessions.agenda.advance', $session))->assertRedirect();
});

it('lets secretariat adjourn from the clerk console', function (): void {
    $secretariat = authActor(UserRole::Secretariat);
    $session = LegislativeSession::factory()->inSession()->create();

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Secretariat')
            ->where('can.adjourn', true));

    $this->actingAs($secretariat)->post(route('sessions.adjourn', $session))->assertRedirect();
    expect($session->fresh()->status)->toBeInstanceOf(Adjourned::class);
});

it('lets secretariat start a sitting and forbids the chair from doing so', function (): void {
    $secretariat = authActor(UserRole::Secretariat);
    $presiding = authActor(UserRole::PresidingOfficer);
    $session = LegislativeSession::factory()->create([
        'status' => Scheduled::$name,
    ]);

    $this->actingAs($presiding)->post(route('sessions.start', $session))->assertForbidden();
    $this->actingAs($secretariat)->post(route('sessions.start', $session))->assertRedirect();
});

it('refuses to advance the agenda while voting is open', function (): void {
    $secretariat = authActor(UserRole::Secretariat);
    $session = LegislativeSession::factory()->inSession()->create();
    $current = AgendaItem::factory()->procedural('call-to-order', 'Call to Order', 1)->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
        'voting_round' => 1,
        'voting_open_at' => now(),
    ]);
    $next = AgendaItem::factory()->procedural('roll-call', 'Roll Call', 2)->create([
        'session_id' => $session->getKey(),
        'status' => 'pending',
    ]);

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->post(route('sessions.agenda.advance', $session))
        ->assertRedirect(route('sessions.floor.secretariat', $session))
        ->assertSessionHas('error', 'sessions.advance_blocked_voting');

    expect($current->fresh()->status)->toBe('in-progress')
        ->and($current->fresh()->voting_open_at)->not->toBeNull()
        ->and($next->fresh()->status)->toBe('pending');
});

it('lets the secretariat return to the previous item after an accidental advance', function (): void {
    $secretariat = authActor(UserRole::Secretariat);
    $session = LegislativeSession::factory()->inSession()->create();
    $first = AgendaItem::factory()->procedural('call-to-order', 'Call to Order', 1)->create([
        'session_id' => $session->getKey(),
        'status' => 'completed',
        'started_at' => now()->subMinutes(5),
        'completed_at' => now()->subMinute(),
    ]);
    $second = AgendaItem::factory()->procedural('roll-call', 'Roll Call', 2)->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
    ]);

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->post(route('sessions.agenda.retreat', $session))
        ->assertRedirect(route('sessions.floor.secretariat', $session))
        ->assertSessionHas('success', 'sessions.agenda_retreated');

    expect($first->fresh()->status)->toBe('in-progress')
        ->and($first->fresh()->completed_at)->toBeNull()
        ->and($second->fresh()->status)->toBe('pending')
        ->and($second->fresh()->started_at)->toBeNull();
});

it('forbids board members from retreating the agenda', function (): void {
    $member = authActor(UserRole::BoardMember);
    $session = LegislativeSession::factory()->inSession()->create();
    AgendaItem::factory()->procedural('call-to-order', 'Call to Order', 1)->create([
        'session_id' => $session->getKey(),
        'status' => 'completed',
        'completed_at' => now()->subMinute(),
    ]);
    AgendaItem::factory()->procedural('roll-call', 'Roll Call', 2)->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
    ]);

    $this->actingAs($member)->post(route('sessions.agenda.retreat', $session))->assertForbidden();
});

it('refuses to retreat the agenda while voting is open', function (): void {
    $secretariat = authActor(UserRole::Secretariat);
    $session = LegislativeSession::factory()->inSession()->create();
    $previous = AgendaItem::factory()->procedural('call-to-order', 'Call to Order', 1)->create([
        'session_id' => $session->getKey(),
        'status' => 'completed',
        'completed_at' => now()->subMinute(),
    ]);
    $current = AgendaItem::factory()->procedural('roll-call', 'Roll Call', 2)->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
        'voting_round' => 1,
        'voting_open_at' => now(),
    ]);

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->post(route('sessions.agenda.retreat', $session))
        ->assertRedirect(route('sessions.floor.secretariat', $session))
        ->assertSessionHas('error', 'sessions.retreat_blocked_voting');

    expect($previous->fresh()->status)->toBe('completed')
        ->and($current->fresh()->status)->toBe('in-progress');
});

it('refuses to retreat when there is no previous item', function (): void {
    $secretariat = authActor(UserRole::Secretariat);
    $session = LegislativeSession::factory()->inSession()->create();
    $current = AgendaItem::factory()->procedural('call-to-order', 'Call to Order', 1)->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
    ]);

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->post(route('sessions.agenda.retreat', $session))
        ->assertRedirect(route('sessions.floor.secretariat', $session))
        ->assertSessionHas('error', 'sessions.no_previous_item');

    expect($current->fresh()->status)->toBe('in-progress');
});

it('restores the last item after the agenda has been advanced off the end', function (): void {
    $secretariat = authActor(UserRole::Secretariat);
    $session = LegislativeSession::factory()->inSession()->create();
    $last = AgendaItem::factory()->procedural('adjournment', 'Adjournment', 1)->create([
        'session_id' => $session->getKey(),
        'status' => 'completed',
        'started_at' => now()->subMinutes(2),
        'completed_at' => now()->subMinute(),
    ]);

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->post(route('sessions.agenda.retreat', $session))
        ->assertRedirect(route('sessions.floor.secretariat', $session))
        ->assertSessionHas('success', 'sessions.agenda_retreated');

    expect($last->fresh()->status)->toBe('in-progress')
        ->and($last->fresh()->completed_at)->toBeNull();
});

it('exposes the previous item on the secretariat console', function (): void {
    $secretariat = authActor(UserRole::Secretariat);
    $session = LegislativeSession::factory()->inSession()->create();
    $previous = AgendaItem::factory()->procedural('call-to-order', 'Call to Order', 1)->create([
        'session_id' => $session->getKey(),
        'status' => 'completed',
        'completed_at' => now()->subMinute(),
    ]);
    $current = AgendaItem::factory()->procedural('roll-call', 'Roll Call', 2)->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
        'started_at' => now(),
    ]);
    $next = AgendaItem::factory()->procedural('opening-prayer', 'Opening Prayer', 3)->create([
        'session_id' => $session->getKey(),
        'status' => 'pending',
    ]);

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Secretariat')
            ->where('previous_item.id', $previous->getKey())
            ->where('current_item.id', $current->getKey())
            ->where('next_item.id', $next->getKey()));
});
