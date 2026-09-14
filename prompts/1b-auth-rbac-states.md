# 1b — Auth, RBAC, State Machines

## Prerequisite

- Slice `1a` must-pass complete
- Load [00-shared-constraints.md](00-shared-constraints.md)

## In scope

- Laravel Fortify session authentication for the Inertia app
- Sanctum ready for external API consumers only (no requirement to ship public API yet)
- `spatie/laravel-permission` roles + granular permissions; seed matrix for all eight roles
- Session status state machine and legislative document workflow state machine (`spatie/laravel-model-states`)
- Permission checks on transitions; invalid transitions throw + tested + audit-logged when attempted
- Basic authorization policies/gates for core models as needed for login/navigation later

## Out of scope

- Full Inertia layouts, design tokens, dashboard UI (slice `1c`)
- Document upload, sessions UI, voting, AI
- Public portal

## Implementation prompt

```text
CONTINUE THE SAME PROJECT. Load 00-shared-constraints.md.

Implement authentication and authorization foundations:

1. Laravel Fortify session auth for the Inertia app. Secure password handling, session timeout configuration, CSRF protection.
2. Spatie roles/permissions for the eight canonical roles with a granular permission matrix. Seed permissions and assign them per role.
3. Implement session status state machine and legislative document workflow state machine with guarded transitions. Transitions live in one place — never scattered status string updates in controllers.
4. Every successful and attempted-invalid transition is permission-checked and written toward the audit log path (full append-only hash-chain enforcement lands in 2c; at minimum record transition events now).
5. AI must inherit the authenticated user's permissions (document this; enforcement hooks come with RAG in 3b).

Tests (Pest):
- Login works for one seeded user of every role
- Invalid state transitions throw
- Permission denials for a sample of role/permission pairs

Do not build the full UI shell yet.
Do not implement AI.
```

## Must-pass

- [ ] Login works for one seeded user of every role
- [ ] Role-appropriate permission abilities are seeded and queryable
- [ ] Invalid session and document workflow transitions throw and are covered by tests
- [ ] Controllers/services do not set raw status strings for those workflows

## Should-pass

- [ ] Sanctum installed and smoke-tested with a token guard stub
- [ ] Password reset / lockout policies documented in config

## Report format

Permission matrix summary; state diagrams; tests; how to log in as each role; debt for `1c`.

## Next

[1c-inertia-design-ci.md](1c-inertia-design-ci.md)
