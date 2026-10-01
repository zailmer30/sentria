<?php

use App\Models\Transcript;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_segment_edits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('transcript_id')->constrained('transcripts')->cascadeOnDelete();
            $table->unsignedBigInteger('segment_index');
            $table->string('field', 20);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignUlid('old_speaker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('new_speaker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['transcript_id', 'segment_index']);
            $table->index(['transcript_id', 'created_at']);
        });

        Transcript::query()
            ->whereNotNull('segments')
            ->orderBy('id')
            ->each(function (Transcript $transcript): void {
                $segments = $transcript->segments;

                if (! is_array($segments) || $segments === []) {
                    return;
                }

                $changed = false;

                foreach ($segments as $index => $segment) {
                    if (! is_array($segment)) {
                        continue;
                    }

                    if (! array_key_exists('original_text', $segment)) {
                        $segment['original_text'] = (string) ($segment['text'] ?? '');
                        $changed = true;
                    }

                    if (! array_key_exists('original_speaker', $segment)) {
                        $segment['original_speaker'] = isset($segment['speaker']) ? (string) $segment['speaker'] : null;
                        $changed = true;
                    }

                    if (! array_key_exists('original_speaker_id', $segment)) {
                        $segment['original_speaker_id'] = isset($segment['speaker_id']) ? (string) $segment['speaker_id'] : null;
                        $changed = true;
                    }

                    if (! array_key_exists('original_attributed', $segment)) {
                        $segment['original_attributed'] = array_key_exists('attributed', $segment)
                            ? (bool) $segment['attributed']
                            : true;
                        $changed = true;
                    }

                    $segments[$index] = $segment;
                }

                if ($changed) {
                    $transcript->update(['segments' => array_values($segments)]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_segment_edits');
    }
};
