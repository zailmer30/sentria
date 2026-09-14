# Future Improvements

Recommended enhancements after initial deployment:

## Operations

- [ ] Scheduled `sentria:backup` via Laravel scheduler with offsite replication (S3/rsync)
- [ ] Immutable backup storage (WORM/object lock) for ransomware resilience
- [ ] Automated restore drill job writing results to monitoring dashboard
- [ ] ClamAV production adapter enabled by default with alerting

## Session floor

- [ ] Multi-day offline LAN exercise with real tablets
- [ ] Background Sync API for vote flush when supported browsers allow
- [ ] Vitest unit tests for `offlineVoteQueue.ts`
- [ ] Explicit “connectivity required” UX for motions and attendance updates offline
- [ ] Per-session overrides of the global chamber channel map
- [ ] Stronger Bisaya/Cebuano STT via a swappable TranscriptionService adapter

## Product

- [ ] Full settings CRUD UI for `system_settings`
- [ ] Electronic signature integration with qualified TSP (Philippine context)
- [ ] Native mobile wrapper (Capacitor) for institutional app store deployment
- [ ] Advanced full-text + vector hybrid tuning with EXPLAIN-driven index review

## Governance

- [ ] Annual validation checklist re-run workflow with sign-off tracking in-app
- [ ] Records retention schedule UI (still no auto hard-delete of official records)
