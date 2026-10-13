# Changelog

All notable changes to FlintPHP are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Production-readiness hardening (explicit composition only). This section records work in progress and is not a released version.

### Fixed

- Added driver-aware SQL identifier quoting for MySQL, SQLite, and PostgreSQL ORM queries and writes.
- Added regression tests for supported identifier quoting and rejection of invalid identifiers.

### Documentation

- Clarified rate-limit backend guarantees and the limitations of process-local and filesystem cache stores.
- Documented deferred session, CSRF, and distributed-cache capabilities.

### Added

- **Database\\Migration\\Migrator** + **MigrationInterface** — versioned up/down,
  tracked in `flint_migrations`.
- **ModelQueryBuilder** — operators (`>`, `IN`, `LIKE`, …), `orderBy`, `limit`,
  `offset`, quoted identifiers.
- **CorsMiddleware**, **RateLimitMiddleware**, **RequestIdMiddleware**.
- **Health\\HealthChecker** — build `/health` payloads (optional DB check).

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
