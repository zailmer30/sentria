<?php

namespace Database\Factories;

use App\Models\LegislativeSession;
use App\Models\Transcript;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transcript>
 */
class TranscriptFactory extends Factory
{
    protected $model = Transcript::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $started = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'session_id' => LegislativeSession::factory(),
            'source' => 'live_stt',
            'status' => 'completed',
            'language' => 'en',
            'provider' => 'whisper-compatible',
            'model' => 'whisper-1',
            'average_confidence' => fake()->randomFloat(4, 0.75, 0.98),
            'duration_seconds' => fake()->numberBetween(1800, 14400),
            'full_text' => fake()->paragraphs(10, true),
            'segments' => collect(range(1, 5))->map(function (int $i): array {
                $text = fake()->sentence(14);
                $speaker = 'Speaker '.$i;

                return [
                    'index' => $i,
                    'start' => $i * 30,
                    'end' => ($i + 1) * 30,
                    'speaker' => $speaker,
                    'text' => $text,
                    'original_text' => $text,
                    'original_speaker' => $speaker,
                    'original_speaker_id' => null,
                    'original_attributed' => true,
                    'confidence' => fake()->randomFloat(4, 0.7, 0.99),
                ];
            })->all(),
            'started_at' => $started,
            'ended_at' => (clone $started)->modify('+2 hours'),
            'created_by' => User::factory(),
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'processing',
            'full_text' => null,
            'segments' => null,
            'ended_at' => null,
        ]);
    }
}
