<?php

use App\Enums\UserRole;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Services\Sessions\AgendaService;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function agendaEditActor(UserRole $role, string $suffix): User
{
    return User::factory()->create([
        'email' => "agenda-edit-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('lets the secretariat edit an agenda item title and description', function (): void {
    $secretariat = agendaEditActor(UserRole::Secretariat, 'save');
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-09-01 09:00:00',
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);
    $invocation = $session->agendaItems()->where('category', 'convocation')->firstOrFail();

    $this->actingAs($secretariat)
        ->put(route('sessions.agenda.update', [$session, $invocation]), [
            'title' => 'Invocation',
            'description' => "Opening Prayer\nLed by Hon. Cruz",
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'sessions.agenda_item_updated');

    $invocation->refresh();

    expect($invocation->title)->toBe('Invocation')
        ->and($invocation->description)->toBe("Opening Prayer\nLed by Hon. Cruz");
});

it('clears a description when the note is emptied', function (): void {
    $secretariat = agendaEditActor(UserRole::Secretariat, 'clear');
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-09-08 09:00:00',
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);
    $invocation = $session->agendaItems()->where('category', 'convocation')->firstOrFail();

    $this->actingAs($secretariat)
        ->put(route('sessions.agenda.update', [$session, $invocation]), [
            'title' => 'Invocation',
            'description' => null,
        ])
        ->assertRedirect();

    expect($invocation->refresh()->description)->toBeNull();
});

it('refuses an agenda edit from a member', function (): void {
    $member = agendaEditActor(UserRole::BoardMember, 'member');
    $session = LegislativeSession::factory()->create([
        'type' => 'regular',
        'scheduled_start_at' => '2026-09-15 09:00:00',
    ]);
    app(AgendaService::class)->prepareStandardTemplate($session);
    $invocation = $session->agendaItems()->where('category', 'convocation')->firstOrFail();

    $this->actingAs($member)
        ->put(route('sessions.agenda.update', [$session, $invocation]), [
            'title' => 'Changed',
            'description' => 'Should not save',
        ])
        ->assertForbidden();

    expect($invocation->refresh()->title)->toBe('Invocation');
});
