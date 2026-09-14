<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Ordinance;
use App\Models\Publication;
use App\Models\Resolution;
use App\Models\User;
use App\Services\Documents\DocumentSearchIndexer;
use App\Services\Documents\DocumentTextStore;
use App\Services\Portal\PublicPortalSearchService;
use App\States\Publication\InternalDocument;
use App\States\Publication\Published;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);

    Storage::fake('local');
});

function signedCopyActor(UserRole $role, string $suffix = 'signed'): User
{
    return User::factory()->create([
        'email' => "{$role->value}-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

function signedPdf(string $name = 'signed.pdf', string $contents = "%PDF-1.4\n%%EOF"): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents)->mimeType('application/pdf');
}

function attachSignedCopy(
    User $actor,
    Ordinance|Resolution $record,
    string $name = 'signed.pdf',
    string $contents = "%PDF-1.4\n%%EOF",
): Ordinance|Resolution {
    $route = $record instanceof Ordinance
        ? route('ordinances.signed-copy.store', $record)
        : route('resolutions.signed-copy.store', $record);
    $show = $record instanceof Ordinance
        ? route('ordinances.show', $record)
        : route('resolutions.show', $record);

    test()->actingAs($actor)
        ->from($show)
        ->post($route, ['file' => signedPdf($name, $contents)])
        ->assertRedirect($show);

    return $record->fresh();
}

it('uploads a pdf signed copy on a draft ordinance without creating a document version', function (): void {
    $secretariat = signedCopyActor(UserRole::Secretariat);
    $document = Document::factory()->ofType(DocumentType::Ordinance)->create();
    $ordinance = Ordinance::factory()->create([
        'document_id' => $document->getKey(),
        'status' => 'draft',
        'enacted_on' => null,
        'approved_on' => null,
        'effectivity_date' => null,
        'publication_date' => null,
    ]);
    $versionCount = $document->versions()->count();

    $ordinance = attachSignedCopy($secretariat, $ordinance, 'ord-001-signed.pdf', "%PDF-1.4\nwet-signed\n%%EOF");

    expect($ordinance->signed_copy_filename)->toBe('ord-001-signed.pdf')
        ->and($ordinance->signed_copy_mime)->toBe('application/pdf')
        ->and($ordinance->hasSignedCopy())->toBeTrue()
        ->and($ordinance->signed_copy_path)->toStartWith('legislation/ordinances/')
        ->and($document->fresh()->versions()->count())->toBe($versionCount);

    Storage::disk('local')->assertExists($ordinance->signed_copy_path);

    expect(AuditLog::query()->where('event', 'legislation.signed_copy.upload')->exists())->toBeTrue();

    $this->actingAs($secretariat)
        ->get(route('ordinances.show', $ordinance))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Legislation/Ordinances/Show')
            ->where('ordinance.signed_copy.filename', 'ord-001-signed.pdf')
            ->where('ordinance.signed_copy.available', true)
            ->missing('ordinance.signed_copy.path')
            ->missing('ordinance.signed_copy.disk')
        );
});

it('rejects a non-pdf signed copy', function (): void {
    $secretariat = signedCopyActor(UserRole::Secretariat, 'nonpdf');
    $ordinance = Ordinance::factory()->create(['status' => 'draft']);

    $this->actingAs($secretariat)
        ->from(route('ordinances.show', $ordinance))
        ->post(route('ordinances.signed-copy.store', $ordinance), [
            'file' => UploadedFile::fake()->createWithContent('notes.txt', 'not a pdf')->mimeType('text/plain'),
        ])
        ->assertSessionHasErrors('file');

    expect($ordinance->fresh()->signedCopyIsAttached())->toBeFalse()
        ->and($ordinance->fresh()->document?->versions()->count())->toBe(0);
});

it('replaces the signed copy in place and removes the previous file', function (): void {
    $secretariat = signedCopyActor(UserRole::Secretariat, 'replace');
    $ordinance = Ordinance::factory()->create(['status' => 'pending']);
    $ordinance = attachSignedCopy($secretariat, $ordinance, 'first.pdf', "%PDF-1.4\nfirst\n%%EOF");
    $previousPath = $ordinance->signed_copy_path;

    $ordinance = attachSignedCopy($secretariat, $ordinance, 'second.pdf', "%PDF-1.4\nsecond\n%%EOF");

    expect($ordinance->signed_copy_filename)->toBe('second.pdf')
        ->and($ordinance->signed_copy_path)->not->toBe($previousPath)
        ->and($ordinance->document?->versions()->count())->toBe(0);

    Storage::disk('local')->assertMissing($previousPath);
    Storage::disk('local')->assertExists($ordinance->signed_copy_path);

    expect(AuditLog::query()->where('event', 'legislation.signed_copy.replace')->exists())->toBeTrue();
});

it('removes the signed copy', function (): void {
    $secretariat = signedCopyActor(UserRole::Secretariat, 'remove');
    $resolution = Resolution::factory()->create(['status' => 'draft']);
    $resolution = attachSignedCopy($secretariat, $resolution, 'res-signed.pdf');
    $path = $resolution->signed_copy_path;

    $this->actingAs($secretariat)
        ->from(route('resolutions.show', $resolution))
        ->delete(route('resolutions.signed-copy.destroy', $resolution))
        ->assertRedirect(route('resolutions.show', $resolution));

    $resolution->refresh();

    expect($resolution->signedCopyIsAttached())->toBeFalse()
        ->and($resolution->signed_copy_filename)->toBeNull();

    Storage::disk('local')->assertMissing($path);
    expect(AuditLog::query()->where('event', 'legislation.signed_copy.remove')->exists())->toBeTrue();
});

it('lets staff preview the signed copy inline', function (): void {
    $secretariat = signedCopyActor(UserRole::Secretariat, 'preview');
    $ordinance = Ordinance::factory()->create();
    $ordinance = attachSignedCopy($secretariat, $ordinance, 'preview.pdf', "%PDF-1.4\npreview-body\n%%EOF");

    $response = $this->actingAs($secretariat)
        ->get(route('ordinances.signed-copy.preview', $ordinance));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store')
        ->and($response->headers->get('Content-Disposition'))->toContain('inline')
        ->and(AuditLog::query()->where('event', 'legislation.signed_copy.preview')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'legislation.signed_copy.download')->exists())->toBeFalse();
});

it('lets a board member preview but not upload a signed copy', function (): void {
    $member = signedCopyActor(UserRole::BoardMember, 'viewonly');
    $secretariat = signedCopyActor(UserRole::Secretariat, 'owner');
    $ordinance = Ordinance::factory()->create();
    $ordinance = attachSignedCopy($secretariat, $ordinance);

    $this->actingAs($member)
        ->get(route('ordinances.signed-copy.preview', $ordinance))
        ->assertOk();

    $this->actingAs($member)
        ->post(route('ordinances.signed-copy.store', $ordinance), [
            'file' => signedPdf('member.pdf'),
        ])
        ->assertForbidden();
});

it('returns 404 not 403 for an unpublished public signed-copy download', function (): void {
    $secretariat = signedCopyActor(UserRole::Secretariat, 'unpublished');
    $document = Document::factory()->ofType(DocumentType::Ordinance)->create([
        'is_public' => false,
        'published_at' => null,
    ]);
    $ordinance = Ordinance::factory()->create([
        'document_id' => $document->getKey(),
        'status' => 'enacted',
    ]);
    $ordinance = attachSignedCopy($secretariat, $ordinance, 'secret.pdf');

    $publication = Publication::factory()->create([
        'document_id' => $document->getKey(),
        'status' => InternalDocument::$name,
        'published_at' => null,
    ]);

    $this->get(route('portal.documents.signed-copy.download', $publication->public_slug))
        ->assertNotFound();

    $this->get(route('portal.ordinances.signed-copy.download', $publication->public_slug))
        ->assertNotFound();

    $this->get(route('portal.ordinances.signed-copy.download', $ordinance->getKey()))
        ->assertNotFound();

    $this->get(route('portal.ordinances.show', $publication->public_slug))
        ->assertNotFound();
});

it('serves the published signed copy and does not fall back to the document version', function (): void {
    $secretariat = signedCopyActor(UserRole::Secretariat, 'public');
    $document = Document::factory()->ofType(DocumentType::Ordinance)->published()->create();
    $deskPath = 'documents/desk/'.$document->getKey().'.pdf';
    Storage::disk('local')->put($deskPath, "%PDF-1.4\nDESK-COPY-ONLY\n%%EOF");

    $version = DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
        'is_current' => true,
        'disk' => 'local',
        'file_path' => $deskPath,
        'original_filename' => 'desk-copy.pdf',
        'mime_type' => 'application/pdf',
        'scan_status' => 'skipped',
    ]);

    $ordinance = Ordinance::factory()->create([
        'document_id' => $document->getKey(),
        'status' => 'enacted',
    ]);

    $publication = Publication::factory()->published()->create([
        'document_id' => $document->getKey(),
        'status' => Published::$name,
    ]);

    $this->get(route('portal.documents.signed-copy.download', $publication->public_slug))
        ->assertNotFound();

    $this->get(route('documents.versions.download', [$document, $version]))
        ->assertRedirect();

    $ordinance = attachSignedCopy(
        $secretariat,
        $ordinance,
        'wet-signed.pdf',
        "%PDF-1.4\nWET-SIGNED-COPY\n%%EOF",
    );

    $download = $this->get(route('portal.documents.signed-copy.download', $publication->public_slug));
    $download->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect($download->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain('wet-signed.pdf')
        ->and($download->streamedContent())->toContain('WET-SIGNED-COPY')
        ->and($download->streamedContent())->not->toContain('DESK-COPY-ONLY');

    $this->get(route('portal.ordinances.signed-copy.download', $publication->public_slug))
        ->assertOk();

    $this->get(route('portal.ordinances.signed-copy.preview', $ordinance->getKey()))
        ->assertOk();

    $this->get(route('portal.documents.show', $publication->public_slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/DocumentShow')
            ->where('publication.ordinance.has_signed_copy', true)
            ->where('publication.ordinance.signed_copy_filename', 'wet-signed.pdf')
        );
});

it('keeps portal search on the published document index after a signed copy is attached', function (): void {
    $secretariat = signedCopyActor(UserRole::Secretariat, 'search');
    $document = Document::factory()->ofType(DocumentType::Ordinance)->published()->create([
        'title' => 'Generic Provincial Measure',
        'abstract' => 'A published measure without the rare keyword in metadata.',
    ]);

    $version = DocumentVersion::factory()->create([
        'document_id' => $document->getKey(),
        'is_current' => true,
        'ocr_status' => 'completed',
    ]);

    $body = "SECTION 1. Short Title.\nThis ordinance establishes the ZXYQBODYONLYKEYWORD fund for rural clinics.";
    app(DocumentTextStore::class)->put($version, $body);
    app(DocumentSearchIndexer::class)->upsert($version->refresh(), $body);

    $ordinance = Ordinance::factory()->create([
        'document_id' => $document->getKey(),
        'status' => 'enacted',
    ]);

    Publication::factory()->published()->create([
        'document_id' => $document->getKey(),
        'title' => 'Generic Provincial Measure',
        'summary' => 'Published summary without the rare token.',
        'status' => Published::$name,
    ]);

    $versionCount = $document->versions()->count();
    attachSignedCopy(
        $secretariat,
        $ordinance,
        'signed-search.pdf',
        "%PDF-1.4\nZXYQSIGNEDONLYTOKEN appears only in the wet-signed file.\n%%EOF",
    );

    expect($document->fresh()->versions()->count())->toBe($versionCount);

    $results = app(PublicPortalSearchService::class)->search([
        'keyword' => 'ZXYQBODYONLYKEYWORD',
    ]);

    expect($results->total())->toBeGreaterThan(0)
        ->and(collect($results->items())->pluck('document_id'))->toContain($document->getKey());

    $signedOnly = app(PublicPortalSearchService::class)->search([
        'keyword' => 'ZXYQSIGNEDONLYTOKEN',
    ]);

    expect(collect($signedOnly->items())->pluck('document_id'))->not->toContain($document->getKey());
});
