<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Organization
    |--------------------------------------------------------------------------
    |
    | Single-LGU deployment. There is no multi-tenancy: one installation
    | serves one legislative body.
    |
    */

    'organization' => [
        'name' => env('SENTRIA_ORG_NAME', 'Sangguniang Panlalawigan'),
        'short_name' => env('SENTRIA_ORG_SHORT_NAME', 'SP'),
        'locality' => env('SENTRIA_ORG_LOCALITY', 'Province of Demo'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | One accent colour for staff chrome and one deep plate colour for hero
    | cards, the chamber floor, the portal plate, and sign-in. Live/red, fonts,
    | and layout stay authored. Env is the fallback until an administrator
    | saves the brand kit.
    |
    */

    'branding' => [
        'accent' => env('SENTRIA_ACCENT', '#0038A8'),
        'plate' => env('SENTRIA_PLATE', '#132042'),
        'plate_pattern' => env('SENTRIA_PLATE_PATTERN', 'authored'),
        'logo_max_kilobytes' => (int) env('SENTRIA_BRAND_LOGO_MAX_KB', 2048),
        'logo_mimes' => ['jpeg', 'jpg', 'png', 'webp'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vite (local+debug only)
    |--------------------------------------------------------------------------
    |
    | The browser loads HMR assets through Laravel (same origin). This origin
    | is the upstream Vite server the proxy talks to — not a public URL.
    |
    */

    'vite' => [
        'dev_server_url' => env('VITE_DEV_SERVER_URL', 'http://127.0.0.1:5173'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quorum
    |--------------------------------------------------------------------------
    |
    | The system reports quorum status. It never decides whether a session may
    | proceed — that judgment belongs to the presiding officer.
    |
    */

    'quorum' => [
        'rule' => env('SENTRIA_QUORUM_RULE', 'majority_of_seated'),
        'fixed_threshold' => env('SENTRIA_QUORUM_FIXED_THRESHOLD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Voting
    |--------------------------------------------------------------------------
    |
    | Electronic voting is not automatically legally binding. Whether it is
    | depends on the organization's own rules of procedure.
    |
    */

    'voting' => [
        'electronic_is_binding' => env('SENTRIA_ELECTRONIC_VOTING_BINDING', false),
        'default_method' => env('SENTRIA_DEFAULT_VOTING_METHOD', 'electronic'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Records retention
    |--------------------------------------------------------------------------
    |
    | Official legislative records are soft-archived. Hard deletion stays off
    | unless two administrators confirm and the action is audited.
    |
    */

    'retention' => [
        'allow_hard_delete' => env('SENTRIA_ALLOW_HARD_DELETE', false),
        'require_dual_admin_confirmation' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Document intake
    |--------------------------------------------------------------------------
    */

    'documents' => [
        'max_upload_size_kb' => env('SENTRIA_MAX_UPLOAD_KB', 51200),
        'allowed_mime_types' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'image/jpeg',
            'image/png',
            'image/tiff',
        ],
        'confidentiality_levels' => ['public', 'internal', 'restricted', 'confidential'],
        // Alphanumeric characters per page below this threshold → treat PDF as a scan.
        'min_chars_per_page' => (int) env('SENTRIA_MIN_CHARS_PER_PAGE', 50),
        'pdftotext_bin' => env('SENTRIA_PDFTOTEXT_BIN', '/usr/bin/pdftotext'),
        'pdfinfo_bin' => env('SENTRIA_PDFINFO_BIN', '/usr/bin/pdfinfo'),
    ],

    'legislation' => [
        // PHP post_max_size / upload_max_filesize must exceed zip + csv (see config/php/conf.d).
        'import_zip_max_kb' => (int) env('SENTRIA_IMPORT_ZIP_MAX_KB', 204800),
        'import_csv_max_kb' => (int) env('SENTRIA_IMPORT_CSV_MAX_KB', 2048),
        'import_max_files' => (int) env('SENTRIA_IMPORT_MAX_FILES', 500),
    ],

    'malware_scanning' => [
        // `null` is the development no-op adapter. Production must set
        // `clamav` or an equivalent documented adapter.
        'driver' => env('MALWARE_SCANNER', 'null'),
        'clamav_socket' => env('CLAMAV_SOCKET', '/var/run/clamav/clamd.ctl'),
        'block_on_scanner_failure' => env('SENTRIA_BLOCK_ON_SCAN_FAILURE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI
    |--------------------------------------------------------------------------
    |
    | AI assists personnel. It never votes, approves, finalizes minutes,
    | determines quorum, publishes, or modifies official records. It always
    | runs with the authenticated user's permissions.
    |
    */

    'ai' => [
        'enabled' => env('AI_ENABLED', false),
        'provider' => env('AI_PROVIDER', 'openai'),
        'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1'),
        'api_key' => env('AI_API_KEY'),
        'chat_model' => env('AI_CHAT_MODEL', 'gpt-4o-mini'),
        // Optional: point embeddings at a different OpenAI-compatible host than chat.
        'embedding_base_url' => env('AI_EMBEDDING_BASE_URL', env('AI_BASE_URL', 'https://api.openai.com/v1')),
        'embedding_api_key' => env('AI_EMBEDDING_API_KEY') ?? env('AI_API_KEY'),
        'embedding_model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        // The pgvector column dimension is fixed at 1536 in migration 001300.
        // Changing this value requires a new migration to alter the vector column
        // and rebuild the HNSW index.
        'embedding_dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 1536),
        // OpenAI text-embedding-3-* accepts `dimensions`. Gemini/Groq reject it.
        // Leave unset for auto: send only when the model name starts with text-embedding-3-.
        'embedding_send_dimensions' => env('AI_EMBEDDING_SEND_DIMENSIONS'),
        'chunk_size_tokens' => 800,
        'chunk_overlap_tokens' => 120,
        'retrieval_top_k' => 8,
        // Strings that must never appear in model output (tests / optional production guard).
        'protected_response_strings' => array_values(array_filter([
            env('SENTRIA_PROTECTED_RESPONSE_SECRET'),
        ])),
    ],

    'ocr' => [
        // Use `tesseract` for scanned PDFs/images. `null` only stubs image OCR for tests.
        'driver' => env('OCR_DRIVER', 'null'),
        'tesseract_bin' => env('OCR_TESSERACT_BIN', '/usr/bin/tesseract'),
        'pdftoppm_bin' => env('OCR_PDFTOPPM_BIN', '/usr/bin/pdftoppm'),
    ],

    'transcription' => [
        // Single: whisper | qwen3 | elevenlabs | fake | null
        // Failover: comma-separated (qwen3,elevenlabs,whisper) or driver=failover + TRANSCRIPTION_FAILOVER
        'driver' => env('TRANSCRIPTION_DRIVER', 'null'),
        'failover' => env('TRANSCRIPTION_FAILOVER', 'qwen3,whisper'),
        'failover_failures' => (int) env('TRANSCRIPTION_FAILOVER_FAILURES', 3),
        'failover_cooldown_seconds' => (int) env('TRANSCRIPTION_FAILOVER_COOLDOWN', 60),
        // Groq / OpenAI-compatible default (used by the `whisper` provider)
        'model' => env('TRANSCRIPTION_MODEL', 'whisper-1'),
        'language_mode' => env('TRANSCRIPTION_LANGUAGE_MODE', 'auto'),
        'base_url' => env('TRANSCRIPTION_BASE_URL', env('AI_BASE_URL', 'https://api.openai.com/v1')),
        'api_key' => env('TRANSCRIPTION_API_KEY', env('AI_API_KEY')),
        'timeout' => (int) env('TRANSCRIPTION_TIMEOUT', 120),
        'connect_timeout' => (int) env('TRANSCRIPTION_CONNECT_TIMEOUT', 5),
        'prompt_path' => resource_path('prompts/transcription_prompt.md'),
        'low_confidence' => (float) env('TRANSCRIPTION_LOW_CONFIDENCE', 0.4),
        'providers' => [
            'qwen3' => [
                'adapter' => 'whisper',
                'base_url' => env('TRANSCRIPTION_QWEN3_BASE_URL', 'http://127.0.0.1:8000/v1'),
                'api_key' => env('TRANSCRIPTION_QWEN3_API_KEY', 'local'),
                'model' => env('TRANSCRIPTION_QWEN3_MODEL', 'Qwen/Qwen3-ASR-1.7B'),
                'timeout' => (int) env('TRANSCRIPTION_QWEN3_TIMEOUT', 15),
                'connect_timeout' => (int) env('TRANSCRIPTION_QWEN3_CONNECT_TIMEOUT', 2),
            ],
            'whisper' => [
                'adapter' => 'whisper',
                'base_url' => env('TRANSCRIPTION_BASE_URL', env('AI_BASE_URL', 'https://api.openai.com/v1')),
                'api_key' => env('TRANSCRIPTION_API_KEY', env('AI_API_KEY')),
                'model' => env('TRANSCRIPTION_MODEL', 'whisper-1'),
                'timeout' => (int) env('TRANSCRIPTION_TIMEOUT', 120),
                'connect_timeout' => (int) env('TRANSCRIPTION_CONNECT_TIMEOUT', 5),
            ],
            'elevenlabs' => [
                'adapter' => 'elevenlabs',
                'base_url' => env('TRANSCRIPTION_ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io'),
                'api_key' => env('TRANSCRIPTION_ELEVENLABS_API_KEY'),
                'model' => env('TRANSCRIPTION_ELEVENLABS_MODEL', 'scribe_v2'),
                'timeout' => (int) env('TRANSCRIPTION_ELEVENLABS_TIMEOUT', 120),
                'connect_timeout' => (int) env('TRANSCRIPTION_ELEVENLABS_CONNECT_TIMEOUT', 5),
                'keyterms' => filter_var(env('TRANSCRIPTION_ELEVENLABS_KEYTERMS', false), FILTER_VALIDATE_BOOLEAN),
                'diarize_upload' => filter_var(env('TRANSCRIPTION_ELEVENLABS_DIARIZE_UPLOAD', true), FILTER_VALIDATE_BOOLEAN),
                'audio_events_upload' => filter_var(env('TRANSCRIPTION_ELEVENLABS_AUDIO_EVENTS_UPLOAD', true), FILTER_VALIDATE_BOOLEAN),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Chamber capture
    |--------------------------------------------------------------------------
    |
    | Default feed is the mixer mix (secretariat assigns speakers). Per-seat
    | mics remain available when ICT wires direct-outs into one multi-channel
    | interface. The capture daemon is an external Sanctum consumer. Raw
    | audio is pruned; transcript text is retained.
    |
    */

    'chamber' => [
        'machine_email' => env('CHAMBER_CAPTURE_EMAIL', 'chamber-capture@sentria.local'),
        'min_chunk_bytes' => (int) env('CHAMBER_MIN_CHUNK_BYTES', 2048),
        'min_duration_ms' => (int) env('CHAMBER_MIN_DURATION_MS', 400),
        'audio_retention_days' => (int) env('CHAMBER_AUDIO_RETENTION_DAYS', 30),
        'heartbeat_ttl_seconds' => (int) env('CHAMBER_HEARTBEAT_TTL', 90),
        'token_ability' => 'chamber:capture',
        'vad_rms' => (float) env('CHAMBER_VAD_RMS', 0.012),
        'default_capture_mode' => env('CHAMBER_DEFAULT_CAPTURE_MODE', 'mixer_mix'),

        // Shared by both producers: the browser reads these over the capture
        // state endpoint, the Python daemon from its own environment. Keep the
        // defaults identical so a chunk boundary does not depend on who cut it.
        'sample_rate' => (int) env('CHAMBER_SAMPLE_RATE', 16000),
        'min_speech_ms' => (int) env('CHAMBER_MIN_SPEECH_MS', 400),
        'silence_end_ms' => (int) env('CHAMBER_SILENCE_END_MS', 600),
        'max_utterance_ms' => (int) env('CHAMBER_MAX_UTTERANCE_MS', 25000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    */

    'locales' => [
        'en' => 'English',
        'fil' => 'Filipino',
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup / disaster recovery
    |--------------------------------------------------------------------------
    |
    | RPO/RTO are organizational targets — configure to match ICT policy.
    | Official records are soft-archived; hard delete requires dual-admin audit.
    |
    */

    'backup' => [
        'retention_days' => (int) env('SENTRIA_BACKUP_RETENTION_DAYS', 30),
        'rpo_minutes' => (int) env('SENTRIA_RPO_MINUTES', 60),
        'rto_minutes' => (int) env('SENTRIA_RTO_MINUTES', 240),
    ],

    /*
    |--------------------------------------------------------------------------
    | Electronic signatures / approvals
    |--------------------------------------------------------------------------
    |
    | Configurable architecture for electronic approval workflows. Uploaded
    | signature images are acknowledgements only — they are NOT legally valid
    | digital signatures under Philippine e-commerce / e-document rules unless
    | integrated with a qualified trust service provider.
    |
    | Drivers: none | image_ack | pkcs7_placeholder
    |
    */

    'electronic_signatures' => [
        'enabled' => env('SENTRIA_ELECTRONIC_SIGNATURES_ENABLED', false),
        'driver' => env('SENTRIA_ELECTRONIC_SIGNATURES_DRIVER', 'none'),
        'image_is_legal_signature' => false,
        'require_wet_signature_fallback' => env('SENTRIA_REQUIRE_WET_SIGNATURE', true),
        'metadata_fields' => ['signer_id', 'signed_at', 'document_checksum', 'intent_label'],
    ],

    /*
    |--------------------------------------------------------------------------
    | User profile photos
    |--------------------------------------------------------------------------
    |
    | Photos are stored on the public disk so the chamber display can show
    | members by face during a live vote. They are identity, not a credential.
    |
    */

    'users' => [
        'avatar_max_kilobytes' => (int) env('SENTRIA_AVATAR_MAX_KB', 2048),
        'avatar_mimes' => ['jpeg', 'jpg', 'png', 'webp'],
    ],

];
