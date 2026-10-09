# Deployment

## Local

```bash
cp .env.example .env
# set APP_KEY after first up, or:
make up
make key
make migrate
make health
```

HTTP: `http://localhost:8080`

Useful targets: `make test`, `make pint`, `make stan`, `make quality`, `make fresh` (local only).

`make test` forces `CACHE_STORE=array` (and sync queue/session) so throttles do not share the app Redis. Compose nginx is the edge: it overwrites `X-Forwarded-For` with the TCP peer; Laravel trusts only private proxy CIDRs (not `*`). If you add an external load balancer in front of nginx, update both nginx forwarding and trusted proxies explicitly.

Identity mail uses queued notifications — keep the `worker` service running. Redis is ephemeral (ADR 0002); pending mail jobs are lost if Redis is recreated.

## Production (single server)

1. Back up PostgreSQL before risky schema changes.
2. Build or pull a versioned image (`IMAGE_TAG=...`).
3. Provide secrets via server `.env` / environment (never bake into the image).
4. Run migrations once:

   ```bash
   docker compose -f compose.yaml -f compose.prod.yaml run --rm app php artisan migrate --force
   ```

5. Start/update services:

   ```bash
   docker compose -f compose.yaml -f compose.prod.yaml up -d
   ```

6. Verify `GET /api/v1/health` and inspect logs.
7. Recreate the worker so it picks up the new code (`compose up -d --force-recreate worker`).

### Constraints

- `APP_DEBUG=false` in production
- PostgreSQL and Redis ports are not published
- Redis is ephemeral (ADR 0002): queued jobs are lost on Redis recreate
- A named Docker volume is **not** a backup — test restore procedures separately
- Non-backward-compatible migrations need a documented rollback plan before apply

## Secrets

- Generate `APP_KEY` with `php artisan key:generate`
- Rotate DB passwords / app key with coordinated downtime and re-encrypt considerations
- Do not commit `.env` or bake secrets into image layers
