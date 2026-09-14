# Testing Report

## CI pipeline

GitHub Actions runs on every push:

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=2G
npm run lint
npm run format:check
npm run types
php artisan test
npm run build
```

## Test framework

- **Pest** for PHP feature and unit tests
- **PHPUnit 12** under the hood
- **ESLint / Prettier / TypeScript** for frontend static checks
- No Vitest in this slice — frontend offline queue logic lives in testable pure functions (`resources/js/lib/offlineVoteQueue.ts`); PHP tests document the queue key contract

## Must-pass coverage (slice 5c)

| Area | Test file(s) |
| --- | --- |
| Vote idempotency | `tests/Feature/Voting/VotingIdempotencyTest.php` |
| Vote append-only | `tests/Feature/Voting/VoteAppendOnlyTest.php` |
| Backup artifacts | `tests/Feature/Backup/BackupArtifactTest.php` |
| Session floor cache (PWA) | `tests/Feature/Backup/BackupArtifactTest.php` (cache JSON) |
| Portal 404 semantics | `tests/Feature/Portal/UnpublishedDocumentReturns404Test.php` |
| RAG ACL isolation | `tests/Feature/Ai/RagPermissionIsolationTest.php` |
| Audit chain | `tests/Feature/Voting/AuditChainVerifyTest.php` |
| HNSW index | `tests/Feature/Ingest/DocumentIngestPipelineTest.php` |

## Manual QA (offline session)

1. Open session floor as Board Member on a tablet/browser.
2. DevTools → Network → Offline.
3. Confirm agenda/current item still visible; offline banner shown.
4. Cast vote while offline — ballot queued message.
5. Restore network — single vote recorded (verify in voting results / DB).
6. Repeat cast — still one vote (idempotent).

## Running tests locally

```bash
php artisan test
php artisan test tests/Feature/Backup/BackupArtifactTest.php
php artisan sentria:check-indexes   # PostgreSQL only
```

Requires PostgreSQL `sentria_testing` database per `phpunit.xml`.

## Validation checklist

Organizational sign-off: [validation-checklist-ict-legal-records-dpo.md](validation-checklist-ict-legal-records-dpo.md)
