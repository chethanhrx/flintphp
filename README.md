# FlintPHP

A fast, secure, modern PHP framework for building production-ready APIs and web applications.

> ⚠️ **Development Status**: FlintPHP is under active development and is not yet ready for production use.

## Requirements

- PHP 8.2 or higher
- Composer 2.x

## Installation

```bash
composer require flintphp/framework
```

## Quick Start

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use FlintPHP\Framework\Foundation\Application;

$app = new Application(__DIR__);
$app->boot();

echo 'FlintPHP ' . $app->version() . ' is running.' . PHP_EOL;
```

## Philosophy

FlintPHP is built around these principles:

- **Fast by default** — Performance is a design consideration, not an afterthought.
- **Secure by default** — Security-first architecture with safe defaults.
- **Modern PHP** — Leverages PHP 8.2+ features: strict types, enums, readonly properties, and more.
- **Clean architecture** — Explicit behavior over magic. Composition over inheritance.
- **Modular** — Use only what you need. No monolithic god objects.
- **Tested** — Every meaningful feature has automated tests.

## Development

### Running Tests

```bash
composer test
```

### Project Structure

```
flintphp/
├── src/
│   └── Foundation/        # Core application bootstrap
│       ├── Application.php
│       └── FlintPHP.php
├── tests/
│   └── Foundation/        # Foundation tests
├── composer.json
├── phpunit.xml
└── README.md
```

## License

FlintPHP is open-source software licensed under the [MIT License](LICENSE).
