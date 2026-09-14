<?php

namespace Database\Seeders;

use App\Enums\Confidentiality;
use App\Models\AiCitation;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Document;
use App\Models\DocumentEmbedding;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo AI conversations and embeddings. Vectors here are random unit vectors,
 * not real embeddings; they exist so retrieval queries and the citation UI
 * have something to run against before the AI slices land.
 */
class AiDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedEmbeddings();
        $this->seedConversation();
    }

    private function seedEmbeddings(): void
    {
        Document::query()
            ->with('currentVersion')
            ->whereHas('currentVersion')
            ->take(6)
            ->get()
            ->each(function (Document $document): void {
                $version = $document->currentVersion;

                if ($version === null) {
                    return;
                }

                foreach (range(0, 2) as $chunkIndex) {
                    DocumentEmbedding::factory()->create([
                        'document_id' => $document->getKey(),
                        'document_version_id' => $version->getKey(),
                        'chunk_index' => $chunkIndex,
                        'page_number' => $chunkIndex + 1,
                        // Mirrors the document so the authorization filter can
                        // run inside the vector query.
                        'confidentiality' => $document->confidentiality ?? Confidentiality::Internal->value,
                        'is_public' => (bool) $document->is_public,
                    ]);
                }
            });
    }

    private function seedConversation(): void
    {
        $secretariat = User::query()->where('email', 'secretariat@sentria.test')->firstOrFail();
        $document = Document::query()->whereNotNull('reference_number')->first();

        if ($document === null) {
            return;
        }

        $conversation = AiConversation::factory()->create([
            'user_id' => $secretariat->getKey(),
            'title' => 'Summary of '.$document->reference_number,
            'message_count' => 2,
        ]);

        AiMessage::factory()->create([
            'ai_conversation_id' => $conversation->getKey(),
            'role' => 'user',
            'content' => "Summarize {$document->reference_number} and list the offices it affects.",
        ]);

        $answer = AiMessage::factory()->assistant()->create([
            'ai_conversation_id' => $conversation->getKey(),
            'content' => 'Draft summary generated from the registered document text. '
                .'Verify every statement against the original before relying on it in official work.',
        ]);

        AiCitation::factory()->create([
            'ai_message_id' => $answer->getKey(),
            'document_id' => $document->getKey(),
            'rank' => 1,
            'quote' => 'Excerpt from the registered document supporting the summary above.',
        ]);
    }
}
