# ADR 0001 — Laravel major version

Status: Accepted  
Date: 2026-10-10

## Context

Backend Foundation spec v1.0 listed Laravel 12 as an initial baseline and required verifying maintained supported releases before installation.

As of 2026-10-10:

- Laravel 12 bug-fix window ended 2026-08-13 (security fixes until 2027-02-24).
- Laravel 13 was released 2026-03-17 and is the current maintained major (PHP 8.3–8.5).

## Decision

Use **Laravel 13** with **PHP 8.4**.

## Consequences

- Aligns with current framework support and the official `laravel/laravel` skeleton.
- Pest 4 + Larastan 3 are compatible with this stack.
- Spec baseline document remains historical; this ADR records the intentional deviation.
