# ADR 0002 — Redis persistence for foundation

Status: Accepted  
Date: 2026-10-10

## Context

Redis is used for cache and queues. Spec allows optional Redis persistence depending on queue durability guarantees.

## Decision

Run Redis **without persistence** (`--appendonly no`, no RDB saves, no named volume) in foundation Compose configs.

## Consequences

- Cache and queued jobs are lost if the Redis container is recreated or crashes.
- Queue handlers must remain idempotent and retry-safe.
- Durable product state stays in PostgreSQL.
- When product jobs become critical, revisit AOF and/or a durable queue driver; document the change in a new ADR.
