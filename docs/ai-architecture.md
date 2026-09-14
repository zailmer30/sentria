# AI Architecture

Sentria uses swappable service interfaces so AI providers remain replaceable without rewriting domain logic.

## Service interfaces

| Interface | Purpose |
| --- | --- |
| `DocumentSummarizationService` | Document summaries |
| `RAGService` | Grounded Q&A with citations |
| `LegislativeSearchService` | Vector + full-text legislative search |
| `DocumentComparisonService` | Version diff analysis |
| `RelatedDocumentService` | Related document suggestions |
| `ConsistencyCheckService` | Cross-reference consistency |
| `MinutesGenerationService` | Draft minutes (never auto-finalized) |
| `TranscriptionService` | Live/session, mixer-mix, and per-seat chamber transcription |

Configuration: `config/sentria.php` (`AI_*`, `OCR_*`, `TRANSCRIPTION_*`, chamber capture). Keys stay server-side. Transcription is independent of chat. Groq Whisper, self-hosted Qwen3-ASR, and ElevenLabs Scribe can run as a failover chain (`TRANSCRIPTION_DRIVER=qwen3,elevenlabs,whisper`). The transcript row records the provider that succeeded. File-upload and mixer-mix Scribe calls may include diarization; per-seat live chunks do not. Mixer lines stay unassigned until the secretariat picks a speaker. Segments below `TRANSCRIPTION_LOW_CONFIDENCE` are marked unverified in the UI. See [chamber-capture.md](chamber-capture.md).

## RAG authorization (ACL in query)

Vector retrieval applies **permission filters inside the SQL/pgvector query** using denormalized fields on `document_embeddings` (`confidentiality`, `is_public`, document grants). Post-filtering retrieved chunks is **not** sufficient and is not used for authorization.

AI inherits the authenticated user's RBAC. Never bypass permissions for AI requests.

## Human-in-the-loop

AI must **not**:

- Vote or determine vote outcomes when official voting data exists
- Finalize minutes, approve legislation, or publish records
- Modify official records silently
- Provide unauthorized legal conclusions

Every AI surface uses the design-system **AI-generated / verify-against-original** treatment.

## Session assistant vs global AI

- **Session assistant** (`/sessions/{id}/assistant`) — scoped to active session context.
- **Global AI** (`/ai`) — RBAC-filtered corpus Q&A with citations.

## Provider outage

When `AI_ENABLED=false` or the provider is unreachable, stub adapters return safe “not available” responses. Core legislative workflows continue without AI.

## Related

- [security-architecture.md](security-architecture.md)
- [architecture.md](architecture.md)
