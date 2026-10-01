<?php

use App\Enums\AttendanceStatus;
use App\Jobs\Documents\ProcessDocumentVersionJob;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\LegislativeSession;
use App\Models\Minutes;
use App\Models\PrivateNote;
use App\Models\Publication;
use App\Models\SessionAttendance;
use App\Models\Transcript;
use App\Models\User;
use App\Models\Vote;
use App\Services\Documents\DocumentTextStore;
use Database\Seeders\MarketingDemoSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Bus::fake([ProcessDocumentVersionJob::class]);

    $this->seed(MarketingDemoSeeder::class);
});

it('stores a real PDF and extracted text for every measure', function (): void {
    $versions = DocumentVersion::query()->get();

    expect($versions)->toHaveCount(7);

    foreach ($versions as $version) {
        expect(Storage::disk('local')->exists($version->file_path))->toBeTrue()
            ->and(Storage::disk('local')->get($version->file_path))->toStartWith('%PDF')
            ->and(app(DocumentTextStore::class)->get($version))->not->toBeEmpty();
    }

    Bus::assertDispatchedTimes(ProcessDocumentVersionJob::class, 7);
});

it('leaves the live sitting one member short of quorum and undeclared', function (): void {
    $live = LegislativeSession::query()->where('title', '38th Regular Session')->firstOrFail();
    $member = User::query()->where('email', 'member@sentria.test')->firstOrFail();

    $counting = SessionAttendance::query()
        ->where('session_id', $live->getKey())
        ->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
        ->count();

    expect($counting)->toBe($live->quorum_required - 1)
        ->and($live->quorum_declared_at)->toBeNull()
        ->and(SessionAttendance::query()->where('session_id', $live->getKey())->where('user_id', $member->getKey())->value('status'))
        ->toBe(AttendanceStatus::Absent->value)
        ->and($live->agendaItems()->where('status', 'in-progress')->value('title'))
        ->toStartWith('An Ordinance Establishing the Provincial Scholarship Program');
});

it('gives the live transcript unassigned mixer lines, a gallery line, and a low-confidence line', function (): void {
    $live = LegislativeSession::query()->where('title', '38th Regular Session')->firstOrFail();
    $segments = collect(Transcript::query()->where('session_id', $live->getKey())->firstOrFail()->segments);

    expect($segments->where('attributed', false))->toHaveCount(3)
        ->and($segments->where('confidence', '<', config('sentria.transcription.low_confidence', 0.4)))->toHaveCount(1)
        ->and($segments->firstWhere('speaker', __('transcripts.gallery')))->not->toBeNull();
});

it('records ballots on the adjourned sitting that match the transcript and the draft minutes', function (): void {
    $past = LegislativeSession::query()->where('title', '37th Regular Session')->firstOrFail();
    $tricycle = Document::query()->where('reference_number', 'like', 'PO-%-00036')->firstOrFail();
    $commendation = Document::query()->where('reference_number', 'like', 'RES-%-00108')->firstOrFail();

    $tally = fn (Document $document): array => Vote::query()
        ->where('session_id', $past->getKey())
        ->whereHas('agendaItem', fn ($query) => $query->where('document_id', $document->getKey()))
        ->pluck('choice')
        ->countBy()
        ->all();

    $minutes = Minutes::query()->where('session_id', $past->getKey())->firstOrFail();
    $tallies = collect($minutes->ai_metadata['vote_tallies'] ?? []);

    expect($tally($tricycle))->toEqual(['yes' => 9, 'no' => 2, 'abstain' => 1])
        ->and($tally($commendation))->toEqual(['yes' => 11, 'abstain' => 1])
        ->and($minutes->status->getValue())->toBe('ai-draft')
        ->and($minutes->content)->toContain('YES: 9 NO: 2 ABSTAIN: 1')
        ->and($minutes->content)->toContain('YES: 11 NO: 0 ABSTAIN: 1')
        ->and($minutes->ai_draft)->toBe($minutes->content)
        ->and($tallies->firstWhere('yes', 9))->toMatchArray(['yes' => 9, 'no' => 2, 'abstain' => 1])
        ->and($tallies->firstWhere('yes', 11))->toMatchArray(['yes' => 11, 'no' => 0, 'abstain' => 1]);
});

it('publishes only enacted measures to the portal', function (): void {
    expect(Publication::query()->where('status', 'published')->count())->toBe(3)
        ->and(Document::query()->where('is_public', true)->count())->toBe(3);
});

it('keeps a private note on the live scholarship measure for the demo board member', function (): void {
    $member = User::query()->where('email', 'member@sentria.test')->firstOrFail();
    $scholarship = Document::query()->where('reference_number', 'like', 'PO-%-00041')->firstOrFail();

    $note = PrivateNote::query()
        ->where('user_id', $member->getKey())
        ->where('notable_id', $scholarship->getKey())
        ->firstOrFail();

    expect($note->body)->toContain('twenty-slot floor');
});

it('writes a linked hash chain for the audit register', function (): void {
    $logs = AuditLog::query()->orderBy('sequence')->get()->values();

    expect($logs->count())->toBeGreaterThanOrEqual(4)
        ->and($logs[0]->previous_hash)->toBeNull();

    foreach (range(1, $logs->count() - 1) as $index) {
        expect($logs[$index]->previous_hash)->toBe($logs[$index - 1]->hash);
    }
});
