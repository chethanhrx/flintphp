# Changelog

All notable changes to FlintPHP are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-11-13

Production multi-host and database hardening release.

### Added

- **DatabaseSessionStore** — shared sessions for multi-host apps (MySQL / PostgreSQL / SQLite).
- **DatabaseCache** — shared cache with **atomic increment** for multi-host rate limiting.
- **Advisory locks** on migrate/rollback (MySQL `GET_LOCK`, PostgreSQL advisory locks; SQLite no-op).
- **migrate:status** and **migrate:rollback** console commands.
- MySQL 8.4 and PostgreSQL 17 integration tests (queries, transactions, migrations, queue, ORM, bool/null).
- `docker-compose.integration.yml` for local dual-engine verification.
- Production checklist: `docs/production.md`.

### Fixed

- Queue indexes without MySQL-invalid `CREATE INDEX IF NOT EXISTS`.
- Portable bool binding (`0`/`1`) across engines.
- ORM insert omits null primary keys so `SERIAL` / `AUTO_INCREMENT` can assign ids.
- Database queue primary keys and bounded VARCHAR columns for MySQL index rules.

### Changed

- Framework version constant set to **1.2.0**.

## [Unreleased]

### Notes

Work after 1.2.0 will land here.

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
