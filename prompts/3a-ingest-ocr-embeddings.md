# 3a — Document Ingest, OCR, Chunking, Embeddings

## Prerequisite

- Slice `2c` must-pass complete
- Load [00-shared-constraints.md](00-shared-constraints.md)

## In scope

- Provider-independent AI service interfaces (stubs OK where unused)
- Queued document processing pipeline on Redis/Horizon: validate → malware scan hook → OCR if needed → text extract → metadata extract → chunk → embed → vector store → search index
- pgvector with HNSW cosine index; configurable embedding dimensions
- Section-aware chunking (~500–800 tokens, 10–15% overlap) with citation metadata
- Per-document Processing / Completed / Failed status visible via Horizon/admin
- Preserve original files; never replace with OCR output

## Out of scope

- Ask Legislative AI chat UI and RAG answers (slice `3b`)
- Document comparison / consistency checker (slice `3c`)
- Session transcription (Part 4)

## Implementation prompt

```text
CONTINUE THE SAME PROJECT. Load 00-shared-constraints.md.

Implement the document intelligence ingest layer.

AI PRINCIPLE reminder: AI is an assistant; humans own official records.

Create provider-independent service interfaces at least for:
DocumentSummarizationService, RAGService, LegislativeSearchService, DocumentComparisonService, RelatedDocumentService, ConsistencyCheckService, MinutesGenerationService, TranscriptionService.
Configure providers via env/config. Prefer Laravel 13 first-party AI/vector helpers only where they fit behind these interfaces.

PIPELINE (queued jobs, Horizon-monitored):
Upload → File Validation → Malware Scan Architecture → OCR if required → Text Extraction → Metadata Extraction → Text Chunking → Embedding Generation → Vector Storage → Search Index.

VECTOR (PostgreSQL + pgvector):
- HNSW index, cosine distance.
- Embedding dimension configurable per provider (do not hard-code 1536).
- Chunk on section headings when detectable; ~500–800 tokens, 10–15% overlap.
- Store chunk metadata: document, version, page, section number/heading, character offsets.

OCR:
Detect selectable text in PDFs. If absent, OCR → store OCR text → preserve original PDF. Never replace original with OCR output.

Show processing status per document. Test job failure and retry behavior.

Do not ship the Ask Legislative AI chat yet (may leave service methods unimplemented or returning not-ready).
```

## Must-pass

- [ ] Upload a scanned PDF and a text PDF; both end up chunked, embedded, and searchable
- [ ] Pipeline statuses visible in Horizon (or equivalent admin job UI)
- [ ] Original binary unchanged after OCR path
- [ ] HNSW index present; dimension comes from config
- [ ] CI still passes

## Should-pass

- [ ] High-quality section detection across varied ordinance formats
- [ ] Production ClamAV adapter enabled in a compose profile

## Report format

Pipeline diagram, vector config, jobs, how to run workers, debt for `3b`.

## Next

[3b-rag-ask-ai.md](3b-rag-ask-ai.md)
