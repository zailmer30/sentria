<?php

use App\Enums\UserRole;
use App\Events\SessionStateChanged;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\User;
use App\States\Session\Adjourned;
use App\States\Session\InSession;
use App\States\Session\Scheduled;
use App\States\Session\Suspended;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);
});

function recessActor(UserRole $role, string $suffix = 'recess'): User
{
    return User::factory()->create([
        'email' => "{$role->value}-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
        'is_seated_member' => $role !== UserRole::Secretariat,
    ])->assignRole($role->value);
}

it('lets the secretariat start a ten-minute recess by default', function (): void {
    $secretariat = recessActor(UserRole::Secretariat, 'default');
    $session = LegislativeSession::factory()->inSession()->create();

    Event::fake([SessionStateChanged::class]);

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->post(route('sessions.recess', $session))
        ->assertRedirect(route('sessions.floor.secretariat', $session))
        ->assertSessionHas('success', 'sessions.recessed');

    $fresh = $session->fresh();

    expect($fresh->status)->toBeInstanceOf(Suspended::class)
        ->and($fresh->recess_ends_at)->not->toBeNull()
        ->and((int) round($fresh->updated_at->diffInMinutes($fresh->recess_ends_at)))->toBe(10);

    Event::assertDispatched(SessionStateChanged::class, function (SessionStateChanged $event) use ($fresh): bool {
        $payload = $event->broadcastWith();

        return $payload['session_id'] === $fresh->getKey()
            && $payload['status'] === Suspended::$name
            && $payload['recess_ends_at'] === $fresh->recess_ends_at?->toIso8601String()
            && is_int($payload['recess_remaining_seconds'])
            && $payload['recess_remaining_seconds'] >= 599
            && $payload['recess_remaining_seconds'] <= 600;
    });
});

it('accepts a custom recess length', function (): void {
    $secretariat = recessActor(UserRole::Secretariat, 'custom');
    $session = LegislativeSession::factory()->inSession()->create();

    $this->actingAs($secretariat)
        ->post(route('sessions.recess', $session), ['duration_minutes' => 15])
        ->assertRedirect();

    $fresh = $session->fresh();

    expect($fresh->recess_ends_at)->not->toBeNull()
        ->and((int) round($fresh->updated_at->diffInMinutes($fresh->recess_ends_at)))->toBe(15);
});

it('leaves recess_ends_at empty on an untimed suspend', function (): void {
    $secretariat = recessActor(UserRole::Secretariat, 'untimed');
    $session = LegislativeSession::factory()->inSession()->create();

    $this->actingAs($secretariat)->post(route('sessions.suspend', $session))->assertRedirect();

    $fresh = $session->fresh();

    expect($fresh->status)->toBeInstanceOf(Suspended::class)
        ->and($fresh->recess_ends_at)->toBeNull();
});

it('clears the recess clock on resume', function (): void {
    $secretariat = recessActor(UserRole::Secretariat, 'resume');
    $session = LegislativeSession::factory()->inSession()->create();

    $this->actingAs($secretariat)->post(route('sessions.recess', $session))->assertRedirect();
    $this->actingAs($secretariat)->post(route('sessions.resume', $session))->assertRedirect();

    $fresh = $session->fresh();

    expect($fresh->status)->toBeInstanceOf(InSession::class)
        ->and($fresh->recess_ends_at)->toBeNull();
});

it('clears the recess clock on adjourn', function (): void {
    $secretariat = recessActor(UserRole::Secretariat, 'adjourn');
    $session = LegislativeSession::factory()->inSession()->create();

    $this->actingAs($secretariat)->post(route('sessions.recess', $session))->assertRedirect();
    $this->actingAs($secretariat)->post(route('sessions.adjourn', $session))->assertRedirect();

    $fresh = $session->fresh();

    expect($fresh->status)->toBeInstanceOf(Adjourned::class)
        ->and($fresh->recess_ends_at)->toBeNull();
});

it('refuses a recess while a ballot is open', function (): void {
    $secretariat = recessActor(UserRole::Secretariat, 'voting');
    $session = LegislativeSession::factory()->inSession()->create();
    AgendaItem::factory()->procedural('call-to-order', 'Call to Order', 1)->create([
        'session_id' => $session->getKey(),
        'status' => 'in-progress',
        'voting_round' => 1,
        'voting_open_at' => now(),
    ]);

    $this->actingAs($secretariat)
        ->from(route('sessions.floor.secretariat', $session))
        ->post(route('sessions.recess', $session))
        ->assertRedirect(route('sessions.floor.secretariat', $session))
        ->assertSessionHas('error', 'sessions.recess_blocked_voting');

    expect($session->fresh()->status)->toBeInstanceOf(InSession::class)
        ->and($session->fresh()->recess_ends_at)->toBeNull();
});

it('refuses a recess when the sitting is not live', function (): void {
    $secretariat = recessActor(UserRole::Secretariat, 'scheduled');
    $session = LegislativeSession::factory()->create([
        'status' => Scheduled::$name,
    ]);

    $this->actingAs($secretariat)
        ->from(route('sessions.show', $session))
        ->post(route('sessions.recess', $session))
        ->assertRedirect(route('sessions.show', $session))
        ->assertSessionHas('error', 'sessions.recess_not_live');
});

it('exposes recess_ends_at on the floor payload', function (): void {
    $secretariat = recessActor(UserRole::Secretariat, 'payload');
    $session = LegislativeSession::factory()->inSession()->create();

    $this->actingAs($secretariat)->post(route('sessions.recess', $session), ['duration_minutes' => 10]);

    $endsAt = $session->fresh()->recess_ends_at?->toIso8601String();

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.dashboard', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Dashboard')
            ->where('session.status', Suspended::$name)
            ->where('session.recess_ends_at', $endsAt)
            ->where('session.recess_remaining_seconds', fn ($seconds) => is_int($seconds) && $seconds >= 599 && $seconds <= 600));

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sessions/Floor/Secretariat')
            ->where('can.suspend', false)
            ->where('can.resume', true)
            ->where('session.recess_ends_at', $endsAt));
});

it('lets the secretariat suspend from the clerk console', function (): void {
    $secretariat = recessActor(UserRole::Secretariat, 'can-suspend');
    $session = LegislativeSession::factory()->inSession()->create();

    $this->actingAs($secretariat)
        ->get(route('sessions.floor.secretariat', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.suspend', true));
});
