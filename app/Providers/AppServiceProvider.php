<?php

namespace App\Providers;

use App\Contracts\AI\ChatCompletionService;
use App\Contracts\AI\ConsistencyCheckService;
use App\Contracts\AI\DocumentSummarizationService;
use App\Contracts\AI\EmbeddingService;
use App\Contracts\AI\LegislativeSearchService;
use App\Contracts\AI\MinutesGenerationService;
use App\Contracts\AI\RAGService;
use App\Contracts\AI\RelatedDocumentService;
use App\Contracts\AI\TranscriptionService;
use App\Contracts\Documents\DocumentComparisonService;
use App\Contracts\Malware\MalwareScanner;
use App\Contracts\OCR\OcrService;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Notifications\Channels\DatabaseChannel;
use App\Services\AI\EmbeddingRelatedDocumentService;
use App\Services\AI\HashChatCompletionService;
use App\Services\AI\HashEmbeddingService;
use App\Services\AI\HeuristicConsistencyCheckService;
use App\Services\AI\LegislativeDocumentSummarizer;
use App\Services\AI\LegislativeMinutesGenerator;
use App\Services\AI\LegislativeRagService;
use App\Services\AI\OpenAiCompatibleChatService;
use App\Services\AI\OpenAiCompatibleEmbeddingService;
use App\Services\AI\PromptLoader;
use App\Services\AI\StructuredDocumentComparisonService;
use App\Services\AI\TranscriptionServiceFactory;
use App\Services\AI\VectorLegislativeSearchService;
use App\Services\Audit\AuditChainHasher;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentAccessService;
use App\Services\Documents\PlainTextDocumentComparisonService;
use App\Services\Malware\NullMalwareScanner;
use App\Services\Notifications\InAppNotifier;
use App\Services\OCR\NullOcrService;
use App\Services\OCR\TesseractOcrService;
use App\Services\Portal\PublicPortal;
use App\Services\Portal\PublicPortalSearchService;
use App\Services\Workflow\GuardedStateTransition;
use App\Support\SameOriginDevVite;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Notifications\Channels\DatabaseChannel as LaravelDatabaseChannel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! in_array('PHP_INI_SCAN_DIR', ServeCommand::$passthroughVariables, true)) {
            ServeCommand::$passthroughVariables[] = 'PHP_INI_SCAN_DIR';
        }

        $this->app->singleton(Vite::class, SameOriginDevVite::class);

        $this->app->singleton(AuditChainHasher::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(GuardedStateTransition::class);
        $this->app->singleton(DocumentAccessService::class);
        $this->app->singleton(PublicPortal::class);
        $this->app->singleton(PublicPortalSearchService::class);
        $this->app->singleton(InAppNotifier::class);
        $this->app->bind(LaravelDatabaseChannel::class, DatabaseChannel::class);

        $this->app->bind(MalwareScanner::class, function (): MalwareScanner {
            $driver = config('sentria.malware_scanning.driver', 'null');

            return match ($driver) {
                'null', null => new NullMalwareScanner,
                default => new NullMalwareScanner,
            };
        });

        $this->app->bind(DocumentComparisonService::class, PlainTextDocumentComparisonService::class);

        $this->app->singleton(PromptLoader::class);

        $this->app->bind(EmbeddingService::class, function (): EmbeddingService {
            $model = strtolower((string) config('sentria.ai.embedding_model', ''));

            if (in_array($model, ['hash', 'hash-local'], true) || blank(config('sentria.ai.api_key'))) {
                return new HashEmbeddingService;
            }

            return new OpenAiCompatibleEmbeddingService;
        });

        $this->app->bind(ChatCompletionService::class, function (): ChatCompletionService {
            $apiKey = config('sentria.ai.api_key');

            if (filled($apiKey)) {
                return new OpenAiCompatibleChatService;
            }

            return new HashChatCompletionService;
        });

        $this->app->bind(OcrService::class, function (): OcrService {
            $driver = config('sentria.ocr.driver', 'null');

            return match ($driver) {
                'tesseract' => new TesseractOcrService(
                    new NullOcrService,
                    (string) config('sentria.ocr.tesseract_bin', '/usr/bin/tesseract'),
                    (string) config('sentria.ocr.pdftoppm_bin', '/usr/bin/pdftoppm'),
                ),
                default => new NullOcrService,
            };
        });

        $this->app->bind(LegislativeSearchService::class, VectorLegislativeSearchService::class);
        $this->app->bind(DocumentSummarizationService::class, LegislativeDocumentSummarizer::class);
        $this->app->bind(RAGService::class, LegislativeRagService::class);
        $this->app->bind(RelatedDocumentService::class, EmbeddingRelatedDocumentService::class);
        $this->app->bind(ConsistencyCheckService::class, HeuristicConsistencyCheckService::class);
        $this->app->singleton(StructuredDocumentComparisonService::class);
        $this->app->bind(MinutesGenerationService::class, LegislativeMinutesGenerator::class);

        $this->app->bind(TranscriptionService::class, function (): TranscriptionService {
            return $this->app->make(TranscriptionServiceFactory::class)->make();
        });
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        RateLimiter::for('portal-search', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Browser capture polls state every 3s, heartbeats every 1s and uploads
        // one chunk per utterance. Generous, but still bounded per operator.
        RateLimiter::for('chamber-capture', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perMinute(300)->by($user !== null ? (string) $user->getKey() : $request->ip());
        });

        RateLimiter::for('ai-ask', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perMinute(20)->by($user !== null ? (string) $user->getKey() : $request->ip());
        });

        // AI inherits the authenticated user's permissions. There is no separate
        // AI principal and no Gate::before bypass for AI-originated work.
        Gate::define('ai.acts-as-user', function (User $user): bool {
            return $user->can('ai.use');
        });
    }
}
