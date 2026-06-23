# SynthetIQ Test Guide

SynthetIQ targets PHP 8.2 or newer and keeps optional integration paths disabled by default so a clean checkout can run the baseline suite without service credentials.

## Baseline

```bash
composer validate
composer install
vendor/bin/phpunit --do-not-cache-result
```

Use focused suites while developing a narrow change:

```bash
vendor/bin/phpunit --do-not-cache-result tests/Intents
vendor/bin/phpunit --do-not-cache-result tests/Language
vendor/bin/phpunit --do-not-cache-result tests/Training
vendor/bin/phpunit --do-not-cache-result tests/SynthetIQTest.php
```

## Optional JenSS Fixtures

JenSS fixture validation is opt-in. Set one of these environment variables to a local Jenerator autoload file:

```bash
SYNTHETIQ_JENERATOR_AUTOLOAD=/path/to/jenerator/vendor/autoload.php vendor/bin/phpunit --do-not-cache-result tests/Jenss
JENERATOR_AUTOLOAD=/path/to/jenerator/vendor/autoload.php vendor/bin/phpunit --do-not-cache-result tests/Jenss
```

When no autoload path is configured, the JenSS smoke test is skipped. The core runtime does not require Jenerator.

## Benchmarks

The current benchmark is a local smoke command for response selection throughput:

```bash
php benchmarks/selection.php 1000 50 5
```

Treat benchmark output as local evidence unless a future CI budget gate states a required threshold.

## Generated Artifacts

Do not commit generated runtime artifacts:

- `vendor/` is installed by Composer.
- `models/` is for local route-state, PHP-ML, and predictor cache files.
- `artifacts/` is for harness logs, scratch files, and temporary test outputs.

Committed examples and fixtures belong under `examples/`, `sample_configs/`, `benchmarks/`, or `tests/`.

## Clean Install Checklist

1. Confirm PHP 8.2 or newer is active.
2. Run `composer install`.
3. Run `composer validate`.
4. Run `vendor/bin/phpunit --do-not-cache-result`.
5. Optionally run `php examples/route_state.php --write --state=models/routes/synthetiq_routes.json`.
6. Optionally run `php benchmarks/selection.php 1000 50 5`.
