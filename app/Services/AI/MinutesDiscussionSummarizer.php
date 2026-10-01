<?php

namespace App\Services\AI;

use App\Contracts\AI\ChatCompletionService;
use App\DTO\AI\ChatMessage;
use App\DTO\AI\MinutesDiscussionResult;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Support\AI\InsufficientEvidence;
use Throwable;

class MinutesDiscussionSummarizer
{
    public const PREFIX = 'Discussion (AI-suggested):';

    private const CHUNK_CHARS = 8000;

    public function __construct(
        private readonly ChatCompletionService $chat,
        private readonly PromptLoader $prompts,
    ) {}

    /**
     * @param  array<string, list<array{text: string, speaker: string|null, attributed: bool, language: string|null}>>  $speechByItem
     */
    public function summarize(LegislativeSession $session, array $speechByItem): MinutesDiscussionResult
    {
        $usable = [];

        foreach ($speechByItem as $itemId => $turns) {
            if ($turns !== []) {
                $usable[(string) $itemId] = $turns;
            }
        }

        if ($usable === []) {
            return new MinutesDiscussionResult([], false, null, null);
        }

        if (! filled(config('sentria.ai.api_key'))) {
            return new MinutesDiscussionResult([], true, 'chat_unavailable', null);
        }

        try {
            $paragraphs = [];
            $model = null;

            foreach ($usable as $itemId => $turns) {
                $item = $session->agendaItems->first(
                    fn (AgendaItem $agendaItem): bool => (string) $agendaItem->getKey() === $itemId,
                );
                $title = $item instanceof AgendaItem
                    ? trim(($item->item_number !== null && $item->item_number !== '' ? $item->item_number.'. ' : '').$item->title)
                    : $itemId;
                $paragraph = $this->summarizeTurns($title, $turns);

                if ($paragraph === null) {
                    continue;
                }

                $paragraphs[$itemId] = self::PREFIX.' '.$paragraph['text'];
                $model = $paragraph['model'];
            }

            return new MinutesDiscussionResult($paragraphs, false, null, $model);
        } catch (Throwable) {
            return new MinutesDiscussionResult([], true, 'chat_failed', null);
        }
    }

    /**
     * @param  list<array{text: string, speaker: string|null, attributed: bool, language: string|null}>  $turns
     * @return array{text: string, model: string}|null
     */
    private function summarizeTurns(string $title, array $turns): ?array
    {
        $speech = $this->formatTurns($turns);
        $chunks = $this->chunks($speech);
        $partials = [];
        $model = null;

        foreach ($chunks as $chunk) {
            $result = $this->ask($title, $chunk, fold: false);

            if ($result === null) {
                continue;
            }

            $partials[] = $result['text'];
            $model = $result['model'];
        }

        if ($partials === [] || $model === null) {
            return null;
        }

        if (count($partials) === 1) {
            return ['text' => $partials[0], 'model' => $model];
        }

        $folded = $this->ask($title, implode("\n\n", $partials), fold: true);

        return $folded ?? ['text' => $partials[0], 'model' => $model];
    }

    /**
     * @return array{text: string, model: string}|null
     */
    private function ask(string $title, string $speech, bool $fold): ?array
    {
        $instruction = $fold
            ? "Combine these partial discussion notes for agenda item {$title} into one short paragraph. Same rules. Output only the paragraph or nothing.\n\n{$speech}"
            : "Agenda item: {$title}\n\nSpeech:\n{$speech}";

        $result = $this->chat->complete(
            $this->prompts->load('minutes_discussion_system'),
            [new ChatMessage('user', $instruction)],
            [
                'mode' => 'minutes_discussion',
                'temperature' => 0.2,
            ],
        );

        $text = $this->normalizeParagraph($result->content);

        if ($text === null) {
            return null;
        }

        return [
            'text' => $text,
            'model' => $result->model,
        ];
    }

    /**
     * @param  list<array{text: string, speaker: string|null, attributed: bool, language: string|null}>  $turns
     */
    private function formatTurns(array $turns): string
    {
        $lines = [];

        foreach ($turns as $turn) {
            $label = $turn['attributed'] && is_string($turn['speaker']) && $turn['speaker'] !== ''
                ? $turn['speaker']
                : 'Unassigned';
            $lines[] = $label.': '.$turn['text'];
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function chunks(string $speech): array
    {
        $length = mb_strlen($speech);

        if ($length <= self::CHUNK_CHARS) {
            return [$speech];
        }

        $chunks = [];

        for ($offset = 0; $offset < $length; $offset += self::CHUNK_CHARS) {
            $chunks[] = mb_substr($speech, $offset, self::CHUNK_CHARS);
        }

        return $chunks;
    }

    private function normalizeParagraph(string $content): ?string
    {
        $text = trim($content);
        $text = trim($text, "\"'`");

        if (str_starts_with($text, self::PREFIX)) {
            $text = trim(substr($text, strlen(self::PREFIX)));
        }

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '' || strcasecmp($text, InsufficientEvidence::PHRASE) === 0) {
            return null;
        }

        return $text;
    }
}
