<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\Confidentiality;
use App\Enums\DocumentType;
use App\Enums\VoteChoice;
use App\Models\AgendaItem;
use App\Models\Committee;
use App\Models\CommitteeReferral;
use App\Models\CommitteeReport;
use App\Models\Document;
use App\Models\DocumentGrant;
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
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Fictional demo content spanning the full legislative lifecycle so every
 * module has something to show before its own slice is built.
 */
class LegislativeContentSeeder extends Seeder
{
    /** @var Collection<int, User> */
    private Collection $seatedMembers;

    /** @var Collection<int, Committee> */
    private Collection $committees;

    private User $secretariat;

    private User $presidingOfficer;

    public function run(): void
    {
        $this->seatedMembers = User::query()->where('is_seated_member', true)->get();
        $this->committees = Committee::query()->get();
        $this->secretariat = User::query()->where('email', 'secretariat@sentria.test')->firstOrFail();
        $this->presidingOfficer = User::query()->where('email', 'presiding@sentria.test')->firstOrFail();

        $documents = $this->seedDocuments();
        $this->seedCommitteeWork($documents);
        $this->seedEnactedLegislation();
        $this->seedSessions($documents);
        $this->seedRestrictedAccessExample();
    }

    /**
     * @return Collection<int, Document>
     */
    private function seedDocuments(): Collection
    {
        return Document::factory()
            ->count(24)
            ->sequence(fn (Sequence $sequence): array => [
                'author_id' => $this->seatedMembers->random()->getKey(),
                'status' => match ($sequence->index % 6) {
                    0 => 'submitted',
                    1 => 'secretariat-review',
                    2 => 'registered',
                    3 => 'committee-review',
                    4 => 'agenda-inclusion',
                    default => 'reading-deliberation',
                },
            ])
            ->create()
            ->each(function (Document $document): void {
                DocumentVersion::factory()->for($document)->create([
                    'uploaded_by' => $document->author_id,
                ]);

                $document->forceFill(['version_count' => 1])->save();
            });
    }

    /**
     * @param  Collection<int, Document>  $documents
     */
    private function seedCommitteeWork(Collection $documents): void
    {
        $documents->take(12)->each(function (Document $document, int $index): void {
            $committee = $this->committees->random();

            $referral = CommitteeReferral::factory()->create([
                'document_id' => $document->getKey(),
                'committee_id' => $committee->getKey(),
                'referred_by' => $this->secretariat->getKey(),
                'status' => $index % 3 === 0 ? 'reported' : 'in-review',
                'completed_at' => $index % 3 === 0 ? now()->subDays(6) : null,
            ]);

            if ($index % 3 !== 0) {
                return;
            }

            CommitteeReport::factory()->adopted()->create([
                'committee_referral_id' => $referral->getKey(),
                'committee_id' => $committee->getKey(),
                'subject_document_id' => $document->getKey(),
                'submitted_by' => $this->seatedMembers->random()->getKey(),
            ]);
        });

        // One overdue referral so the dashboard has a real exception to show.
        CommitteeReferral::factory()->overdue()->create([
            'document_id' => $documents->random()->getKey(),
            'committee_id' => $this->committees->random()->getKey(),
            'referred_by' => $this->secretariat->getKey(),
        ]);
    }

    private function seedEnactedLegislation(): void
    {
        Ordinance::factory()->count(6)->create()->each(function (Ordinance $ordinance): void {
            $this->publish($ordinance->document_id, $ordinance->title);
        });

        Resolution::factory()->count(8)->create()->each(function (Resolution $resolution): void {
            $this->publish($resolution->document_id, $resolution->title);
        });
    }

    private function publish(string $documentId, string $title): void
    {
        Publication::factory()->published()->create([
            'document_id' => $documentId,
            'title' => $title,
            'reviewed_by' => $this->secretariat->getKey(),
            'published_by' => $this->secretariat->getKey(),
        ]);
    }

    /**
     * @param  Collection<int, Document>  $documents
     */
    private function seedSessions(Collection $documents): void
    {
        $this->seedPastSession($documents);
        $this->seedLiveSession($documents);
        $this->seedUpcomingSession($documents);
    }

    /**
     * @param  Collection<int, Document>  $documents
     */
    private function seedPastSession(Collection $documents): void
    {
        $session = LegislativeSession::factory()->adjourned()->create([
            'presiding_officer_id' => $this->presidingOfficer->getKey(),
            'secretary_id' => $this->secretariat->getKey(),
            'seated_member_count' => $this->seatedMembers->count(),
            'quorum_required' => intdiv($this->seatedMembers->count(), 2) + 1,
            'quorum_declared_at' => now()->subDays(7)->setTime(9, 15),
            'quorum_declared_by' => $this->presidingOfficer->getKey(),
        ]);

        $this->seedAttendance($session);
        $items = $this->seedAgenda($session, $documents->skip(4)->take(3));

        $votingItem = $items->first(fn (AgendaItem $item): bool => (bool) $item->requires_vote);

        if ($votingItem instanceof AgendaItem) {
            $motion = Motion::factory()->carried()->create([
                'session_id' => $session->getKey(),
                'agenda_item_id' => $votingItem->getKey(),
                'moved_by' => $this->seatedMembers->random()->getKey(),
                'seconded_by' => $this->seatedMembers->random()->getKey(),
            ]);

            $this->seedBallots($session, $votingItem, $motion);
        }

        Transcript::factory()->create([
            'session_id' => $session->getKey(),
            'created_by' => $this->secretariat->getKey(),
        ]);

        Minutes::factory()->aiDrafted()->create([
            'session_id' => $session->getKey(),
            'prepared_by' => $this->secretariat->getKey(),
        ]);
    }

    /**
     * @param  Collection<int, Document>  $documents
     */
    private function seedLiveSession(Collection $documents): void
    {
        $session = LegislativeSession::factory()->inSession()->create([
            'presiding_officer_id' => $this->presidingOfficer->getKey(),
            'secretary_id' => $this->secretariat->getKey(),
            'seated_member_count' => $this->seatedMembers->count(),
            'quorum_required' => intdiv($this->seatedMembers->count(), 2) + 1,
            'quorum_declared_at' => now()->subMinutes(50),
            'quorum_declared_by' => $this->presidingOfficer->getKey(),
        ]);

        $this->seedAttendance($session);
        $items = $this->seedAgenda($session, $documents->skip(8)->take(4));

        $items->first()?->forceFill([
            'status' => 'in-progress',
            'started_at' => now()->subMinutes(12),
        ])->save();

        Motion::factory()->seconded()->create([
            'session_id' => $session->getKey(),
            'agenda_item_id' => $items->first()?->getKey(),
            'moved_by' => $this->seatedMembers->random()->getKey(),
        ]);

        Transcript::factory()->processing()->create([
            'session_id' => $session->getKey(),
            'created_by' => $this->secretariat->getKey(),
            'started_at' => now()->subHour(),
        ]);
    }

    /**
     * @param  Collection<int, Document>  $documents
     */
    private function seedUpcomingSession(Collection $documents): void
    {
        $session = LegislativeSession::factory()->scheduled()->create([
            'scheduled_start_at' => now()->addWeek()->setTime(9, 0),
            'scheduled_end_at' => now()->addWeek()->setTime(13, 0),
            'presiding_officer_id' => $this->presidingOfficer->getKey(),
            'secretary_id' => $this->secretariat->getKey(),
            'seated_member_count' => $this->seatedMembers->count(),
            'quorum_required' => intdiv($this->seatedMembers->count(), 2) + 1,
        ]);

        $this->seedAgenda($session, $documents->take(3));
    }

    private function seedAttendance(LegislativeSession $session): void
    {
        $this->seatedMembers->each(function (User $user, int $index) use ($session): void {
            $status = $index % 7 === 0 ? AttendanceStatus::Excused : AttendanceStatus::Present;

            SessionAttendance::factory()->create([
                'session_id' => $session->getKey(),
                'user_id' => $user->getKey(),
                'status' => $status->value,
                'checked_in_at' => $status->countsTowardQuorum() ? $session->actual_start_at ?? now() : null,
                'check_in_method' => $status->countsTowardQuorum() ? 'tablet' : null,
                'recorded_by' => $this->secretariat->getKey(),
            ]);
        });
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return Collection<int, AgendaItem>
     */
    private function seedAgenda(LegislativeSession $session, Collection $documents): Collection
    {
        $items = collect();
        $position = 1;

        foreach ([['call-to-order', 'Call to Order'], ['roll-call', 'Roll Call and Determination of Quorum']] as [$category, $title]) {
            $items->push(AgendaItem::factory()->procedural($category, $title, $position++)->create([
                'session_id' => $session->getKey(),
            ]));
        }

        foreach ($documents as $document) {
            $items->push(AgendaItem::factory()->create([
                'session_id' => $session->getKey(),
                'document_id' => $document->getKey(),
                'position' => $position,
                'item_number' => (string) $position,
                'title' => $document->title,
                'category' => 'second-reading',
                'requires_vote' => true,
                'presented_by' => $document->author_id,
            ]));

            $position++;
        }

        $items->push(AgendaItem::factory()->procedural('adjournment', 'Adjournment', $position)->create([
            'session_id' => $session->getKey(),
        ]));

        return $items;
    }

    private function seedBallots(LegislativeSession $session, AgendaItem $item, Motion $motion): void
    {
        $present = SessionAttendance::query()
            ->where('session_id', $session->getKey())
            ->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
            ->pluck('user_id');

        $present->each(function (string $userId, int $index) use ($session, $item, $motion): void {
            $choice = match (true) {
                $index % 9 === 0 => VoteChoice::Abstain,
                $index % 5 === 0 => VoteChoice::No,
                default => VoteChoice::Yes,
            };

            Vote::factory()->create([
                'session_id' => $session->getKey(),
                'agenda_item_id' => $item->getKey(),
                'motion_id' => $motion->getKey(),
                'user_id' => $userId,
                'voting_round' => 1,
                'choice' => $choice->value,
                'cast_at' => now()->subDays(7)->setTime(10, 30),
                'recorded_by' => $this->secretariat->getKey(),
            ]);
        });
    }

    /**
     * A confidential document reachable only through an explicit grant, so the
     * ACL path has real data to exercise.
     */
    private function seedRestrictedAccessExample(): void
    {
        $legalReviewer = User::query()->where('email', 'legal@sentria.test')->firstOrFail();

        $document = Document::factory()->confidential()->ofType(DocumentType::Communication)->create([
            'title' => 'Confidential communication on a pending administrative case',
            'author_id' => $this->secretariat->getKey(),
            'confidentiality' => Confidentiality::Confidential->value,
            'status' => 'secretariat-review',
        ]);

        DocumentVersion::factory()->for($document)->create([
            'uploaded_by' => $this->secretariat->getKey(),
        ]);

        DocumentGrant::factory()->create([
            'document_id' => $document->getKey(),
            'user_id' => $legalReviewer->getKey(),
            'ability' => 'view',
            'granted_by' => $this->secretariat->getKey(),
            'reason' => 'Legal review of a pending administrative matter.',
        ]);

        PrivateNote::factory()->create([
            'user_id' => $legalReviewer->getKey(),
            'notable_type' => Document::class,
            'notable_id' => $document->getKey(),
            'body' => 'Owner-only working note. Never shared, exported, or retrieved by AI for another user.',
        ]);
    }
}
