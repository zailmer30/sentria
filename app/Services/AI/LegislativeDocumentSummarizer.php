<?php

namespace App\Services\AI;

use App\Contracts\AI\ChatCompletionService;
use App\Contracts\AI\DocumentSummarizationService;
use App\DTO\AI\ChatMessage;
use App\DTO\AI\DocumentSummary;
use App\Models\Document;
use App\Models\DocumentMetadata;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentAccessService;
use App\Services\Documents\DocumentTextStore;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use JsonException;

class LegislativeDocumentSummarizer implements DocumentSummarizationService
{
    private const SUMMARY_KEY = 'ai_summary';

    private const MAX_TEXT_CHARS = 12000;

    public function __construct(
        private readonly ChatCompletionService $chat,
        private readonly PromptLoader $prompts,
        private readonly AuditLogger $audit,
        private readonly DocumentAccessService $access,
        private readonly DocumentTextStore $textStore,
    ) {}

    public function summarize(User $user, Document $document, bool $refresh = false): DocumentSummary
    {
        if (! $user->can('ai.summarize')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (! $this->access->userCanView($user, $document)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (! $refresh) {
            $existing = $this->loadStoredSummary($document);

            if ($existing instanceof DocumentSummary) {
                return $existing;
            }
        }

        $document->loadMissing('currentVersion');
        $version = $document->currentVersion;

        abort_unless($version !== null, 422, 'Document has no current version to summarize.');

        $text = trim((string) $this->textStore->get($version));

        abort_if($text === '', 422, 'Document text is not available for summarization.');

        $truncated = Str::limit($text, self::MAX_TEXT_CHARS, '… [truncated]');
        $systemPrompt = $this->prompts->load('summarization_system');

        $chatResult = $this->chat->complete(
            $systemPrompt,
            [new ChatMessage('user', "Document text:\n\n{$truncated}")],
            [
                'mode' => 'summarize',
                'document_text' => $truncated,
            ],
        );

        $summary = $this->parseSummary($chatResult->content, $chatResult->model);

        DocumentMetadata::query()->updateOrCreate(
            [
                'document_id' => $document->getKey(),
                'key' => self::SUMMARY_KEY,
            ],
            [
                'value' => $summary->executiveSummary,
                'value_json' => $summary->toMetadataJson(),
                'source' => 'ai',
                'confidence' => null,
            ],
        );

        $this->audit->record(
            event: 'ai.summarize',
            category: 'ai',
            auditable: $document,
            actor: $user,
            context: [
                'document_id' => $document->getKey(),
                'document_version_id' => $version->getKey(),
                'model' => $chatResult->model,
                'usage' => [
                    'input_tokens' => $chatResult->inputTokens,
                    'output_tokens' => $chatResult->outputTokens,
                ],
                'refreshed' => $refresh,
            ],
            message: 'AI document summary generated.',
        );

        return $summary;
    }

    public function loadStoredSummary(Document $document): ?DocumentSummary
    {
        $metadata = DocumentMetadata::query()
            ->where('document_id', $document->getKey())
            ->where('key', self::SUMMARY_KEY)
            ->where('source', 'ai')
            ->first();

        if ($metadata === null || ! is_array($metadata->value_json)) {
            return null;
        }

        return DocumentSummary::fromMetadataJson($metadata->value_json);
    }

    private function parseSummary(string $content, string $model): DocumentSummary
    {
        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode(trim($content), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $decoded = [
                'executive_summary' => trim($content),
                'purpose' => '',
                'key_provisions' => [],
                'important_dates' => [],
                'financial_info' => null,
                'affected_offices' => [],
                'related_docs_hints' => [],
                'potential_issues' => [],
            ];
        }

        $summary = DocumentSummary::fromMetadataJson($decoded, $model);
        $generatedAt = now()->toIso8601String();

        return new DocumentSummary(
            executiveSummary: $summary->executiveSummary,
            purpose: $summary->purpose,
            keyProvisions: $summary->keyProvisions,
            importantDates: $summary->importantDates,
            financialInfo: $summary->financialInfo,
            affectedOffices: $summary->affectedOffices,
            relatedDocsHints: $summary->relatedDocsHints,
            potentialIssues: $summary->potentialIssues,
            model: $model,
            generatedAt: $generatedAt,
        );
    }
}
