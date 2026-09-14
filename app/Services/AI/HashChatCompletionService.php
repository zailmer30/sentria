<?php

namespace App\Services\AI;

use App\Contracts\AI\ChatCompletionService;
use App\DTO\AI\ChatMessage;
use App\DTO\AI\ChatResult;
use App\DTO\AI\ContextChunk;
use App\Support\AI\InsufficientEvidence;
use Illuminate\Support\Str;

/**
 * Deterministic offline chat for CI/dev when no API key is configured.
 */
class HashChatCompletionService implements ChatCompletionService
{
    public function complete(string $system, array $messages, array $options = []): ChatResult
    {
        $mode = (string) ($options['mode'] ?? 'rag');

        if ($mode === 'summarize') {
            return $this->summarizeDocument($messages, $options);
        }

        /** @var list<ContextChunk> $chunks */
        $chunks = $options['context_chunks'] ?? [];

        if ($chunks === []) {
            return new ChatResult(
                content: InsufficientEvidence::PHRASE,
                model: $this->modelName(),
                inputTokens: $this->estimateTokens($system, $messages),
                outputTokens: str_word_count(InsufficientEvidence::PHRASE),
            );
        }

        if ($this->shouldRefuseInjectionAttempt($messages, $chunks)) {
            return new ChatResult(
                content: InsufficientEvidence::PHRASE,
                model: $this->modelName(),
                inputTokens: $this->estimateTokens($system, $messages),
                outputTokens: str_word_count(InsufficientEvidence::PHRASE),
            );
        }

        $content = $this->sanitizeProtectedStrings($this->synthesizeRagAnswer($messages, $chunks));

        return new ChatResult(
            content: $content,
            model: $this->modelName(),
            inputTokens: $this->estimateTokens($system, $messages),
            outputTokens: str_word_count($content),
        );
    }

    public function modelName(): string
    {
        return 'hash-local-chat';
    }

    /**
     * @param  list<ChatMessage>  $messages
     * @param  list<ContextChunk>  $chunks
     */
    private function shouldRefuseInjectionAttempt(array $messages, array $chunks): bool
    {
        $question = $this->latestUserMessage($messages);

        if (! $this->questionSeeksProtectedSecrets($question)) {
            return false;
        }

        foreach ($chunks as $chunk) {
            if ($this->chunkContainsInjectionPattern($chunk->chunkText)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<ChatMessage>  $messages
     */
    private function latestUserMessage(array $messages): string
    {
        $lastUser = collect($messages)
            ->reverse()
            ->first(fn (ChatMessage $message): bool => $message->role === 'user');

        return $lastUser instanceof ChatMessage ? $lastUser->content : '';
    }

    private function questionSeeksProtectedSecrets(string $question): bool
    {
        return preg_match('/\b(secrets?|api[\s_-]?keys?|passwords?|credentials?|system[\s_-]?prompt)\b/i', $question) === 1;
    }

    private function chunkContainsInjectionPattern(string $text): bool
    {
        return preg_match('/ignore\s+(all\s+)?previous\s+instructions/i', $text) === 1
            || preg_match('/reveal\s+(all\s+)?secrets/i', $text) === 1;
    }

    private function sanitizeProtectedStrings(string $content): string
    {
        /** @var list<string> $protected */
        $protected = config('sentria.ai.protected_response_strings', []);

        foreach ($protected as $secret) {
            if (is_string($secret) && $secret !== '' && str_contains($content, $secret)) {
                return InsufficientEvidence::PHRASE;
            }
        }

        return $content;
    }

    /**
     * @param  list<ChatMessage>  $messages
     * @param  list<ContextChunk>  $chunks
     */
    private function synthesizeRagAnswer(array $messages, array $chunks): string
    {
        $lastUser = collect($messages)
            ->reverse()
            ->first(fn (ChatMessage $message): bool => $message->role === 'user');

        $questionText = $lastUser instanceof ChatMessage ? $lastUser->content : '';

        $lines = [
            'Based on the authorized legislative records retrieved for your question, here is what the sources indicate:',
            '',
        ];

        foreach ($chunks as $chunk) {
            $location = $this->formatLocation($chunk);
            $excerpt = Str::limit(trim($chunk->chunkText), 180, '…');
            $lines[] = "[{$chunk->index}] {$chunk->documentTitle}{$location}: {$excerpt}";
        }

        $lines[] = '';
        $lines[] = $this->buildAnswerParagraph($questionText, $chunks);

        return implode("\n", $lines);
    }

    /**
     * @param  list<ContextChunk>  $chunks
     */
    private function buildAnswerParagraph(string $question, array $chunks): string
    {
        $citationRefs = implode(', ', array_map(
            static fn (ContextChunk $chunk): string => "[{$chunk->index}]",
            $chunks,
        ));

        $first = $chunks[0];
        $snippet = Str::limit(trim($first->chunkText), 120, '…');

        if ($question !== '') {
            return "Regarding \"{$question}\", the most relevant passage is in {$first->documentTitle} "
                ."{$this->formatLocation($first)} {$citationRefs}: \"{$snippet}\"";
        }

        return "The retrieved sources {$citationRefs} contain: \"{$snippet}\"";
    }

    private function formatLocation(ContextChunk $chunk): string
    {
        $parts = [];

        if ($chunk->pageNumber !== null) {
            $parts[] = "page {$chunk->pageNumber}";
        }

        if ($chunk->sectionNumber !== null) {
            $parts[] = "Section {$chunk->sectionNumber}";
        } elseif ($chunk->sectionHeading !== null) {
            $parts[] = $chunk->sectionHeading;
        }

        if ($parts === []) {
            return '';
        }

        return ' ('.implode(', ', $parts).')';
    }

    /**
     * @param  list<ChatMessage>  $messages
     * @param  array<string, mixed>  $options
     */
    private function summarizeDocument(array $messages, array $options): ChatResult
    {
        $text = trim((string) ($options['document_text'] ?? ''));

        if ($text === '') {
            $lastUser = collect($messages)
                ->reverse()
                ->first(fn (ChatMessage $message): bool => $message->role === 'user');

            $text = $lastUser instanceof ChatMessage ? $lastUser->content : '';
        }

        $summary = $this->heuristicSummary($text);

        return new ChatResult(
            content: json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            model: $this->modelName(),
            inputTokens: str_word_count($text),
            outputTokens: str_word_count(json_encode($summary) ?: ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function heuristicSummary(string $text): array
    {
        $lines = preg_split('/\R+/', $text) ?: [];
        $sections = [];
        $currentSection = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^SECTION\s+(\d+[A-Za-z]?)\.?\s*(.*)$/i', $trimmed, $matches) === 1) {
                $currentSection = 'Section '.$matches[1].($matches[2] !== '' ? ': '.$matches[2] : '');
                $sections[] = $currentSection;

                continue;
            }

            if ($currentSection === null && count($sections) < 3) {
                $sections[] = Str::limit($trimmed, 120, '…');
            }
        }

        preg_match_all('/\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4}\b/i', $text, $dateMatches);
        preg_match_all('/(?:PHP|₱|P)\s?[\d,]+(?:\.\d{2})?|\b\d{1,3}(?:,\d{3})+\b/', $text, $moneyMatches);

        $financial = null;

        if ($moneyMatches[0] !== []) {
            $financial = 'Identified amounts: '.implode('; ', array_slice(array_unique($moneyMatches[0]), 0, 5));
        }

        $purposeLine = collect($lines)
            ->first(fn (string $line): bool => str_contains(strtolower($line), 'purpose')
                || str_contains(strtolower($line), 'short title')
                || str_contains(strtolower($line), 'shall be known'));

        return [
            'executive_summary' => Str::limit(trim($text), 280, '…'),
            'purpose' => $purposeLine !== null ? Str::limit(trim($purposeLine), 200, '…') : 'Purpose not explicitly labeled in the extracted text.',
            'key_provisions' => array_values(array_slice(array_unique($sections), 0, 6)),
            'important_dates' => array_values(array_slice(array_unique($dateMatches[0]), 0, 5)),
            'financial_info' => $financial,
            'affected_offices' => $this->extractOffices($text),
            'related_docs_hints' => $this->extractRelatedHints($text),
            'potential_issues' => $this->extractPotentialIssues($text),
        ];
    }

    /**
     * @return list<string>
     */
    private function extractOffices(string $text): array
    {
        preg_match_all('/\b(?:Office of the|Department of|Municipal|Provincial|City)\s+[A-Z][A-Za-z\s]+/', $text, $matches);

        return array_values(array_slice(array_unique(array_map('trim', $matches[0])), 0, 5));
    }

    /**
     * @return list<string>
     */
    private function extractRelatedHints(string $text): array
    {
        preg_match_all('/\b(?:Ordinance|Resolution)\s+(?:No\.?\s*)?[\w-]+/i', $text, $matches);

        return array_values(array_slice(array_unique($matches[0]), 0, 5));
    }

    /**
     * @return list<string>
     */
    private function extractPotentialIssues(string $text): array
    {
        $issues = [];

        if (preg_match('/confidential|restricted|classified/i', $text) === 1) {
            $issues[] = 'Document references confidentiality or restricted access language.';
        }

        if (preg_match('/appropriation|budget|fund/i', $text) === 1) {
            $issues[] = 'Contains appropriation or budget language requiring fiscal review.';
        }

        if (preg_match('/penalt|fine|imprison/i', $text) === 1) {
            $issues[] = 'Contains penalty or enforcement language.';
        }

        return $issues;
    }

    /**
     * @param  list<ChatMessage>  $messages
     */
    private function estimateTokens(string $system, array $messages): int
    {
        $words = str_word_count($system);

        foreach ($messages as $message) {
            $words += str_word_count($message->content);
        }

        return max(1, (int) round($words * 1.3));
    }
}
