# ADR 0003 — UUIDv7 primary keys for users

Status: Accepted  
Date: 2026-10-10

## Context

Backend Foundation defaulted to bigint identity IDs. Identity & Authentication v1.0 requires UUIDv7 for `users.id` and Sanctum `uuidMorphs` for personal access tokens.

No production user data exists yet. Example `notes` has no foreign key to `users`.

## Decision

- Use UUIDv7 for `users.id` via Laravel `HasUuids` (default in Laravel 13).
- Update the base `users` / `sessions` migration in place for local greenfield setups.
- Use `uuidMorphs('tokenable')` on `personal_access_tokens`.
- Keep bigint IDs for unrelated tables (e.g. Example `notes`) unless a later ADR says otherwise.

## Consequences

- Local environments that already migrated must run `migrate:fresh` (destructive; only when no valuable data).
- Production / databases with real data must not apply destructive recreate migrations; any future type change needs a non-destructive plan and explicit approval.
- `docs/conventions.md` documents User as the UUID exception.
