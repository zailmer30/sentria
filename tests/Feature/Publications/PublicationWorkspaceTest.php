<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Ordinance;
use App\Models\Publication;
use App\Models\User;
use App\States\Publication\InternalDocument;
use App\States\Publication\SecretariatReview;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
        SystemSettingSeeder::class,
    ]);
});

function publicationRegisterActor(): User
{
    return User::factory()->create([
        'email' => 'secretariat-publications@sentria.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole(UserRole::Secretariat->value);
}

it('renders the publication register with summary figures and rows', function (): void {
    $actor = publicationRegisterActor();

    Publication::factory()->create([
        'title' => 'Sample Ordinance',
        'summary' => 'A measure moving through publication review.',
        'status' => SecretariatReview::$name,
    ]);
    Publication::factory()->create([
        'title' => 'Draft Measure',
        'status' => InternalDocument::$name,
    ]);

    $this->actingAs($actor)
        ->get(route('publications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Publications/Index')
            ->has('publications.data', 2)
            ->where('summary.matching', 2)
            ->where('summary.drafting', 1)
            ->where('summary.review', 1)
            ->where('summary.published', 0)
            ->where('filters.scope', 'matching')
            ->where('can.create', true)
            ->missing('publication')
            ->has('filters')
            ->has('statuses'));
});

it('filters the publication register by search', function (): void {
    $actor = publicationRegisterActor();

    Publication::factory()->create(['title' => 'Coastal Protection Ordinance']);
    Publication::factory()->create(['title' => 'Budget Resolution']);

    $this->actingAs($actor)
        ->get(route('publications.index', ['search' => 'Coastal']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Publications/Index')
            ->has('publications.data', 1)
            ->where('publications.data.0.title', 'Coastal Protection Ordinance')
            ->where('filters.search', 'Coastal')
            ->where('summary.matching', 1));
});

it('scopes the publication register to the review card', function (): void {
    $actor = publicationRegisterActor();

    Publication::factory()->create([
        'title' => 'In Review',
        'status' => SecretariatReview::$name,
    ]);
    Publication::factory()->create([
        'title' => 'Still Drafting',
        'status' => InternalDocument::$name,
    ]);

    $this->actingAs($actor)
        ->get(route('publications.index', ['scope' => 'review']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Publications/Index')
            ->has('publications.data', 1)
            ->where('publications.data.0.title', 'In Review')
            ->where('filters.scope', 'review')
            ->where('summary.matching', 2)
            ->where('summary.review', 1));
});

it('opens a publication record as its own page', function (): void {
    $actor = publicationRegisterActor();

    $publication = Publication::factory()->create(['title' => 'Open Data Ordinance']);

    $this->actingAs($actor)
        ->get(route('publications.show', $publication))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Publications/Show')
            ->where('publication.title', 'Open Data Ordinance')
            ->has('publication.workflow', 4)
            ->has('publication.transitions')
            ->has('publication.activity')
            ->has('publication.release_targets', 3)
            ->where('can.transition', true)
            ->missing('publications'));
});

it('opens the start-publication form with documents that have no live record', function (): void {
    $actor = publicationRegisterActor();

    $available = Document::factory()->ofType(DocumentType::Communication)->create(['title' => 'Ready to Publish']);
    $taken = Document::factory()->ofType(DocumentType::Communication)->create(['title' => 'Already Public']);
    Publication::factory()->create([
        'document_id' => $taken->getKey(),
        'title' => 'Already Public',
    ]);

    $this->actingAs($actor)
        ->get(route('publications.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Publications/Create')
            ->has('documents', 1)
            ->where('documents.0.id', $available->getKey())
            ->where('documents.0.title', 'Ready to Publish'));
});

it('leaves measures without a legislation record out of the start-publication form', function (): void {
    $actor = publicationRegisterActor();

    Document::factory()->ofType(DocumentType::ProposedOrdinance)->create(['title' => 'Unnumbered Measure']);
    $numbered = Ordinance::factory()->create()->document;

    $this->actingAs($actor)
        ->get(route('publications.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Publications/Create')
            ->has('documents', 1)
            ->where('documents.0.id', $numbered?->getKey()));
});

it('starts a publication record from a source document', function (): void {
    $actor = publicationRegisterActor();
    $document = Document::factory()->ofType(DocumentType::Communication)->create([
        'title' => 'Coastal Setback Advisory',
        'abstract' => 'Limits construction along the shoreline.',
    ]);

    $this->actingAs($actor)
        ->post(route('publications.store'), [
            'document_id' => $document->getKey(),
            'title' => 'Coastal Setback Advisory',
            'summary' => 'Limits construction along the shoreline.',
        ])
        ->assertRedirect();

    $publication = Publication::query()->where('document_id', $document->getKey())->first();

    expect($publication)->not->toBeNull()
        ->and($publication?->title)->toBe('Coastal Setback Advisory');
});

it('refuses to start a publication for a measure with no legislation record', function (): void {
    $actor = publicationRegisterActor();
    $document = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create([
        'title' => 'Coastal Setback Ordinance',
    ]);

    $this->actingAs($actor)
        ->post(route('publications.store'), [
            'document_id' => $document->getKey(),
            'title' => 'Coastal Setback Ordinance',
        ])
        ->assertSessionHasErrors('document_id');

    $this->actingAs($actor)
        ->post(route('documents.publication.create', $document))
        ->assertRedirect()
        ->assertSessionHas('error', 'publications.requires_legislation_record');

    expect(Publication::query()->where('document_id', $document->getKey())->exists())->toBeFalse();
});

it('starts a publication for a measure once its ordinance is recorded', function (): void {
    $actor = publicationRegisterActor();
    $document = Ordinance::factory()->create()->document;

    $this->actingAs($actor)
        ->post(route('documents.publication.create', $document))
        ->assertRedirect();

    expect(Publication::query()->where('document_id', $document?->getKey())->exists())->toBeTrue();
});

it('hides the start-publication action on a measure document page', function (): void {
    $actor = publicationRegisterActor();
    $measure = Document::factory()->ofType(DocumentType::ProposedOrdinance)->create();
    $filing = Document::factory()->ofType(DocumentType::Communication)->create();

    $this->actingAs($actor)
        ->get(route('documents.show', $measure))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.createPublication', false));

    $this->actingAs($actor)
        ->get(route('documents.show', $filing))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.createPublication', true));
});
