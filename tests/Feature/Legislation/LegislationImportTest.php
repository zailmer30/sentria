<?php

use App\Enums\DocumentOrigin;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Jobs\Documents\ProcessDocumentVersionJob;
use App\Models\Document;
use App\Models\LegislationImportBatch;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\User;
use App\States\Document\Archive;
use Database\Seeders\PermissionMatrixSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        PermissionMatrixSeeder::class,
    ]);

    Storage::fake('local');
    Queue::fake();
});

function importActor(UserRole $role, string $suffix = 'import'): User
{
    return User::factory()->create([
        'email' => "{$role->value}-{$suffix}@sentria.test",
        'password' => Hash::make('password'),
        'is_active' => true,
    ])->assignRole($role->value);
}

function importCsv(string $name, string $contents): UploadedFile
{
    $path = sys_get_temp_dir().'/'.uniqid('legcsv', true).'.csv';
    file_put_contents($path, $contents);

    return new UploadedFile($path, $name, 'text/csv', UPLOAD_ERR_OK, true);
}

/**
 * @param  array<string, string>  $files
 */
function importZip(array $files): UploadedFile
{
    $path = sys_get_temp_dir().'/'.uniqid('legzip', true).'.zip';
    $zip = new ZipArchive;
    expect($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    foreach ($files as $name => $contents) {
        $zip->addFromString($name, $contents);
    }

    $zip->close();

    return new UploadedFile($path, 'files.zip', 'application/zip', UPLOAD_ERR_OK, true);
}

function ordinanceCsv(string $number = '012', int $year = 2019, string $title = 'Scholarship Ordinance', string $status = 'enacted'): string
{
    return implode("\n", [
        'ordinance_number,series_year,title,status,purpose,enacted_on',
        "{$number},{$year},{$title},{$status},A scholarship program,{$year}-03-12",
        '',
    ]);
}

function resolutionCsv(string $number = '045', int $year = 2020, string $title = 'Commendation Resolution'): string
{
    return implode("\n", [
        'resolution_number,series_year,title,status,adopted_on',
        "{$number},{$year},{$title},adopted,{$year}-06-01",
        '',
    ]);
}

it('lets the secretariat preview and commit a matched historical ordinance', function (): void {
    $secretariat = importActor(UserRole::Secretariat);

    $this->actingAs($secretariat)
        ->post(route('ordinances.import.store'), [
            'csv' => importCsv('ordinances.csv', ordinanceCsv()),
            'zip' => importZip(['Ord. No. 12 s. 2019.pdf' => "%PDF-1.4\n%%EOF"]),
        ])
        ->assertRedirect();

    $batch = LegislationImportBatch::query()->firstOrFail();

    $this->actingAs($secretariat)
        ->get(route('ordinances.import.show', $batch))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Legislation/Import')
            ->where('kind', 'ordinance')
            ->where('preview.counts.matched', 1)
            ->where('preview.counts.unmatched_rows', 0)
            ->where('preview.matched.0.number', '012')
            ->where('preview.matched.0.filename', 'Ord. No. 12 s. 2019.pdf')
        );

    $this->actingAs($secretariat)
        ->post(route('ordinances.import.commit', $batch))
        ->assertRedirect(route('ordinances.import.show', $batch));

    $ordinance = Ordinance::query()->where('ordinance_number', '012')->firstOrFail();

    expect($ordinance->title)->toBe('Scholarship Ordinance')
        ->and($ordinance->status)->toBe('enacted')
        ->and($ordinance->imported_at)->not->toBeNull()
        ->and($ordinance->signedCopyIsAttached())->toBeTrue();

    $document = $ordinance->document()->firstOrFail();

    expect($document->document_type)->toBe(DocumentType::Ordinance)
        ->and($document->origin)->toBe(DocumentOrigin::Archive->value)
        ->and($document->status)->toBeInstanceOf(Archive::class)
        ->and($document->is_public)->toBeFalse()
        ->and($document->versions()->count())->toBe(1);

    Queue::assertPushed(ProcessDocumentVersionJob::class);
});

it('commits matched pairs and reports unmatched rows and files', function (): void {
    $secretariat = importActor(UserRole::Secretariat, 'partial');
    $csv = implode("\n", [
        'ordinance_number,series_year,title,status',
        '012,2019,Scholarship Ordinance,enacted',
        '013,2019,Missing File Ordinance,enacted',
        '',
    ]);

    $this->actingAs($secretariat)
        ->post(route('ordinances.import.store'), [
            'csv' => importCsv('ordinances.csv', $csv),
            'zip' => importZip([
                'Ord. No. 12 s. 2019.pdf' => "%PDF-1.4\n%%EOF",
                'IMG_0042.pdf' => "%PDF-1.4\n%%EOF",
            ]),
        ])
        ->assertRedirect();

    $batch = LegislationImportBatch::query()->firstOrFail();

    $this->actingAs($secretariat)
        ->get(route('ordinances.import.show', $batch))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('preview.counts.matched', 1)
            ->where('preview.counts.unmatched_rows', 1)
            ->where('preview.counts.unmatched_files', 1)
        );

    $this->actingAs($secretariat)
        ->post(route('ordinances.import.commit', $batch))
        ->assertRedirect();

    expect(Ordinance::query()->count())->toBe(1)
        ->and(Ordinance::query()->where('ordinance_number', '012')->exists())->toBeTrue()
        ->and(Ordinance::query()->where('ordinance_number', '013')->exists())->toBeFalse();
});

it('skips numbers already on the register', function (): void {
    $secretariat = importActor(UserRole::Secretariat, 'dup');
    $document = Document::factory()->ofType(DocumentType::Ordinance)->create();
    Ordinance::factory()->create([
        'document_id' => $document->getKey(),
        'ordinance_number' => 'ORD-2019-012',
        'series_year' => 2019,
        'title' => 'Existing',
        'status' => 'enacted',
    ]);

    $this->actingAs($secretariat)
        ->post(route('ordinances.import.store'), [
            'csv' => importCsv('ordinances.csv', ordinanceCsv()),
            'zip' => importZip(['ORD-2019-012.pdf' => "%PDF-1.4\n%%EOF"]),
        ])
        ->assertRedirect();

    $batch = LegislationImportBatch::query()->firstOrFail();

    $this->actingAs($secretariat)
        ->get(route('ordinances.import.show', $batch))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('preview.counts.matched', 0)
            ->where('preview.counts.duplicates', 1)
        );

    $this->actingAs($secretariat)
        ->post(route('ordinances.import.commit', $batch))
        ->assertRedirect();

    expect(Ordinance::query()->count())->toBe(1)
        ->and(Ordinance::query()->where('title', 'Scholarship Ordinance')->exists())->toBeFalse();
});

it('imports historical resolutions the same way', function (): void {
    $secretariat = importActor(UserRole::Secretariat, 'res');

    $this->actingAs($secretariat)
        ->post(route('resolutions.import.store'), [
            'csv' => importCsv('resolutions.csv', resolutionCsv()),
            'zip' => importZip(['Resolution No. 45 s. 2020.pdf' => "%PDF-1.4\n%%EOF"]),
        ])
        ->assertRedirect();

    $batch = LegislationImportBatch::query()->firstOrFail();

    $this->actingAs($secretariat)
        ->post(route('resolutions.import.commit', $batch))
        ->assertRedirect();

    $resolution = Resolution::query()->where('resolution_number', '045')->firstOrFail();

    expect($resolution->status)->toBe('adopted')
        ->and($resolution->imported_at)->not->toBeNull()
        ->and($resolution->document?->document_type)->toBe(DocumentType::Resolution)
        ->and($resolution->document?->origin)->toBe(DocumentOrigin::Archive->value);
});

it('denies a board member from importing legislation', function (): void {
    $member = importActor(UserRole::BoardMember);

    $this->actingAs($member)
        ->get(route('ordinances.import.create'))
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('ordinances.import.store'), [
            'csv' => importCsv('ordinances.csv', ordinanceCsv()),
            'zip' => importZip(['Ord. No. 12 s. 2019.pdf' => "%PDF-1.4\n%%EOF"]),
        ])
        ->assertForbidden();
});

it('downloads a csv template', function (): void {
    $secretariat = importActor(UserRole::Secretariat, 'template');

    $this->actingAs($secretariat)
        ->get(route('ordinances.import.template'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertSee('ordinance_number', false);
});

it('shares import size limits with the form', function (): void {
    $secretariat = importActor(UserRole::Secretariat, 'limits');

    $this->actingAs($secretariat)
        ->get(route('ordinances.import.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Legislation/Import')
            ->where('maxCsvKb', 2048)
            ->where('maxZipKb', 204800)
        );
});

it('returns to the import form when the post is larger than php allows', function (): void {
    $secretariat = importActor(UserRole::Secretariat, 'toolarge');

    $this->actingAs($secretariat)
        ->from(route('ordinances.import.create'))
        ->withServerVariables(['CONTENT_LENGTH' => 512 * 1024 * 1024])
        ->post(route('ordinances.import.store'))
        ->assertRedirect(route('ordinances.import.create'))
        ->assertSessionHas('error', 'legislation.import_too_large');
});
