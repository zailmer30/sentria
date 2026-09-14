<?php

namespace App\Services\AI;

use App\Contracts\Documents\DocumentComparisonService;
use App\DTO\Documents\ComparisonResult;
use App\DTO\Documents\StructuredComparisonResult;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\Documents\DocumentAccessService;
use App\Services\Documents\DocumentTextStore;
use Illuminate\Auth\Access\AuthorizationException;

class StructuredDocumentComparisonService
{
    private const SECTION_HEADING_PATTERN = '/^(?:SECTION|Sec\.|Article|ARTICLE|CHAPTER)\s+([^\s.:]+)/im';

    private const CURRENCY_PATTERN = '/(?:PHP|₱|\$)\s*[\d,]+(?:\.\d{2})?/i';

    private const DATE_PATTERN = '/\b(?:\d{1,2}\/\d{1,2}\/\d{2,4}|(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4})\b/i';

    private const PENALTY_PATTERN = '/\b(?:penalty|fine|imprisonment|sanction)\b/i';

    private const DEFINITION_PATTERN = '/\b(?:shall mean|is defined as|for purposes of this)\b/i';

    public function __construct(
        private readonly DocumentComparisonService $lineDiff,
        private readonly DocumentAccessService $access,
        private readonly DocumentTextStore $textStore,
    ) {}

    public function compareVersions(User $user, DocumentVersion $from, DocumentVersion $to): StructuredComparisonResult
    {
        $from->loadMissing('document');
        $to->loadMissing('document');

        abort_unless($from->document !== null && $to->document !== null, 422, 'Document versions must belong to a document.');

        $this->authorizeBothDocuments($user, $from->document, $to->document);

        return $this->buildResult(
            $this->textStore->get($from),
            $this->textStore->get($to),
            $this->lineDiff->compare($from, $to),
        );
    }

    public function compareDocuments(User $user, Document $left, Document $right): StructuredComparisonResult
    {
        $this->authorizeBothDocuments($user, $left, $right);

        $left->loadMissing('currentVersion');
        $right->loadMissing('currentVersion');

        $leftVersion = $left->currentVersion;
        $rightVersion = $right->currentVersion;

        abort_unless($leftVersion !== null && $rightVersion !== null, 422, 'Both documents must have a current version.');

        return $this->compareVersions($user, $leftVersion, $rightVersion);
    }

    private function authorizeBothDocuments(User $user, Document $left, Document $right): void
    {
        if (! $user->can('ai.compare')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (! $this->access->userCanView($user, $left) || ! $this->access->userCanView($user, $right)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function buildResult(?string $leftText, ?string $rightText, ComparisonResult $lineDiff): StructuredComparisonResult
    {
        $left = trim((string) ($leftText ?? ''));
        $right = trim((string) ($rightText ?? ''));

        return new StructuredComparisonResult(
            lineDiff: $lineDiff,
            changedSections: $this->diffSections($left, $right),
            amountChanges: $this->diffMatches($left, $right, self::CURRENCY_PATTERN, 'amount'),
            dateChanges: $this->diffMatches($left, $right, self::DATE_PATTERN, 'date'),
            penaltyChanges: $this->diffLineMatches($left, $right, self::PENALTY_PATTERN, 'penalty'),
            definitionChanges: $this->diffLineMatches($left, $right, self::DEFINITION_PATTERN, 'definition'),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function diffSections(string $left, string $right): array
    {
        $leftSections = $this->sectionMap($left);
        $rightSections = $this->sectionMap($right);
        $changes = [];

        foreach ($leftSections as $number => $content) {
            if (! array_key_exists($number, $rightSections)) {
                $changes[] = [
                    'change' => 'removed',
                    'section_number' => $number,
                    'before' => $content,
                    'after' => null,
                ];

                continue;
            }

            if ($this->normalize($content) !== $this->normalize($rightSections[$number])) {
                $changes[] = [
                    'change' => 'changed',
                    'section_number' => $number,
                    'before' => $content,
                    'after' => $rightSections[$number],
                ];
            }
        }

        foreach ($rightSections as $number => $content) {
            if (! array_key_exists($number, $leftSections)) {
                $changes[] = [
                    'change' => 'added',
                    'section_number' => $number,
                    'before' => null,
                    'after' => $content,
                ];
            }
        }

        return $changes;
    }

    /**
     * @return array<string, string>
     */
    private function sectionMap(string $text): array
    {
        if ($text === '' || preg_match_all(self::SECTION_HEADING_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $sections = [];

        foreach ($matches[0] as $index => $headingMatch) {
            $number = strtoupper((string) $matches[1][$index][0]);
            $start = (int) $headingMatch[1];
            $nextStart = isset($matches[0][$index + 1]) ? (int) $matches[0][$index + 1][1] : strlen($text);
            $sections[$number] = trim(substr($text, $start, $nextStart - $start));
        }

        return $sections;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function diffMatches(string $left, string $right, string $pattern, string $label): array
    {
        $leftMatches = $this->uniqueMatches($left, $pattern);
        $rightMatches = $this->uniqueMatches($right, $pattern);
        $changes = [];

        foreach ($leftMatches as $value) {
            if (! in_array($value, $rightMatches, true)) {
                $changes[] = [
                    'change' => 'removed',
                    'type' => $label,
                    'value' => $value,
                ];
            }
        }

        foreach ($rightMatches as $value) {
            if (! in_array($value, $leftMatches, true)) {
                $changes[] = [
                    'change' => 'added',
                    'type' => $label,
                    'value' => $value,
                ];
            }
        }

        return $changes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function diffLineMatches(string $left, string $right, string $pattern, string $label): array
    {
        $leftLines = $this->matchingLines($left, $pattern);
        $rightLines = $this->matchingLines($right, $pattern);
        $changes = [];

        foreach ($leftLines as $line) {
            if (! in_array($line, $rightLines, true)) {
                $changes[] = [
                    'change' => 'removed',
                    'type' => $label,
                    'line' => $line,
                ];
            }
        }

        foreach ($rightLines as $line) {
            if (! in_array($line, $leftLines, true)) {
                $changes[] = [
                    'change' => 'added',
                    'type' => $label,
                    'line' => $line,
                ];
            }
        }

        return $changes;
    }

    /**
     * @return list<string>
     */
    private function uniqueMatches(string $text, string $pattern): array
    {
        if ($text === '' || preg_match_all($pattern, $text, $matches) === false) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn (string $match): string => trim($match),
            $matches[0],
        )));
    }

    /**
     * @return list<string>
     */
    private function matchingLines(string $text, string $pattern): array
    {
        $lines = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            if (preg_match($pattern, $line) === 1) {
                $lines[] = trim($line);
            }
        }

        return array_values(array_unique($lines));
    }

    private function normalize(string $text): string
    {
        return preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
    }
}
