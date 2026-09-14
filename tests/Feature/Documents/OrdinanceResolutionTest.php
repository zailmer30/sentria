<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\User;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);
});

function legislationActor(UserRole $role): User
{
    return User::factory()->create([
        'email' => "{$role->value}-leg@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

it('allows secretariat to crud ordinances and resolutions', function (): void {
    $secretariat = legislationActor(UserRole::Secretariat);

    $ordinanceDocument = Document::factory()->ofType(DocumentType::Ordinance)->create();
    $resolutionDocument = Document::factory()->ofType(DocumentType::Resolution)->create();

    $this->actingAs($secretariat)
        ->post(route('ordinances.store'), [
            'document_id' => $ordinanceDocument->getKey(),
            'ordinance_number' => 'ORD-2026-001',
            'series_year' => 2026,
            'title' => 'Provincial Scholarship Ordinance',
            'status' => 'enacted',
        ])
        ->assertRedirect();

    $ordinance = Ordinance::query()->where('ordinance_number', 'ORD-2026-001')->firstOrFail();

    $this->actingAs($secretariat)
        ->get(route('ordinances.show', $ordinance))
        ->assertOk();

    $this->actingAs($secretariat)
        ->put(route('ordinances.update', $ordinance), [
            'document_id' => $ordinanceDocument->getKey(),
            'ordinance_number' => 'ORD-2026-001',
            'series_year' => 2026,
            'title' => 'Provincial Scholarship Ordinance (Amended Title)',
            'status' => 'enacted',
        ])
        ->assertRedirect(route('ordinances.show', $ordinance));

    expect($ordinance->fresh()->title)->toBe('Provincial Scholarship Ordinance (Amended Title)');

    $this->actingAs($secretariat)
        ->get(route('resolutions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Legislation/Resolutions/Form')
            ->where('resolution', null)
            ->has('documents')
        );

    $this->actingAs($secretariat)
        ->post(route('resolutions.store'), [
            'document_id' => $resolutionDocument->getKey(),
            'resolution_number' => 'RES-2026-001',
            'series_year' => 2026,
            'title' => 'Commendation Resolution',
            'status' => 'adopted',
        ])
        ->assertRedirect();

    $resolution = Resolution::query()->where('resolution_number', 'RES-2026-001')->firstOrFail();

    $this->actingAs($secretariat)
        ->get(route('resolutions.show', $resolution))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Legislation/Resolutions/Show')
            ->where('resolution.title', 'Commendation Resolution')
            ->where('publication', null)
            ->has('history')
            ->where('can.update', true)
            ->has('can.createPublication')
        );
});

it('denies board member from managing legislation records', function (): void {
    $member = legislationActor(UserRole::BoardMember);

    expect($member->can('create', Ordinance::class))->toBeFalse()
        ->and($member->can('create', Resolution::class))->toBeFalse();
});

it('offers only matching document types when recording legislation', function (): void {
    $secretariat = legislationActor(UserRole::Secretariat);

    $ordinanceDocument = Document::factory()->ofType(DocumentType::Ordinance)->create();
    $proposedOrdinance = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create();
    $resolutionDocument = Document::factory()->ofType(DocumentType::Resolution)->create();
    $proposedResolution = Document::factory()->ofType(DocumentType::ProposedResolution)->create();
    $minutes = Document::factory()->ofType(DocumentType::Minutes)->create();

    $this->actingAs($secretariat)
        ->get(route('ordinances.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Legislation/Ordinances/Form')
            ->has('documents', 2)
            ->where('documents', function ($documents) use ($ordinanceDocument, $proposedOrdinance, $resolutionDocument, $minutes): bool {
                $ids = collect($documents)->pluck('id')->all();

                return in_array($ordinanceDocument->getKey(), $ids, true)
                    && in_array($proposedOrdinance->getKey(), $ids, true)
                    && ! in_array($resolutionDocument->getKey(), $ids, true)
                    && ! in_array($minutes->getKey(), $ids, true);
            })
        );

    $this->actingAs($secretariat)
        ->get(route('resolutions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Legislation/Resolutions/Form')
            ->has('documents', 2)
            ->where('documents', function ($documents) use ($resolutionDocument, $proposedResolution, $ordinanceDocument, $minutes): bool {
                $ids = collect($documents)->pluck('id')->all();

                return in_array($resolutionDocument->getKey(), $ids, true)
                    && in_array($proposedResolution->getKey(), $ids, true)
                    && ! in_array($ordinanceDocument->getKey(), $ids, true)
                    && ! in_array($minutes->getKey(), $ids, true);
            })
        );

    $this->actingAs($secretariat)
        ->post(route('ordinances.store'), [
            'document_id' => $resolutionDocument->getKey(),
            'ordinance_number' => 'ORD-2026-099',
            'series_year' => 2026,
            'title' => 'Wrong type ordinance',
            'status' => 'draft',
        ])
        ->assertSessionHasErrors('document_id');

    $this->actingAs($secretariat)
        ->post(route('resolutions.store'), [
            'document_id' => $ordinanceDocument->getKey(),
            'resolution_number' => 'RES-2026-099',
            'series_year' => 2026,
            'title' => 'Wrong type resolution',
            'status' => 'draft',
        ])
        ->assertSessionHasErrors('document_id');
});
