# Agent instructions

Short index. Read the relevant docs before changing code.

## Before work

1. `docs/current-state.md` — what exists now
2. `docs/architecture.md` — modular monolith rules
3. `docs/conventions.md` — API/module/testing conventions
4. `docs/backend-foundation-spec-v1.md` / `docs/spec/identity-auth-spec-v1.md` — acceptance criteria
5. `docs/api/identity.md` — Identity HTTP contract
6. `docs/decisions/` — ADRs for intentional deviations

## Rules of engagement

- Prefer native Laravel patterns; no speculative repositories/DTO/command bus
- Small coherent changes; no unrelated refactors
- New modules: register provider in `bootstrap/providers.php`
- Do not auto-migrate on container start
- Do not add dependencies without rationale and compatibility check
- Run and report real command results; never claim tests passed without running them
- Update docs when architecture, contracts, or deployment steps change
- Do not modify production secrets or run destructive production commands

## Verification commands

```bash
make up
make migrate
make health
make quality
```

## Cursor rules

Scoped rules live in `.cursor/rules/` (`laravel.mdc`, `modules.mdc`, `testing.mdc`).
