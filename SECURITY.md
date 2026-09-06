# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 1.0.x   | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

Please report security vulnerabilities responsibly:

1. **Do not open a public GitHub issue** for suspected security problems.
2. Open a **private security advisory** via GitHub ("Security" tab →
   "Report a vulnerability"), or contact the maintainer directly at
   <chethankumarhr751@gmail.com>.
3. Include a description, reproduction steps, affected versions, and any
   proof-of-concept details.

You can expect an initial acknowledgment within **7 days**. We will keep you
informed of progress toward a fix and coordinate a disclosure timeline with
you. Credit is given to reporters by default unless anonymity is requested.

## Scope and Guarantees

FlintPHP makes **bounded, technical** security guarantees — never absolute
ones:

- HTTP header values are validated against RFC 7230 field-value grammar
  (response-splitting control characters are rejected).
- The Kernel exception boundary never discloses internal exception details in
  production responses; only `HttpException` messages are surfaced.
- Authentication credentials are hashed before provider lookup and compared
  using constant-time `hash_equals`.
- Authorization enforcement is fail-closed: denials, authorizer exceptions,
  and unbound implementations all result in the request not proceeding.
- Database access uses real prepared statements by default; SQL identifiers
  generated from model metadata are strictly validated.
- Cache files are keyed by SHA-256 hashes (path traversal is structurally
  infeasible) and store JSON only (no deserialization).

**Out of scope / application responsibility:** the correctness of
application-provided authorization policies, `UserProvider`
implementations, route handlers, and any code the application binds into the
Container. The framework does not validate the security of developer-owned
logic.

## Known Limitations

See the "Limitations / Deferred Features" section of the README for features
that are intentionally not provided (sessions, CSRF infrastructure, distributed
caching, etc.). Applications must address those concerns at their own layer.
