<?php

namespace App\Services\Documents;

use App\DTO\Documents\DocumentChunk;

class DocumentChunker
{
    private const SECTION_PATTERN = '/^(?:SECTION|Sec\.|Article|ARTICLE|CHAPTER)\s+/i';

    private const MARKDOWN_HEADING_PATTERN = '/^#{1,6}\s+.+/';

    /**
     * @return list<DocumentChunk>
     */
    public function chunk(string $text): array
    {
        $normalized = trim(str_replace("\r\n", "\n", $text));

        if ($normalized === '') {
            return [];
        }

        $chunkSizeTokens = (int) config('sentria.ai.chunk_size_tokens', 800);
        $overlapTokens = (int) config('sentria.ai.chunk_overlap_tokens', 120);
        $targetChars = (int) round($chunkSizeTokens * 4);
        $overlapChars = (int) round($overlapTokens * 4);

        $sections = $this->splitSections($normalized);
        $chunks = [];
        $index = 0;

        foreach ($sections as $section) {
            $sectionText = $section['text'];
            $offset = $section['char_start'];
            $cursor = 0;
            $sectionLength = strlen($sectionText);

            while ($cursor < $sectionLength) {
                $sliceLength = min($targetChars, $sectionLength - $cursor);
                $slice = substr($sectionText, $cursor, $sliceLength);
                $charStart = $offset + $cursor;
                $charEnd = $charStart + strlen($slice);

                $chunks[] = new DocumentChunk(
                    text: trim($slice),
                    index: $index,
                    sectionHeading: $section['heading'],
                    sectionNumber: $section['number'],
                    pageNumber: $this->estimatePageNumber($normalized, $charStart),
                    charStart: $charStart,
                    charEnd: $charEnd,
                );

                $index++;

                if ($cursor + $sliceLength >= $sectionLength) {
                    break;
                }

                $cursor += max(1, $sliceLength - $overlapChars);
            }
        }

        return $chunks;
    }

    /**
     * @return list<array{text: string, heading: ?string, number: ?string, char_start: int}>
     */
    private function splitSections(string $text): array
    {
        $lines = explode("\n", $text);
        $sections = [];
        $currentHeading = null;
        $currentNumber = null;
        $buffer = [];
        $charOffset = 0;
        $sectionStart = 0;

        foreach ($lines as $line) {
            $lineLength = strlen($line) + 1;
            $isSection = preg_match(self::SECTION_PATTERN, $line) === 1
                || preg_match(self::MARKDOWN_HEADING_PATTERN, $line) === 1;

            if ($isSection && $buffer !== []) {
                $sections[] = [
                    'text' => implode("\n", $buffer),
                    'heading' => $currentHeading,
                    'number' => $currentNumber,
                    'char_start' => $sectionStart,
                ];
                $buffer = [];
            }

            if ($isSection) {
                $sectionStart = $charOffset;
                $currentHeading = trim($line);
                $currentNumber = $this->extractSectionNumber($line);
            }

            $buffer[] = $line;
            $charOffset += $lineLength;
        }

        $sections[] = [
            'text' => implode("\n", $buffer),
            'heading' => $currentHeading,
            'number' => $currentNumber,
            'char_start' => $sectionStart,
        ];

        return $sections;
    }

    private function extractSectionNumber(string $line): ?string
    {
        if (preg_match('/^(?:SECTION|Sec\.|Article|ARTICLE|CHAPTER)\s+([^\s.:]+)/i', $line, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^#{1,6}\s+(.+)/', $line, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    private function estimatePageNumber(string $fullText, int $charStart): ?int
    {
        if (preg_match_all('/--- Page (\d+) ---/', $fullText, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return null;
        }

        $page = 1;

        foreach ($matches[1] as $index => $match) {
            $markerOffset = $matches[0][$index][1];

            if ($markerOffset <= $charStart) {
                $page = (int) $match[0];
            }
        }

        return $page;
    }
}
