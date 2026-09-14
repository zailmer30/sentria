<?php

namespace App\Services\AI;

use App\Contracts\AI\ConsistencyCheckService;
use App\DTO\AI\ConsistencyCheckResult;
use App\DTO\AI\ConsistencyFinding;
use App\Models\Document;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentAccessService;
use App\Services\Documents\DocumentTextStore;
use Illuminate\Auth\Access\AuthorizationException;

class HeuristicConsistencyCheckService implements ConsistencyCheckService
{
    private const SECTION_HEADING_PATTERN = '/^(?:SECTION|Sec\.|Article|ARTICLE|CHAPTER)\s+([^\s.:]+)/im';

    private const SECTION_REFERENCE_PATTERN = '/\b(?:Section|SECTION|Sec\.)\s+(\d+[A-Za-z]?)\b/i';

    private const CURRENCY_PATTERN = '/(?:PHP|₱|\$)\s*[\d,]+(?:\.\d{2})?/i';

    private const DATE_PATTERN = '/\b(?:\d{1,2}\/\d{1,2}\/\d{2,4}|(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4})\b/i';

    public function __construct(
        private readonly DocumentAccessService $access,
        private readonly AuditLogger $audit,
        private readonly DocumentTextStore $textStore,
    ) {}

    public function check(User $user, Document $document): ConsistencyCheckResult
    {
        if (! $user->can('ai.checkConsistency')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (! $this->access->userCanView($user, $document)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $document->loadMissing('currentVersion');
        $version = $document->currentVersion;
        $text = $version !== null ? trim((string) $this->textStore->get($version)) : '';

        if ($text === '') {
            $result = new ConsistencyCheckResult(findings: []);

            $this->auditCheck($user, $document, $result);

            return $result;
        }

        $findings = [
            ...$this->findBrokenCrossReferences($text),
            ...$this->findDuplicateSectionNumbers($text),
            ...$this->findNumberingGaps($text),
            ...$this->findDuplicateAmounts($text),
            ...$this->findDuplicateDates($text),
        ];

        $result = new ConsistencyCheckResult(findings: $findings);

        $this->auditCheck($user, $document, $result);

        return $result;
    }

    /**
     * @return list<ConsistencyFinding>
     */
    private function findBrokenCrossReferences(string $text): array
    {
        $definedSections = $this->definedSectionNumbers($text);
        $findings = [];

        if (preg_match_all(self::SECTION_REFERENCE_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        foreach ($matches[1] as $index => $match) {
            $sectionNumber = strtoupper((string) $match[0]);
            $offset = (int) ($matches[0][$index][1] ?? 0);

            if (in_array($sectionNumber, $definedSections, true)) {
                continue;
            }

            $findings[] = new ConsistencyFinding(
                type: 'broken_cross_reference',
                severity: 'high',
                message: "Section {$match[0]} is referenced, but no Section {$match[0]} heading was detected.",
                locationHint: $this->locationHint($text, $offset),
            );
        }

        return $this->dedupeFindings($findings);
    }

    /**
     * @return list<ConsistencyFinding>
     */
    private function findDuplicateSectionNumbers(string $text): array
    {
        if (preg_match_all(self::SECTION_HEADING_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $seen = [];
        $findings = [];

        foreach ($matches[1] as $index => $match) {
            $sectionNumber = strtoupper((string) $match[0]);
            $offset = (int) ($matches[0][$index][1] ?? 0);

            if (isset($seen[$sectionNumber])) {
                $findings[] = new ConsistencyFinding(
                    type: 'duplicate_section_number',
                    severity: 'medium',
                    message: "Section {$match[0]} appears more than once as a heading.",
                    locationHint: $this->locationHint($text, $offset),
                );

                continue;
            }

            $seen[$sectionNumber] = $offset;
        }

        return $findings;
    }

    /**
     * @return list<ConsistencyFinding>
     */
    private function findNumberingGaps(string $text): array
    {
        $numericSections = array_values(array_filter(
            $this->definedSectionNumbers($text),
            static fn (string $number): bool => ctype_digit($number),
        ));

        if (count($numericSections) < 2) {
            return [];
        }

        $numbers = array_map(intval(...), $numericSections);
        sort($numbers);

        $findings = [];

        for ($index = 1, $max = max($numbers); $index < $max; $index++) {
            if (! in_array($index, $numbers, true)) {
                $findings[] = new ConsistencyFinding(
                    type: 'numbering_gap',
                    severity: 'low',
                    message: "Section {$index} heading was not detected between other numbered sections.",
                    locationHint: null,
                );
            }
        }

        return $findings;
    }

    /**
     * @return list<ConsistencyFinding>
     */
    private function findDuplicateAmounts(string $text): array
    {
        if (preg_match_all(self::CURRENCY_PATTERN, $text, $matches) === false) {
            return [];
        }

        $amounts = array_map(
            static fn (string $amount): string => strtoupper(preg_replace('/\s+/', '', $amount) ?? $amount),
            $matches[0],
        );

        $counts = array_count_values($amounts);
        $findings = [];

        foreach ($counts as $amount => $count) {
            if ($count < 2) {
                continue;
            }

            $findings[] = new ConsistencyFinding(
                type: 'repeated_amount',
                severity: 'low',
                message: "The amount {$amount} appears {$count} times. Verify whether repeated figures are intentional.",
                locationHint: null,
            );
        }

        return $findings;
    }

    /**
     * @return list<ConsistencyFinding>
     */
    private function findDuplicateDates(string $text): array
    {
        if (preg_match_all(self::DATE_PATTERN, $text, $matches) === false) {
            return [];
        }

        $dates = array_map('trim', $matches[0]);
        $counts = array_count_values($dates);
        $findings = [];

        foreach ($counts as $date => $count) {
            if ($count < 2) {
                continue;
            }

            $findings[] = new ConsistencyFinding(
                type: 'repeated_date',
                severity: 'low',
                message: "The date {$date} appears {$count} times. Verify whether repeated dates are intentional.",
                locationHint: null,
            );
        }

        return $findings;
    }

    /**
     * @return list<string>
     */
    private function definedSectionNumbers(string $text): array
    {
        if (preg_match_all(self::SECTION_HEADING_PATTERN, $text, $matches) === false) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn (string $number): string => strtoupper($number),
            $matches[1],
        )));
    }

    private function locationHint(string $text, int $offset): string
    {
        $lineNumber = substr_count(substr($text, 0, max(0, $offset)), "\n") + 1;
        $lineStart = strrpos(substr($text, 0, max(0, $offset)), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $lineEnd = strpos($text, "\n", $offset);
        $line = trim(substr($text, $lineStart, ($lineEnd === false ? strlen($text) : $lineEnd) - $lineStart));

        return "Line {$lineNumber}: {$line}";
    }

    /**
     * @param  list<ConsistencyFinding>  $findings
     * @return list<ConsistencyFinding>
     */
    private function dedupeFindings(array $findings): array
    {
        $unique = [];

        foreach ($findings as $finding) {
            $key = $finding->type.'|'.$finding->message;
            $unique[$key] = $finding;
        }

        return array_values($unique);
    }

    private function auditCheck(User $user, Document $document, ConsistencyCheckResult $result): void
    {
        $this->audit->record(
            event: 'ai.consistency_check',
            category: 'ai',
            auditable: $document,
            actor: $user,
            context: [
                'document_id' => $document->getKey(),
                'finding_count' => count($result->findings),
                'finding_types' => array_values(array_unique(array_map(
                    static fn (ConsistencyFinding $finding): string => $finding->type,
                    $result->findings,
                ))),
            ],
            message: 'AI-assisted consistency review completed.',
            isAiActor: true,
        );
    }
}
