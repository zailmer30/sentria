<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\Document;
use Illuminate\Validation\ValidationException;

class MeasureSubmissionValidator
{
    /**
     * Fields a proposed ordinance or resolution must carry before it leaves the secretary.
     *
     * @return array<string, string>
     */
    public function missingFields(Document $document): array
    {
        if (! $document->document_type->isMeasure()) {
            return [];
        }

        $missing = [];

        if ($document->title === '') {
            $missing['title'] = 'A number and title are required.';
        }

        if ($document->reference_number === null || $document->reference_number === '') {
            $missing['reference_number'] = 'A measure number is required.';
        }

        if ($document->enacting_clause === null || trim((string) $document->enacting_clause) === '') {
            $missing['enacting_clause'] = 'An enacting or ordaining clause is required.';
        }

        if ($this->requiresExplanatoryNote($document->document_type)
            && ($document->explanatory_note === null || trim((string) $document->explanatory_note) === '')) {
            $missing['explanatory_note'] = 'An explanatory note is required for ordinances.';
        }

        if ($document->author_id === null && ($document->external_author === null || $document->external_author === '')) {
            $missing['author_id'] = 'The measure must be signed by an author or proponent.';
        }

        return $missing;
    }

    /**
     * @throws ValidationException
     */
    public function assertReadyForSecretary(Document $document): void
    {
        $missing = $this->missingFields($document);

        if ($missing === []) {
            return;
        }

        throw ValidationException::withMessages($missing);
    }

    public function requiresExplanatoryNote(DocumentType $type): bool
    {
        return in_array($type, [DocumentType::ProposedOrdinance, DocumentType::Ordinance], true);
    }
}
