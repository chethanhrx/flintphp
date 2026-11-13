# FlintPHP production checklist

This guide is for running FlintPHP APIs and services in production. It matches the **explicit, no-magic** design of the framework.

## 1. Runtime

- PHP **8.2+** with `opcache` enabled in production.
- Prefer PHP-FPM behind Nginx/Caddy, or a worker runtime (FrankenPHP / RoadRunner) if you know the trade-offs.
- Set `display_errors=0` and log errors to stderr or a collector.

## 2. Configuration and secrets

- Load config via `Env` + `PhpFileLoader` into `ConfigRepository` — never commit `.env`.
- Database credentials, JWT secrets, and cookie secrets come from the environment only.
- Rotate secrets with a dual-read window when possible.

## 3. HTTP and security

- Terminate TLS at the proxy. Point the app at the public HTTPS origin for any absolute URLs.
- Trust only your proxy for client IP (`X-Forwarded-For`) if you use IP-based rate limits.
- Keep `SecurityHeadersMiddleware` and a strict CORS allowlist for browser clients.
- Use `JsonExceptionHandler` (or equivalent) so 500 responses never leak stack traces.

## 4. Sessions (multi-host)

| Hosts | Store |
|-------|--------|
| Single machine | `FileSessionStore` |
| Multiple app instances | **`DatabaseSessionStore`** (shared DB) or Redis (when you add a Redis driver) |

Always:

- Secure, HttpOnly, SameSite cookies (`SessionCookieConfiguration`).
- Rotate session id on login / privilege change.
- Put CSRF middleware on unsafe methods for cookie-authenticated browsers.

## 5. Rate limiting (multi-host)

`RateLimitMiddleware` requires an **atomic** counter backend for safety under concurrency.

| Setup | Backend |
|-------|---------|
| Single process / tests | `ArrayCache` |
| Multi-host | **`DatabaseCache`** (implements `AtomicCounterInterface`) |

Do not use non-atomic fallback in production.

## 6. Database and migrations

- Supported engines in integration tests: **MySQL 8.4**, **PostgreSQL 17**, SQLite (dev).
- Set `charset=utf8mb4` for MySQL.
- Run migrations **before** switching traffic to code that requires the new schema:

```text
migrate
migrate:status
migrate:rollback --steps=1   # only when rehearsed
```

- `Migrator` takes an **advisory lock** (MySQL `GET_LOCK`, Postgres advisory locks) so two deploys cannot migrate at once.
- MySQL DDL is **not** transactional: keep migrations small; prefer expand/contract for zero downtime.

## 7. Queues and workers

- `DatabaseQueue` is durable and multi-worker safe (claim via delete). Index `(queue, available_at)` is created automatically.
- Run workers under a process supervisor; set max attempts and monitor `flint_failed_jobs`.
- Prefer a separate DB user with least privilege for workers if possible.

## 8. Health and observability

- Expose `/health` via `HealthChecker` (include DB check).
- Export Prometheus text from `MetricRegistry` on an internal-only path.
- Ship structured logs (JSON lines) to your aggregator; bind a PSR-3 logger into the exception handler when available.

## 9. Deploy order (safe default)

1. Build and test artifact  
2. Run `migrate` against the target database (one job, advisory lock)  
3. Roll out application processes  
4. Smoke-test `/health` and a critical write path  
5. Only then remove old processes  

## 10. What FlintPHP is optimized for

Explicit composition, predictable request lifecycle, security-minded defaults, and multi-engine SQL for **APIs and services**. It is not a claim of “replace every enterprise framework.” Use the shared DB session/cache path when you scale past one host; add Redis later if you need sub-millisecond shared state.
