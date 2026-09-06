# Contributing to FlintPHP

Thank you for considering a contribution. FlintPHP is deliberately small and
opinionated — contributions are evaluated against the architecture principles
below.

## Development Setup

Requirements: PHP 8.2+, Composer 2.x.

```bash
git clone https://github.com/chethanhrx/flintphp.git
cd flintphp
composer install
composer test
```

## Running the Checks

```bash
composer test                            # PHPUnit
composer validate                        # composer.json validity
git diff --check                         # whitespace hygiene
php benchmarks/HttpPipelineBench.php     # performance spot-check
```

## Architecture Principles

These are **binding**. Pull requests that violate them will be asked to
change course:

1. **Explicit composition over magic.** No facades, no global helpers, no
   static state, no service locators, no `app()` function.
2. **No discovery.** No filesystem scanning, class autodiscovery, attribute
   scanning, or reflection-based component discovery. Components are wired
   explicitly via `Application::bootstrapWith([...])` and the Container.
3. **Immutability where practical.** Request, Response, Route, HeaderBag,
   configuration, and middleware registries are immutable; mutation methods
   return new instances.
4. **Fail loudly, fail closed.** Developer configuration errors must throw —
   never silently coerce or fall back. Security-relevant failures must never
   result in an operation proceeding.
5. **No disclosure.** Internal exception details, paths, credentials, and
   policy internals must never appear in production HTTP responses.
6. **Minimal dependencies.** PHP standard library and framework code first.
   Every new runtime dependency needs a written justification.
7. **Backward compatibility.** Public APIs are contracts. Additive changes
   preferred; breaking changes require strong justification and a changelog
   entry.

## Pull Requests

- Keep changes scoped: one subsystem per PR.
- Add tests for every behavior change (happy path, failure path, boundaries,
  security regressions).
- Update the README and `CHANGELOG.md` when behavior or public APIs change.
- The full test suite must pass with zero regressions.
- Match existing code style: `declare(strict_types=1)`, `final` classes,
  `readonly` properties, constructor property promotion, typed signatures.

## Security

Do not report vulnerabilities in public issues — see
[SECURITY.md](SECURITY.md) for the responsible disclosure process.

## License

By contributing, you agree that your contributions are licensed under the
[MIT License](LICENSE).
