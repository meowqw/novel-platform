# Novel Platform — Backend Foundation

Laravel 13 modular monolith foundation (API + infrastructure). No product features beyond a small Example `notes` module.

## Requirements

- Docker and Docker Compose v2
- Make (optional, wraps Compose)

## Quick start

```bash
cp .env.example .env
make up
make key
make migrate
make health
```

App: [http://localhost:8080/api/v1/health](http://localhost:8080/api/v1/health)

## Common commands

| Command | Purpose |
|---------|---------|
| `make up` | Build and start local stack |
| `make migrate` | Run migrations (explicit) |
| `make test` | Pest tests |
| `make pint` | Formatting check |
| `make stan` | PHPStan / Larastan |
| `make quality` | pint + stan + test |
| `make down` | Stop stack |

## Layout

- `app/Modules/` — business modules (see Example)
- `docker/` — nginx and PHP runtime config
- `docs/` — architecture, conventions, deployment, ADRs
- `compose.yaml` + `compose.dev.yaml` / `compose.prod.yaml`

## Identity API

See [docs/api/identity.md](docs/api/identity.md). Local mail defaults to `MAIL_MAILER=log` (or Mailpit on port 1025). Queued verification/reset notifications are processed by the `worker` service.

After schema changes on a **local** empty database:

```bash
make fresh
```

Do not run destructive fresh migrations against production data.

## Docs

- [Architecture](docs/architecture.md)
- [Conventions](docs/conventions.md)
- [Deployment](docs/deployment.md)
- [Current state](docs/current-state.md)
- [Identity API](docs/api/identity.md)
- [Foundation spec](docs/spec/backend-foundation-spec-v1.md)
- [Identity spec](docs/spec/identity-auth-spec-v1.md)
- [Agent index](AGENTS.md)
