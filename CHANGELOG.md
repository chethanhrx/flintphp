# Changelog

All notable changes to FlintPHP are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Application::setExceptionHandler()**: replace the exception handler before
  `kernel()` is built (e.g. `new JsonExceptionHandler()` for APIs). Throws
  `LogicException` if the Kernel is already finalized. Rebinds
  `ExceptionHandlerInterface` in the container.
- **Config\\Env**: explicit `.env` file loader. Returns a `array<string,string>` map;
  does not auto-inject into classes. Existing keys are not overwritten. Invalid or
  missing files throw `ConfigurationException`.
- **Response::error()**: consistent API error JSON shape
  `{"error":{"message":"...","status":400,"code":"optional"}}`.
- **JsonExceptionHandler**: exception boundary that always returns JSON error
  bodies; unexpected failures remain generic 500 with no internal disclosure.
- **CLI launcher** (`bin/flint`): thin executable + ParsedInput options + built-in
  `list` / `help` commands (explicit registration only).
- **Phase A Test Hardening**: edge-case suites for Router, Kernel, Container,
  Request, Validator, PDO, API lifecycle, Env, Response::error, and exception
  handler replacement.

### Changed

- `Request::path()` falls back to `'/'` when the parsed URI path is empty.
- `Request::query()` and `Request::server()` use `array_key_exists` semantics.

### Security

- Console input is treated as untrusted opaque data (never executed).

## [1.0.0] - 2026-09-06

First stable release. See git history for the full 1.0.0 feature list and deferred items.
