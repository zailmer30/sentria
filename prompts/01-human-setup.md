# 01 — Human Setup (Do This Before Any Coding Slice)

These steps are for **you**, not the coding agent.

## Prerequisite

- Empty project directory (this repo) ready for Laravel init in slice `1a`.

## In scope

- Install frontend design skills
- Prepare local runtime + Docker services
- Create/confirm git so each slice is commit-reviewable

## Out of scope

- Application code
- Running coding-agent slices

## Steps

### 1. Install frontend skills

From the project root:

```bash
# Refero
npx skills add https://github.com/referodesign/refero_skill --skill refero-design
```

Verify `.cursor/skills/` (or `.claude/skills/`) contains `refero-design/`, with `SKILL.md`. Ask the agent to list available skills. Commit skill folders so cloud agents get them.

**Optional — Refero MCP** (skill works without it). Cursor `.cursor/mcp.json`:

```json
{
  "mcpServers": {
    "refero": {
      "url": "https://api.refero.design/mcp",
      "headers": { "Authorization": "Bearer <token>" }
    }
  }
}
```

Claude Code:

```bash
claude mcp add --transport http refero https://api.refero.design/mcp --header "Authorization: Bearer <token>"
```

### 2. Prepare the environment

Host tooling:

- PHP 8.4, Composer, Node 22 LTS
- Ability to run Docker (preferred) or equivalent local services

**Required:** a `docker-compose.yml` (agent may create it in `1a` if missing; human must be able to run it) covering at minimum:

- PostgreSQL 17 with `pgvector`
- Redis
- Networking suitable for Laravel Reverb later (published ports / same Docker network)

Optional later: ClamAV container for the production malware-scan adapter.

### 3. Git

- Use this repository; commit after every **passed** coding slice so each stage is reviewable and revertible.

## Must-pass (before slice 1a)

- [ ] Design skill folders present and visible to the agent
- [ ] PHP 8.4, Composer, Node 22 available
- [ ] PostgreSQL 17 + pgvector and Redis reachable (via docker-compose or documented equivalent)
- [ ] Git repository initialized

## Should-pass

- [ ] Refero MCP configured (if subscription available)
- [ ] ClamAV container sketched for later production use

## Next

Proceed to [1a-architecture-schema.md](1a-architecture-schema.md) with [00-shared-constraints.md](00-shared-constraints.md).
