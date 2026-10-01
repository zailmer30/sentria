<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\Confidentiality;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Enums\VoteChoice;
use App\Jobs\Documents\ProcessDocumentVersionJob;
use App\Models\AgendaItem;
use App\Models\Committee;
use App\Models\CommitteeReferral;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\LegislativeSession;
use App\Models\Minutes;
use App\Models\Motion;
use App\Models\Ordinance;
use App\Models\PrivateNote;
use App\Models\Publication;
use App\Models\Resolution;
use App\Models\SessionAttendance;
use App\Models\Transcript;
use App\Models\TranscriptSegmentEdit;
use App\Models\User;
use App\Models\Vote;
use App\Services\AI\LegislativeMinutesGenerator;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentTextStore;
use App\States\Minutes\AiDraft;
use Barryvdh\DomPDF\PDF;
use Carbon\CarbonImmutable;
use Database\Seeders\Marketing\MarketingDemoContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fictional, camera-ready demo data for the marketing film: one live sitting
 * for the floor, one adjourned sitting for the transcript and minutes, and a
 * few published measures for the portal. Every measure has a real PDF.
 *
 * Refuses to run unless the database name contains "marketing", so it cannot
 * overwrite the development database by accident.
 */
class MarketingDemoSeeder extends Seeder
{
    /** @var Collection<string, User> */
    private Collection $people;

    /** @var list<string> */
    private array $versionIds = [];

    private int $year;

    public function run(): void
    {
        $this->assertMarketingDatabase();

        config([
            'sentria.organization.name' => MarketingDemoContent::ORGANIZATION,
            'sentria.organization.short_name' => MarketingDemoContent::SHORT_NAME,
            'sentria.organization.locality' => MarketingDemoContent::LOCALITY,
        ]);

        $this->call([
            RolePermissionSeeder::class,
            PermissionMatrixSeeder::class,
            UserSeeder::class,
            SystemSettingSeeder::class,
        ]);

        $this->year = (int) now()->year;
        $this->people = $this->seedMembers();

        $this->call(CommitteeSeeder::class);

        $documents = $this->seedMeasures();
        $this->seedPortal($documents);
        $this->seedAdjournedSession($documents);
        $this->seedLiveSession($documents);
        $this->seedAuditTrail();

        foreach ($this->versionIds as $versionId) {
            ProcessDocumentVersionJob::dispatch($versionId);
        }
    }

    private function assertMarketingDatabase(): void
    {
        $database = (string) DB::connection()->getDatabaseName();

        if (app()->runningUnitTests() || str_contains($database, 'marketing')) {
            return;
        }

        throw new RuntimeException(sprintf(
            'MarketingDemoSeeder only runs against a database whose name contains "marketing". Current database: "%s".',
            $database,
        ));
    }

    /**
     * @return Collection<string, User>
     */
    private function seedMembers(): Collection
    {
        $people = collect([
            'presiding' => User::query()->where('email', 'presiding@sentria.test')->firstOrFail(),
            'secretariat' => User::query()->where('email', 'secretariat@sentria.test')->firstOrFail(),
            'dizon' => User::query()->where('email', 'member@sentria.test')->firstOrFail(),
            'manalo' => User::query()->where('email', 'chair@sentria.test')->firstOrFail(),
            'salazar' => User::query()->where('email', 'committee@sentria.test')->firstOrFail(),
        ]);

        $number = 101;

        foreach (MarketingDemoContent::members() as $key => [$first, $middle, $last, $position, $district]) {
            $user = User::query()->updateOrCreate(
                ['email' => "{$key}@sentria.test"],
                [
                    'first_name' => $first,
                    'middle_name' => $middle,
                    'last_name' => $last,
                    'display_name' => "{$first} {$last}",
                    'honorific' => 'Hon.',
                    'position_title' => $position,
                    'district' => $district,
                    'employee_number' => sprintf('EMP-%04d', $number++),
                    'is_seated_member' => true,
                    'password' => Hash::make(UserSeeder::DEMO_PASSWORD),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'locale' => 'en',
                ],
            );

            $user->syncRoles([UserRole::BoardMember->value]);
            $people->put($key, $user);
        }

        return $people;
    }

    /**
     * @return Collection<string, Document>
     */
    private function seedMeasures(): Collection
    {
        $documents = collect();

        foreach (MarketingDemoContent::measures($this->year) as $key => $measure) {
            $published = $measure['status'] === 'public-publication';
            $committee = isset($measure['committee'])
                ? Committee::query()->where('name', $measure['committee'])->first()
                : null;

            $document = Document::factory()->create([
                'reference_number' => $measure['reference'],
                'title' => $measure['title'],
                'slug' => Str::slug(Str::limit($measure['title'], 80, '')).'-'.Str::lower(Str::random(6)),
                'abstract' => $measure['abstract'],
                'explanatory_note' => implode("\n\n", $measure['explanatory_note'] ?? []) ?: null,
                'enacting_clause' => $measure['enacting_clause'],
                'document_type' => $measure['type']->value,
                'status' => $measure['status'],
                'confidentiality' => $published ? Confidentiality::Public->value : Confidentiality::Internal->value,
                'origin' => 'member',
                'author_id' => $this->person($measure['author'])->getKey(),
                'committee_id' => $committee?->getKey(),
                'submitted_at' => now()->subWeeks(5),
                'registered_at' => now()->subWeeks(5)->addDay(),
                'registered_by' => $this->person('secretariat')->getKey(),
                'tags' => $measure['tags'],
                'is_public' => false,
            ]);

            $this->attachPdf($document, $measure);
            $documents->put($key, $document);

            if ($committee instanceof Committee) {
                CommitteeReferral::factory()->create([
                    'document_id' => $document->getKey(),
                    'committee_id' => $committee->getKey(),
                    'referred_by' => $this->person('secretariat')->getKey(),
                    'status' => 'in-review',
                    'referred_at' => now()->subDays(7),
                    'due_at' => now()->addDays(23),
                ]);
            }
        }

        return $documents;
    }

    /**
     * @param  array<string, mixed>  $measure
     */
    private function attachPdf(Document $document, array $measure): DocumentVersion
    {
        $enactedLine = match ($measure['type']) {
            DocumentType::Ordinance => 'ENACTED: '.now()->subMonths((int) ($measure['enacted_months_ago'] ?? 1))->format('F j, Y'),
            DocumentType::Resolution => 'ADOPTED: '.now()->subDays(7)->format('F j, Y'),
            default => null,
        };

        /** @var PDF $pdf */
        $pdf = app('dompdf.wrapper');
        $pdf->loadView('marketing.measure', [
            'measure' => $measure,
            'year' => $this->year,
            'organization' => MarketingDemoContent::ORGANIZATION,
            'locality' => MarketingDemoContent::LOCALITY,
            'author' => $this->honorific($measure['author']),
            'coAuthors' => array_map(fn (string $key): string => $this->honorific($key), $measure['co_authors']),
            'enactedLine' => $enactedLine,
            'secretary' => $this->person('secretariat')->display_name,
            'presidingOfficer' => 'Hon. '.$this->person('presiding')->display_name,
            'footer' => 'Sentria demonstration record. Fictional content.',
        ]);
        $pdf->setPaper('a4');

        $bytes = $pdf->output();
        $pageCount = (int) $pdf->getDomPDF()->getCanvas()->get_page_count();
        $hash = hash('sha256', $bytes);
        $filename = Str::slug($measure['number_label']).'.pdf';
        $path = sprintf('documents/%s/v1-%s.pdf', $document->getKey(), substr($hash, 0, 16));

        Storage::disk('local')->put($path, $bytes);

        $version = DocumentVersion::factory()->for($document)->create([
            'file_path' => $path,
            'original_filename' => $filename,
            'file_size' => strlen($bytes),
            'checksum_sha256' => $hash,
            'page_count' => max(1, $pageCount),
            'processing_status' => 'pending',
            'uploaded_by' => $this->person('secretariat')->getKey(),
        ]);

        app(DocumentTextStore::class)->put($version, $this->plainText($measure));
        $document->forceFill(['version_count' => 1])->save();
        $this->versionIds[] = $version->getKey();

        return $version;
    }

    /**
     * @param  array<string, mixed>  $measure
     */
    private function plainText(array $measure): string
    {
        $lines = [
            $measure['number_label'],
            Str::upper($measure['title']),
            ...($measure['explanatory_note'] ?? []),
            ...array_map(fn (string $line): string => 'WHEREAS, '.$line, $measure['whereas'] ?? []),
            $measure['enacting_clause'],
        ];

        foreach ($measure['sections'] as $section) {
            $lines[] = trim(($section['heading'] ?? '').' '.$section['body']);

            foreach ($section['items'] ?? [] as $item) {
                $lines[] = '- '.$item;
            }
        }

        return implode("\n\n", $lines);
    }

    /**
     * @param  Collection<string, Document>  $documents
     */
    private function seedPortal(Collection $documents): void
    {
        foreach (MarketingDemoContent::measures($this->year) as $key => $measure) {
            if ($measure['status'] !== 'public-publication') {
                continue;
            }

            $document = $documents->get($key);

            if ($measure['type'] === DocumentType::Ordinance) {
                $enacted = now()->subMonths((int) $measure['enacted_months_ago']);

                Ordinance::factory()->create([
                    'document_id' => $document->getKey(),
                    'ordinance_number' => $measure['ordinance_number'],
                    'series_year' => $this->year,
                    'title' => $measure['title'],
                    'purpose' => $measure['abstract'],
                    'enacted_on' => $enacted,
                    'effectivity_date' => $enacted->copy()->addDays(25),
                ]);
            } else {
                Resolution::factory()->create([
                    'document_id' => $document->getKey(),
                    'resolution_number' => $measure['resolution_number'],
                    'series_year' => $this->year,
                    'title' => $measure['title'],
                    'purpose' => $measure['abstract'],
                    'category' => $measure['resolution_category'],
                    'adopted_on' => now()->subDays(7),
                    'effectivity_date' => now()->subDays(7),
                ]);
            }

            Publication::factory()->published()->create([
                'document_id' => $document->getKey(),
                'document_version_id' => $document->versions()->where('is_current', true)->value('id'),
                'title' => $measure['title'],
                'summary' => $measure['publication_summary'],
                'public_slug' => Str::slug(Str::limit($measure['title'], 60, '')).'-'.Str::lower(Str::random(6)),
                'categories' => [$measure['type'] === DocumentType::Ordinance ? 'ordinances' : 'resolutions'],
                'reviewed_by' => $this->person('secretariat')->getKey(),
                'published_by' => $this->person('secretariat')->getKey(),
                'reviewed_at' => now()->subDays(3),
                'published_at' => now()->subDays(2),
                'view_count' => 0,
                'download_count' => 0,
            ]);
        }
    }

    /**
     * The previous sitting: tricycle ordinance approved on second reading
     * 9-2-1, commendation adopted 11-0-1, transcript complete, minutes held
     * as an AI draft whose official tallies match the ballots.
     *
     * @param  Collection<string, Document>  $documents
     */
    private function seedAdjournedSession(Collection $documents): void
    {
        $day = CarbonImmutable::now()->subDays(7)->setTime(9, 0);

        $session = LegislativeSession::factory()->adjourned()->create([
            'session_number' => sprintf('RS-%d-00037', $this->year),
            'title' => '37th Regular Session',
            'type' => 'regular',
            'legislative_year' => $this->year,
            'scheduled_start_at' => $day,
            'scheduled_end_at' => $day->addHours(4),
            'actual_start_at' => $day->addMinutes(5),
            'actual_end_at' => $day->addHours(3)->addMinutes(20),
            'adjourned_at' => $day->addHours(3)->addMinutes(20),
            'agenda_locked_at' => $day->subDays(2),
            'documents_distributed_at' => $day->subDays(2),
            'presiding_officer_id' => $this->person('presiding')->getKey(),
            'secretary_id' => $this->person('secretariat')->getKey(),
            'seated_member_count' => $this->seatedCount(),
            'quorum_required' => intdiv($this->seatedCount(), 2) + 1,
            'quorum_declared_at' => $day->addMinutes(12),
            'quorum_declared_by' => $this->person('presiding')->getKey(),
        ]);

        $this->seedAttendance($session, $day->addMinutes(5), [
            'late' => ['abad'],
            'on-official-business' => ['buenaventura'],
        ]);

        $items = $this->seedOrderOfBusiness($session, [
            'first-reading' => [],
            'business-for-the-day' => [$documents->get('tricycle'), $documents->get('commendation')],
            'third-reading' => [],
        ], allCompleted: true, startedAt: $day);

        $tricycleItem = $items->firstWhere('document_id', $documents->get('tricycle')->getKey());
        $commendationItem = $items->firstWhere('document_id', $documents->get('commendation')->getKey());

        $this->seedDecidedMotion($session, $tricycleItem, 'pascual', 'castillo', sprintf(
            'I move that Proposed Ordinance No. %d-036 be approved on second reading, as amended.',
            $this->year,
        ), $day->addMinutes(27), [
            'no' => ['delossantos', 'abad'],
            'abstain' => ['ramirez'],
        ]);

        $this->seedDecidedMotion($session, $commendationItem, 'ilagan', 'soriano', sprintf(
            'I move that Proposed Resolution No. %d-108 be adopted.',
            $this->year,
        ), $day->addMinutes(30), [
            'abstain' => ['evangelista'],
        ]);

        $this->seedAdjournedTranscript($session, $day->addMinutes(5));

        $draft = MarketingDemoContent::adjournedMinutesDraft($this->year, $day->format('F j, Y'));

        Minutes::factory()->create([
            'session_id' => $session->getKey(),
            'status' => AiDraft::$name,
            'ai_draft' => $draft,
            'content' => $draft,
            'ai_generated_at' => $day->addHours(4),
            'ai_model' => 'official-records-minutes',
            'ai_metadata' => [
                'banner' => LegislativeMinutesGenerator::DRAFT_BANNER,
                'source' => 'official-records',
                'requires_human_verification' => true,
                'vote_tallies' => [
                    [
                        'agenda_item_id' => $tricycleItem->getKey(),
                        'title' => $tricycleItem->title,
                        'voting_round' => 1,
                        'yes' => 9,
                        'no' => 2,
                        'abstain' => 1,
                        'inhibit' => 0,
                    ],
                    [
                        'agenda_item_id' => $commendationItem->getKey(),
                        'title' => $commendationItem->title,
                        'voting_round' => 1,
                        'yes' => 11,
                        'no' => 0,
                        'abstain' => 1,
                        'inhibit' => 0,
                    ],
                ],
            ],
            'prepared_by' => $this->person('secretariat')->getKey(),
        ]);
    }

    /**
     * The sitting on the floor right now. The scholarship ordinance is the
     * live item. Seven members count toward a quorum of eight; the demo board
     * member is absent until they open the floor on their tablet. Quorum is
     * not declared, and no motion is pending, so both happen on camera.
     *
     * @param  Collection<string, Document>  $documents
     */
    private function seedLiveSession(Collection $documents): void
    {
        $start = CarbonImmutable::now()->subMinutes(55);

        $session = LegislativeSession::factory()->inSession()->create([
            'session_number' => sprintf('RS-%d-00038', $this->year),
            'title' => '38th Regular Session',
            'type' => 'regular',
            'legislative_year' => $this->year,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->addHours(4),
            'actual_start_at' => $start,
            'agenda_locked_at' => $start->subDays(2),
            'documents_distributed_at' => $start->subDays(2),
            'presiding_officer_id' => $this->person('presiding')->getKey(),
            'secretary_id' => $this->person('secretariat')->getKey(),
            'seated_member_count' => $this->seatedCount(),
            'quorum_required' => intdiv($this->seatedCount(), 2) + 1,
            'quorum_declared_at' => null,
            'quorum_declared_by' => null,
        ]);

        $this->seedAttendance($session, $start, [
            'late' => ['delossantos'],
            'on-official-business' => ['buenaventura'],
            'excused' => ['evangelista'],
            'absent' => ['dizon', 'manalo', 'salazar', 'abad', 'ramirez'],
        ]);

        $items = $this->seedOrderOfBusiness($session, [
            'first-reading' => [$documents->get('watershed')],
            'business-for-the-day' => [$documents->get('scholarship'), $documents->get('road_moa')],
            'third-reading' => [$documents->get('tricycle')],
        ], allCompleted: false, startedAt: $start);

        $live = $items->firstWhere('document_id', $documents->get('scholarship')->getKey());
        $live->forceFill([
            'status' => 'in-progress',
            'started_at' => $start->addSeconds(2890),
        ])->save();

        $segments = [];

        foreach (MarketingDemoContent::liveTranscript($this->year) as $index => [$offset, $speaker, $text, $confidence]) {
            $segments[] = $this->segment($index + 1, $offset, $speaker, $text, $confidence, attributed: $speaker !== null);
        }

        Transcript::query()->create([
            'session_id' => $session->getKey(),
            'agenda_item_id' => $live->getKey(),
            'source' => 'chamber_channels',
            'status' => 'processing',
            'language' => 'en',
            'provider' => 'whisper-compatible',
            'model' => 'whisper-1',
            'segments' => $segments,
            'full_text' => implode("\n", array_column($segments, 'text')),
            'average_confidence' => round(collect($segments)->avg('confidence'), 4),
            'started_at' => $start,
            'created_by' => $this->person('secretariat')->getKey(),
        ]);

        PrivateNote::query()->create([
            'user_id' => $this->person('dizon')->getKey(),
            'notable_type' => Document::class,
            'notable_id' => $documents->get('scholarship')->getKey(),
            'body' => MarketingDemoContent::memberScholarshipNote(),
            'page_number' => 1,
            'color' => 'amber',
        ]);
    }

    /**
     * Mirrors the standard order of business in AgendaService. Measures sit
     * under their heading; positions run across the whole agenda.
     *
     * @param  array<string, list<Document>>  $measures
     * @return Collection<int, AgendaItem>
     */
    private function seedOrderOfBusiness(LegislativeSession $session, array $measures, bool $allCompleted, CarbonImmutable $startedAt): Collection
    {
        $items = collect();
        $position = 1;
        $done = ['status' => 'completed', 'started_at' => $startedAt, 'completed_at' => $startedAt->addMinutes(10)];

        $add = function (array $attributes, ?AgendaItem $parent = null, bool $completed = false) use ($session, $items, &$position, $done): AgendaItem {
            $item = AgendaItem::query()->create([
                'session_id' => $session->getKey(),
                'parent_id' => $parent?->getKey(),
                'position' => $position++,
                'status' => 'pending',
                'requires_vote' => false,
                ...$attributes,
                ...($completed ? $done : []),
            ]);
            $items->push($item);

            return $item;
        };

        $measure = function (Document $document, AgendaItem $heading, int $sibling, string $category, int $reading) use ($add, $allCompleted): AgendaItem {
            return $add([
                'item_number' => $heading->item_number.'.'.$sibling,
                'title' => $document->title,
                'document_id' => $document->getKey(),
                'committee_id' => $document->committee_id,
                'category' => $category,
                'reading_number' => $reading,
                'requires_vote' => $reading > 1,
                'presented_by' => $document->author_id,
                'time_allotment_minutes' => 30,
            ], $heading, $allCompleted || $reading === 1);
        };

        $add(['category' => 'call-to-order', 'title' => 'Call to Order', 'item_number' => '1'], completed: true);
        $add(['category' => 'convocation', 'title' => 'Invocation', 'item_number' => '2'], completed: true);
        $add(['category' => 'roll-call', 'title' => 'Roll Call', 'item_number' => '3'], completed: true);
        $add(['category' => 'approval-minutes', 'title' => 'Reading and Consideration of the Minutes', 'item_number' => '4'], completed: true);
        $add(['category' => 'privilege-hour', 'title' => 'Privilege Hour', 'item_number' => '5', 'time_allotment_minutes' => 10], completed: true);

        $reference = $add(['category' => 'first-reading', 'title' => 'Reference of Business', 'item_number' => '6'], completed: true);
        foreach ($measures['first-reading'] as $i => $document) {
            $measure($document, $reference, $i + 1, 'first-reading', 1);
        }

        $calendar = $add(['category' => 'calendar-of-business', 'title' => 'Calendar of Business', 'item_number' => '7'], completed: $allCompleted);
        $add(['category' => 'unfinished-business', 'title' => 'Unfinished Business', 'item_number' => '7.1'], $calendar, completed: true);
        $today = $add(['category' => 'business-for-the-day', 'title' => 'Business for the Day', 'item_number' => '7.2'], $calendar, completed: $allCompleted);
        foreach ($measures['business-for-the-day'] as $i => $document) {
            $measure($document, $today, $i + 1, 'second-reading', 2);
        }

        $third = $add(['category' => 'third-reading', 'title' => 'Business on Third and Final Reading', 'item_number' => '8'], completed: $allCompleted);
        foreach ($measures['third-reading'] as $i => $document) {
            $measure($document, $third, $i + 1, 'third-reading', 3);
        }

        $add(['category' => 'other-matters', 'title' => 'Other Matters / Announcements', 'item_number' => '9'], completed: $allCompleted);
        $add(['category' => 'adjournment', 'title' => 'Adjournment', 'item_number' => '10', 'time_allotment_minutes' => 5], completed: $allCompleted);

        return $items;
    }

    /**
     * Everyone not listed under another status is present.
     *
     * @param  array<string, list<string>>  $exceptions
     */
    private function seedAttendance(LegislativeSession $session, CarbonImmutable $checkIn, array $exceptions): void
    {
        $statusByKey = [];

        foreach ($exceptions as $status => $keys) {
            foreach ($keys as $key) {
                $statusByKey[$key] = AttendanceStatus::from($status);
            }
        }

        $remarks = [
            AttendanceStatus::OnOfficialBusiness->value => 'Attending a regional development council meeting.',
            AttendanceStatus::Excused->value => 'On approved leave.',
        ];

        foreach ($this->seatedMembers() as $key => $user) {
            $status = $statusByKey[$key] ?? AttendanceStatus::Present;
            $counts = $status->countsTowardQuorum();

            SessionAttendance::query()->create([
                'session_id' => $session->getKey(),
                'user_id' => $user->getKey(),
                'status' => $status->value,
                'checked_in_at' => $counts ? ($status === AttendanceStatus::Late ? $checkIn->addMinutes(25) : $checkIn) : null,
                'check_in_method' => $counts ? 'tablet' : null,
                'remarks' => $remarks[$status->value] ?? null,
                'recorded_by' => $this->person('secretariat')->getKey(),
            ]);
        }
    }

    /**
     * Carried motion plus one electronic ballot per present member. The
     * presiding officer does not vote.
     *
     * @param  array<string, list<string>>  $dissent
     */
    private function seedDecidedMotion(LegislativeSession $session, AgendaItem $item, string $mover, string $seconder, string $text, CarbonImmutable $movedAt, array $dissent): void
    {
        $motion = Motion::query()->create([
            'session_id' => $session->getKey(),
            'agenda_item_id' => $item->getKey(),
            'type' => 'main',
            'text' => $text,
            'status' => 'carried',
            'moved_by' => $this->person($mover)->getKey(),
            'moved_at' => $movedAt,
            'seconded_by' => $this->person($seconder)->getKey(),
            'seconded_at' => $movedAt->addSeconds(8),
            'disposed_at' => $movedAt->addMinutes(2),
            'voting_round' => 1,
            'requires_vote' => true,
        ]);

        $item->forceFill([
            'voting_round' => 1,
            'voting_opened_at' => $movedAt->addSeconds(15),
            'voting_closed_at' => $movedAt->addMinutes(2),
        ])->save();

        $choices = [];

        foreach ($dissent as $choice => $keys) {
            foreach ($keys as $key) {
                $choices[$key] = VoteChoice::from($choice);
            }
        }

        $voters = SessionAttendance::query()
            ->where('session_id', $session->getKey())
            ->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
            ->where('user_id', '!=', $this->person('presiding')->getKey())
            ->pluck('user_id');

        foreach ($this->seatedMembers() as $key => $user) {
            if (! $voters->contains($user->getKey())) {
                continue;
            }

            Vote::query()->create([
                'session_id' => $session->getKey(),
                'agenda_item_id' => $item->getKey(),
                'motion_id' => $motion->getKey(),
                'user_id' => $user->getKey(),
                'voting_round' => 1,
                'choice' => ($choices[$key] ?? VoteChoice::Yes)->value,
                'method' => 'electronic',
                'cast_at' => $movedAt->addSeconds(30 + count($choices)),
            ]);
        }
    }

    private function seedAdjournedTranscript(LegislativeSession $session, CarbonImmutable $start): void
    {
        $segments = [];
        $correctedIndex = null;

        foreach (MarketingDemoContent::adjournedTranscript($this->year) as $i => $line) {
            $segment = $this->segment($i + 1, $line['start'], $line['speaker'], $line['text'], $line['confidence'], attributed: true);

            if (isset($line['original_text'])) {
                $segment['original_text'] = $line['original_text'];
                $segment['edited_at'] = $start->addHours(4)->toIso8601String();
                $segment['edited_by'] = $this->person('secretariat')->getKey();
                $correctedIndex = $i + 1;
            }

            $segments[] = $segment;
        }

        $transcript = Transcript::query()->create([
            'session_id' => $session->getKey(),
            'source' => 'live_stt',
            'status' => 'completed',
            'language' => 'en',
            'provider' => 'whisper-compatible',
            'model' => 'whisper-1',
            'segments' => $segments,
            'full_text' => implode("\n", array_column($segments, 'text')),
            'average_confidence' => round(collect($segments)->avg('confidence'), 4),
            'duration_seconds' => 12000,
            'started_at' => $start,
            'ended_at' => $start->addSeconds(12000),
            'created_by' => $this->person('secretariat')->getKey(),
        ]);

        if ($correctedIndex !== null) {
            $corrected = $segments[$correctedIndex - 1];

            TranscriptSegmentEdit::query()->create([
                'transcript_id' => $transcript->getKey(),
                'segment_index' => $correctedIndex,
                'field' => 'text',
                'old_value' => $corrected['original_text'],
                'new_value' => $corrected['text'],
                'user_id' => $this->person('secretariat')->getKey(),
                'created_at' => $start->addHours(4),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function segment(int $index, float $start, ?string $speaker, string $text, float $confidence, bool $attributed): array
    {
        $user = $speaker !== null && $speaker !== 'gallery' ? $this->person($speaker) : null;
        $label = match (true) {
            $user instanceof User => $user->display_name,
            $speaker === 'gallery' => __('transcripts.gallery'),
            default => null,
        };

        return [
            'index' => $index,
            'start' => $start,
            'end' => $start + max(3.0, str_word_count($text) * 0.42),
            'speaker' => $label,
            'speaker_id' => $user?->getKey(),
            'attributed' => $attributed,
            'text' => $text,
            'confidence' => $confidence,
            'language' => 'en',
            'original_text' => $text,
            'original_speaker' => $label,
            'original_speaker_id' => $user?->getKey(),
            'original_attributed' => $attributed,
        ];
    }

    /**
     * A short hash chain so the audit register is not empty. Events are
     * fictional session facts, written through AuditLogger so the hashes link.
     */
    private function seedAuditTrail(): void
    {
        $audit = app(AuditLogger::class);
        $secretariat = $this->person('secretariat');
        $past = LegislativeSession::query()->where('title', '37th Regular Session')->firstOrFail();
        $minutes = Minutes::query()->where('session_id', $past->getKey())->firstOrFail();
        $publication = Publication::query()->where('status', 'published')->orderBy('published_at')->firstOrFail();

        $audit->record(
            event: 'session.adjourned',
            category: 'session',
            auditable: $past,
            actor: $secretariat,
            new: ['status' => 'adjourned', 'session_number' => $past->session_number],
            message: '37th Regular Session adjourned.',
        );

        $audit->record(
            event: 'vote.closed',
            category: 'session',
            auditable: $past,
            actor: $secretariat,
            new: ['yes' => 9, 'no' => 2, 'abstain' => 1],
            message: sprintf('Electronic vote closed on Proposed Ordinance No. %d-036. Tally 9-2-1.', $this->year),
        );

        $audit->record(
            event: 'ai.minutes.draft',
            category: 'ai',
            auditable: $minutes,
            actor: $secretariat,
            new: ['status' => AiDraft::$name],
            message: 'AI draft minutes generated from official session records.',
            isAiActor: true,
        );

        $audit->record(
            event: 'publication.published',
            category: 'publication',
            auditable: $publication,
            actor: $secretariat,
            new: ['title' => $publication->title],
            message: 'Measure published to the public portal.',
        );
    }

    /**
     * @return Collection<string, User>
     */
    private function seatedMembers(): Collection
    {
        return $this->people->filter(fn (User $user): bool => (bool) $user->is_seated_member);
    }

    private function seatedCount(): int
    {
        return $this->seatedMembers()->count();
    }

    private function person(string $key): User
    {
        return $this->people->get($key) ?? throw new RuntimeException("Unknown demo person [{$key}].");
    }

    private function honorific(string $key): string
    {
        return 'Hon. '.$this->person($key)->display_name;
    }
}
