# Changelog

All notable changes to FlintPHP are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

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

### Security

- Console input is treated as untrusted data: hostile argument/option values
  (shell metacharacters, command substitution, path traversal, control
  characters) are preserved verbatim and never executed.

## [1.0.0] - 2026-09-06

First stable release. FlintPHP v1.0.0 is a small, explicit, security-conscious
framework: no facades, no global state, no automatic discovery. All components
compose through `Application`, the PSR-11 `Container`, and explicit
`BootstrapperInterface` implementations.

### Added

- **Application composition**: explicit composition root owning the Container,
  ConfigRepository, Router, MiddlewareStack, HandlerInvoker, ExceptionHandler,
  and Kernel; idempotent `boot()`; extension via `bootstrapWith([...])` with
  first-party bootstrappers — `DatabaseBootstrapper`, `OrmBootstrapper`,
  `SecurityBootstrapper`, `ValidationBootstrapper`,
  `AuthenticationBootstrapper`, `AuthorizationBootstrapper`.
- **HTTP**: immutable `Request` (attributes, trusted-proxy-aware client IP,
  `fromGlobals()` as the only superglobal access point) and `Response` (JSON
  factory, validated status codes, CRLF/control-character-safe headers via
  `HeaderBag`).
- **Routing**: static/dynamic route matching with compiled regex, 404/405 +
  `Allow` semantics, immutable `Route`, and route-scoped middleware resolved
  lazily through the Container (zero-overhead fast path for unscoped routes).
- **Middleware**: immutable onion pipeline; deterministic ordering; global and
  route-scoped composition; short-circuiting; exception transparency.
- **Kernel**: request dispatch with a single exception boundary; unexpected
  failures render a generic 500 with zero internal disclosure; `HttpException`
  renders controlled error responses.
- **Container**: PSR-11 with `set`/`singleton`/`bind` aliases, autowiring,
  circular-dependency and circular-alias detection, scalar defaults, nullable
  dependencies.
- **Validation**: stateless `Validator` with built-in rules, immutable custom
  rule registration via `withRule()` (built-in names protected from override),
  no silent coercion, `ValidationBootstrapper` for container composition.
- **Database**: lazy PDO connections (mysql/pgsql/sqlite) with real prepared
  statements by default (`ATTR_EMULATE_PREPARES = false`), explicit
  transaction API (no silent nesting), fail-loud configuration validation that
  never discloses credential values.
- **ORM**: strictly typed Data Mapper (public non-static properties only),
  constructor-bypass hydration with reflection caching, `fillable` mass
  assignment protection, identifier validation on all generated SQL.
- **Authentication**: `AuthenticatorInterface` + `BearerTokenAuthenticator`
  (tokens hashed before provider lookup, `hash_equals` comparison),
  `RequireAuthenticationMiddleware` (401 + `WWW-Authenticate: Bearer`),
  application-provided `UserProviderInterface`; no ORM coupling, no invented
  User model.
- **Authorization**: `AuthorizerInterface` boolean decision contract,
  `RequireAuthorizationMiddleware` (deterministic 403, no
  `WWW-Authenticate`), fail-closed on all failure paths (denial, exception,
  unbound implementation, identity contract violations); policy logic is
  application-owned by design.
- **Security**: `SecurityHeadersMiddleware` with explicit configuration,
  `TrustedProxyConfiguration` (CIDR, IPv4/IPv6) consumed by
  `Request::fromGlobals()`; security headers apply to all responses,
  including authorization denials.
- **Cache**: `ArrayCache` / `FileCache` with strict key validation,
  SHA-256-hashed file paths (traversal-infeasible), JSON-only storage (no
  deserialization), atomic writes.
- **Queue**: in-memory `ArrayQueue` + `Worker` (explicitly non-persistent).
- **Events**: synchronous dispatcher with exact-class matching, listener
  snapshots, reentrancy safety.
- **CLI**: minimal command registry/dispatcher with integer exit codes; no
  `exit()` in framework code, no shell execution.
- **Testing**: `TestCase`/`TestResponse` HTTP lifecycle helpers; no global
  state.
- **OpenAPI**: immutable OpenAPI 3.1 document construction and serialization.
- **WebSockets**: handshake validation, frame parser/builder, message
  assembler with bounded limits (10 MiB, 1000 fragments) and UTF-8
  validation.
- **Observability**: structured in-memory `Logger`/`NullLogger` with strict
  channel validation.
- **Metrics**: `Counter`/`Gauge`/`Histogram` with overflow/NaN rejection and
  isolated registries.
- **Configuration**: read-only nested dot-notation `ConfigRepository` with
  strict key validation.
- **Release infrastructure**: MIT `LICENSE`, `SECURITY.md`,
  `CONTRIBUTING.md`, GitHub Actions CI, HTTP pipeline benchmark.

### Security

- Header values follow the RFC 7230 field-value character set while
  rejecting CR, LF, NUL, and other disallowed C0 control characters to
  prevent header injection and response splitting.
- Database configuration errors disclose key names and value types only —
  never values (credentials cannot leak through validation messages).
- Exception boundary discloses no internal messages, stack traces, or paths.
- Authorization enforcement is fail-closed and cannot be bypassed through
  middleware ordering; adding middleware can only add checks.

### Deferred (intentionally)

Sessions, OAuth/JWT ecosystems, RBAC/permission models, policy discovery,
distributed cache/queue infrastructure, `.env`/file configuration loading,
automatic route/controller/model discovery, templating, and asset pipelines.
See the README "Limitations / Deferred Features" section.

## [0.32.0]

### Added

- Authorization Foundation: `AuthorizerInterface`,
  `RequireAuthorizationMiddleware`, `AuthorizationBootstrapper`, and
  route-scoped authorization with deterministic 403 semantics.

## [0.31.0]

### Added

- Authentication Composition: `AuthenticationBootstrapper`, route-scoped
  middleware (`Router` verb methods accept an optional middleware list;
  `Route::withMiddleware()` immutable derivation), lazy `Kernel` container
  resolution.

## [0.30.0] and earlier

- Iterative foundation releases: HTTP, routing, middleware, container,
  validation, database, ORM, authentication primitives, security, cache,
  queue, events, CLI, testing, OpenAPI, WebSockets, observability, metrics,
  configuration, and application composition. See the git history and README
  release sections for details.
