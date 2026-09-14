# 3c — Related Documents, Comparison, Consistency Checker

## Prerequisite

- Slice `3b` must-pass complete
- Load [00-shared-constraints.md](00-shared-constraints.md)

## In scope

- Related documents suggestions (high/medium/low relevance) clearly labeled AI suggestions
- Document A vs B comparison (sections, provisions, amounts, dates, penalties, definitions)
- Consistency checker (missing sections, broken references, duplicates, numbering, terminology/date/amount inconsistencies, undefined terms)
- Wire UI entries: Related Documents, Compare Documents, AI Review
- Respect document ACL on all AI tools

## Out of scope

- Session-floor assistant, STT, draft minutes (Part 4)
- Public portal

## Implementation prompt

```text
CONTINUE THE SAME PROJECT. Load 00-shared-constraints.md.

RELATED DOCUMENTS:
Identify potentially related ordinances, resolutions, committee reports, previous versions, communications, supporting documents.
Display High / Medium / Low relevance. Label as AI suggestions. ACL applies.

DOCUMENT COMPARISON:
Document A vs B — changed sections, added/removed provisions, changed amounts, dates, penalties, definitions.

CONSISTENCY CHECKER:
Detect possible missing sections, broken references, duplicate provisions, numbering errors, inconsistent terminology/dates/amounts, undefined terms.
Example: “Section 8 refers to Section 12, but Section 12 was not detected.”
Label: “AI-assisted review. Human verification required.” Do not call this legal advice.

UI: Related Documents, Compare Documents, AI Review — AI visual treatment everywhere.

Tests: comparison output shape, consistency findings on fixture docs, unauthorized document protection for these tools.
```

## Must-pass

- [ ] Compare two versions/docs and show added/removed/changed highlights
- [ ] Consistency check on a fixture with a broken cross-reference surfaces a labeled finding
- [ ] Related docs tool never returns unauthorized documents
- [ ] CI still passes

## Should-pass

- [ ] Tuned relevance scoring with human-eval samples
- [ ] Side-by-side PDF sync scroll

## Report format

Services, UI routes, tests, remaining AI debt before Part 4.

## Next

[4a-session-assistant.md](4a-session-assistant.md)
