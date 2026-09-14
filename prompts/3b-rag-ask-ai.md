# 3b — Ask Legislative AI, RAG, Citations, Summaries

## Prerequisite

- Slice `3a` must-pass complete
- Load [00-shared-constraints.md](00-shared-constraints.md)

## In scope

- Document AI summaries with mandatory verify banner + source access
- “Ask Legislative AI” chat with RAG
- Authorization **inside** vector/SQL retrieval (not post-filter)
- Source citations from chunk metadata; no fabricated citations
- Semantic search with metadata filters
- AI audit logging (user, question, retrieved docs, sources, model, response, usage)
- AI UI surfaces using Part 1 AI visual treatment

## Out of scope

- Related-doc ranking UI polish beyond basic hooks, deep comparison, consistency checker (`3c`)
- Live session assistant / transcription (Part 4)

## Implementation prompt

```text
CONTINUE THE SAME PROJECT. Load 00-shared-constraints.md.

Implement Ask Legislative AI and RAG.

AI must NOT vote, approve/reject, modify official records, bypass permissions, or give unauthorized legal conclusions.

DOCUMENT SUMMARY:
Generate executive summary, purpose, key provisions, important dates, financial info if identifiable, affected offices, related docs, potential issues.
Display: "AI GENERATED — VERIFY AGAINST ORIGINAL DOCUMENT". Always provide source access.

ASK LEGISLATIVE AI:
Chat for questions like “What previous ordinances are related to this proposed ordinance?”
Return related records with document, reason, page/section, open link.

RAG FLOW:
User Question → Permission Check → Query Understanding → Semantic Search → Metadata Filtering → Retrieve Authorized Documents → Chunks → Generation → Citations → Response.

CRITICAL:
Never retrieve unauthorized documents. Permission filter is part of the vector search SQL itself, not a post-filter.

CITATIONS:
Every factual answer cites document, version, page, section from chunk metadata. Never fabricate.
If insufficient evidence: “I could not find sufficient information in the available legislative records.”

SEMANTIC SEARCH:
Natural language + filters (year, type, committee, author, status, date).

AI AUDIT:
Log user, question, timestamp, retrieved documents, sources, model/provider, response, token/usage.

UI: AI Summary, Ask Legislative AI; use AI-content visual treatment.

Tests: RAG retrieval, permission isolation at retrieval layer, citation generation, hallucination/no-evidence handling, unauthorized protection.
```

## Must-pass

- [ ] Ask Legislative AI answers with citations that open the correct page/section
- [ ] Permission isolation: user without access to Document X gets nothing from it; audit log shows it was never retrieved
- [ ] No-evidence questions return the insufficient-information response (not fabrication)
- [ ] AI content visually distinct everywhere it appears
- [ ] CI still passes

## Should-pass

- [ ] Streaming chat responses
- [ ] Multi-turn conversation memory quality tuning

## Report format

RAG architecture, prompt templates, security tests, config instructions, debt for `3c`.

## Next

[3c-compare-consistency.md](3c-compare-consistency.md)
