<?php

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Document\Registered;
use App\States\Document\SecretariatReview;
use App\States\Document\Submitted;
use App\States\Session\AgendaPrepared;
use App\States\Session\Draft;
use App\States\Session\Scheduled;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function actor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-workflow@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('transitions a session from draft to agenda-prepared when permitted', function (): void {
    $secretariat = actor(UserRole::Secretariat);
    $session = LegislativeSession::factory()->create(['status' => Draft::$name]);

    app(GuardedStateTransition::class)->transition($session, AgendaPrepared::class, $secretariat);

    expect($session->fresh()->status)->toBeInstanceOf(AgendaPrepared::class);
    $this->assertDatabaseHas('audit_logs', [
        'event' => 'workflow.transition',
        'auditable_id' => $session->getKey(),
    ]);
});

it('transitions a prepared session to scheduled when permitted', function (): void {
    $secretariat = actor(UserRole::Secretariat);
    $session = LegislativeSession::factory()->create(['status' => AgendaPrepared::$name]);

    app(GuardedStateTransition::class)->transition($session, Scheduled::class, $secretariat);

    expect($session->fresh()->status)->toBeInstanceOf(Scheduled::class);
});

it('throws and audit-logs invalid session transitions', function (): void {
    $secretariat = actor(UserRole::Secretariat);
    $session = LegislativeSession::factory()->create(['status' => Draft::$name]);

    expect(fn () => app(GuardedStateTransition::class)->transition($session, Scheduled::class, $secretariat))
        ->toThrow(TransitionNotFound::class);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'workflow.transition.invalid',
        'auditable_id' => $session->getKey(),
    ]);

    expect($session->fresh()->status)->toBeInstanceOf(Draft::class);
});

it('denies session transitions without permission', function (): void {
    $member = actor(UserRole::BoardMember);
    $session = LegislativeSession::factory()->create(['status' => Draft::$name]);

    expect(fn () => app(GuardedStateTransition::class)->transition($session, AgendaPrepared::class, $member))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'workflow.transition.denied',
        'auditable_id' => $session->getKey(),
    ]);
});

it('transitions a document through secretariat review when permitted', function (): void {
    $secretariat = actor(UserRole::Secretariat);
    $document = Document::factory()->create(['status' => Submitted::$name]);

    $service = app(GuardedStateTransition::class);
    $service->transition($document, SecretariatReview::class, $secretariat);
    $service->transition($document->fresh(), Registered::class, $secretariat);

    expect($document->fresh()->status)->toBeInstanceOf(Registered::class);
});

it('throws on invalid document workflow jumps', function (): void {
    $secretariat = actor(UserRole::Secretariat);
    $document = Document::factory()->create(['status' => Submitted::$name]);

    expect(fn () => app(GuardedStateTransition::class)->transition($document, Registered::class, $secretariat))
        ->toThrow(TransitionNotFound::class);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'workflow.transition.invalid',
        'auditable_id' => $document->getKey(),
    ]);
});
