# Changelog

All notable changes to FlintPHP are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Config\\PhpFileLoader**: load `config/*.php` files (each returning an array)
  into a nested config map for `ConfigRepository`. Explicit directory argument;
  no recursive app discovery.
- **Application::setExceptionHandler()**: replace the exception handler before
  `kernel()` is built (e.g. `new JsonExceptionHandler()` for APIs).
- **Config\\Env**: explicit `.env` file loader.
- **Response::error()**: consistent API error JSON shape.
- **JsonExceptionHandler**: JSON exception boundary for APIs.
- CLI launcher, ParsedInput options, built-in `list` / `help`.
- Phase A test hardening + tests for Env, Response::error, exception handler
  replacement, PhpFileLoader.

### Changed

- `Request::path()` empty-path fallback to `/`.
- `Request::query()` / `server()` use `array_key_exists`.

### Security

- Console input treated as untrusted opaque data.

## [1.0.0] - 2026-09-06

First stable release. See git history for full details.
