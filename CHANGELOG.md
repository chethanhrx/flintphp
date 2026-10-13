# Changelog

All notable changes to FlintPHP are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Production-readiness hardening (explicit composition only). This section records work in progress and is not a released version.

### Fixed

- Added driver-aware SQL identifier quoting for MySQL, SQLite, and PostgreSQL ORM queries and writes.
- Added regression tests for supported identifier quoting and rejection of invalid identifiers.
- Added `Kernel::handleGlobals()` so request-construction failures are processed by the configured exception handler; documented it as the preferred front-controller entry point.
- Reconciled the transaction wrapper's reported state with PDO when callers change transaction state through the exposed PDO connection.
- Added strict WebSocket Origin allowlisting with normalized origins, host/port validation, and malformed IPv6 rejection.

### Documentation

- Clarified rate-limit backend guarantees and the limitations of process-local and filesystem cache stores.
- Documented production session-store requirements, cookie settings, middleware ordering, and CSRF usage in `docs/session-security.md`.

### Added

- Added explicit server-side session primitives: `Session` and `SessionStoreInterface`.
- Added `ArraySessionStore`, an expiring process-local store intended for development and tests.
- Added `FileSessionStore` for single-host persistence using JSON records, strict session ID validation, restrictive permissions, locking, and atomic writes.
- Added secure cookie configuration and opt-in `SessionMiddleware`.
- Added session ID rotation for privilege changes and removal of the previous stored session record.
- Added session-bound CSRF token generation and constant-time verification, plus opt-in `CsrfMiddleware` for unsafe HTTP methods.
- Added **Database\\Migration\\Migrator** and **MigrationInterface** for versioned up/down migrations tracked in `flint_migrations`.
- Added **ModelQueryBuilder** operators (`>`, `IN`, `LIKE`, …), `orderBy`, `limit`, `offset`, and quoted identifiers.
- Added **CorsMiddleware**, **RateLimitMiddleware**, and **RequestIdMiddleware**.
- Added **Health\\HealthChecker** to build a `/health` payload with an optional database check.

## [1.1.0] - 2026-10-12

DX and API-usability release. No facades, no discovery, no magic.

### Added

- **Application::setExceptionHandler()** — replace the exception handler before
  `kernel()` is built (e.g. `JsonExceptionHandler` for APIs).
- **Config\\Env** — explicit `.env` file loader (returns a map; no auto-injection).
- **Config\\PhpFileLoader** — load `config/*.php` files into a nested array for
  `ConfigRepository`.
- **Response::error()** — consistent API error JSON:
  `{"error":{"message":"...","status":400,"code":"optional"}}`.
- **JsonExceptionHandler** — exception boundary that always returns JSON errors;
  unexpected failures stay generic 500 with no internal disclosure.
- CLI: `bin/flint`, `ParsedInput` options, built-in `list` / `help`.
- Expanded test coverage (edge cases, Env, Response::error, handler replacement,
  PhpFileLoader).

### Changed

- **Kernel** 404 / 405 responses now use `Response::error()` (JSON). 405 still
  sets the `Allow` header.
- `Request::path()` empty-path fallback to `/`.
- `Request::query()` / `server()` use `array_key_exists` semantics.
- Framework version constant set to **1.1.0**.

### Security

- Console input treated as untrusted opaque data (never executed).

## [1.0.0] - 2026-09-06

First stable release. See git history for the full feature list and deferred items.
