# Changelog

All notable changes to FlintPHP are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Enterprise platform phase 1 + 2 (explicit composition only).

### Added

- **Database\\Migration\\Migrator** + **MigrationInterface** — versioned up/down.
- **ModelQueryBuilder** — operators, `orderBy`, `limit`, `offset`, quoted ids.
- **CorsMiddleware**, **RateLimitMiddleware**, **RequestIdMiddleware**.
- **Health\\HealthChecker**.
- **Queue\\DatabaseQueue** + **SerializableJobInterface** + **ReservedJob**.
- **Worker** retries / dead-letter for database jobs.
- **Console\\Command\\MigrateCommand** (register in your app console).

## [1.1.0] - 2026-10-12

DX and API-usability release. See git history for details.

## [1.0.0] - 2026-09-06

First stable release.
