# Changelog

All notable changes to FlintPHP are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Config\\Env**: explicit `.env` file loader. Returns a `array<string,string>` map;
  does not auto-inject into classes. Existing keys are not overwritten. Invalid or
  missing files throw `ConfigurationException`.
- **Response::error()**: consistent API error JSON shape
  `{"error":{"message":"...","status":400,"code":"optional"}}`.
- **JsonExceptionHandler**: exception boundary that always returns JSON error
  bodies; unexpected failures remain generic 500 with no internal disclosure.
- **CLI launcher** (`bin/flint`): thin executable that loads the Composer
  autoloader, forwards `argv` to `ConsoleApplication`, and passes the command's
  exit code to the process. Exposed as `vendor/bin/flint` via the new
  composer `bin` entry.
- **Console options**: `ParsedInputInterface` / `ParsedInput` — deterministic
  `--name=value` options, boolean flags, and `--` separator handling on top of
  the existing positional-only `InputInterface`. Option names are strictly
  validated; malformed options throw. Argument and option values are treated
  as opaque data (never interpreted or executed). Single-dash tokens remain
  positional, preserving legacy `Input` behavior. Fully backward compatible:
  `Input` and all existing `ConsoleApplication`/`CommandInterface` APIs are
  unchanged.
- **Built-in console commands**: `list` (alphabetical command listing) and
  `help` (usage + per-command help), generated exclusively from explicitly
  registered commands — no discovery, no reflection. Both are ordinary
  `CommandInterface` implementations backed by a read-only
  `CommandCollectionInterface` view; disable them with
  `new ConsoleApplication(false)`. `--help` routes to `help` when built-ins
  are enabled.
- **Phase A Test Hardening**: comprehensive edge-case test suites across
  the core primitives (Router, Kernel, Container, Request, Validator, PDO,
  API lifecycle integration) plus Env and Response::error tests.

### Changed

- `Request::path()` now strictly falls back to `'/'` when the parsed URI path
  is empty string `''`, ensuring uniform absolute path semantics.
- `Request::query()` and `Request::server()` now verify key existence with
  `array_key_exists` rather than null-coalescing `??`, correctly distinguishing
  explicit `null` values from missing parameters.

### Security

- Console input is treated as untrusted data: hostile argument/option values
  (shell metacharacters, command substitution, path traversal, control
  characters) are preserved verbatim and never executed.

## [1.0.0] - 2026-09-06

First stable release. FlintPHP v1.0.0 is a small, explicit, security-conscious
framework: no facades, no global state, no automatic discovery. All components
compose through `Application`, the PSR-11 `Container`, and explicit
`BootstrapperInterface` implementations.

See git history for the full 1.0.0 feature list and deferred items.
