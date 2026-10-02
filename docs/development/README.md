# Development

## Requirements

- PHP 8.4+
- Composer 2
- PDO SQLite
- Node.js/npm for browser-backed measurements

## Setup

```bash
composer install
```

For browser measurements:

```bash
cd browser
npm install
npx playwright install --with-deps chromium
cd ..
```

## Quality commands

```bash
composer validate --strict
composer audit
composer test:unit
composer test:integration
composer psalm
composer phpstan
composer phpcs
composer mutation
```

GitHub Actions runs the deterministic gates independently so failures are attributable.

## CLI

```bash
php bin/privacy-evidence doctor
php bin/privacy-evidence source:import tests/Fixtures/sources/sites.csv
php bin/privacy-evidence run path/to/sites.csv
php bin/privacy-evidence status RUN_ID
php bin/privacy-evidence run:resume RUN_ID
php bin/privacy-evidence analyze RUN_ID
php bin/privacy-evidence report RUN_ID
```

Live web measurement is intentionally outside the deterministic default test path.
