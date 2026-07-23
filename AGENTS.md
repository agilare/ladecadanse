# AGENTS.md

This file provides guidance to AI coding agents (Claude Code, Codex, Cursor, etc.) when working with code in this repository.

## Project overview

La décadanse is a PHP 8.3 cultural events agenda for Geneva and surroundings. It is a legacy codebase undergoing active modernization toward OOP and better architecture.

## Commands

### Static analysis

```sh
composer phpstan          # PHPStan static analysis (level 4)
composer psalm            # Psalm static analysis
composer rector:dry-run   # Show suggested modernizations without applying
./vendor/bin/phan --progress-bar -o phan.txt  # Phan analysis
composer sniffer:php83    # PHP 8.3 compatibility check (also :php81, :php82, :php84)
```

### Tests

```sh
composer test:api         # Run Codeception functional API tests
```

E2E tests use Selenium IDE — open `tests/ladecadanse.side` in the browser extension. Copy `tests/.env_model` to `tests/.env` before running API tests.

### Docker (development)

```sh
make start                # Start dev environment (localhost:7777)
make stop                 # Stop services
make logs                 # Tail logs
make shell                # Shell in web container
make build                # Rebuild images
make start PROFILE=prod   # Production (localhost:8080)
```

### Setup (without Docker)

```sh
composer install
cp app/env_model.php app/env.php        # Configure DB, SMTP, API keys
cp app/db.config_model.php app/db.config.php
cp .htaccess.example .htaccess
```

## Architecture

### Request lifecycle

Every public-facing PHP file starts with `require_once("app/bootstrap.php")`, which:
1. Loads `app/env.php` (environment constants: `ENV`, `DB_*`, `EMAIL_*`, feature flags)
2. Loads `app/config.php` (global constants, region/category arrays, icon HTML strings)
3. Starts the session
4. Instantiates global objects: `$connector` (DbConnector), `$connectorPdo`, `$authorization`, `$videur` (Sentry), `$tplEngine`, `$translator`, `$logger`

These globals are used throughout the codebase via `global $connector;` etc.

### Directory structure

- `app/` — bootstrap, config, env files (sensitive config kept out of git)
- `librairies/` — PHP class library, namespace `Ladecadanse\` (PSR-4 autoloaded)
- `event/`, `lieu/`, `organisateur/`, `admin/` — section-specific pages
- `resources/` — email templates (`.txt`), `messages.yml` (translations), SQL migration files
- `web/` — static assets: CSS/JS (`web/librairies/`), uploads, interface images
- `var/` — logs, cache (not in git)
- `tests/` — Codeception API tests and Selenium IDE project

### ORM-like base class

`Element` (`librairies/Element.php`) is the base class for domain entities:
- Extended by `Evenement`, `Lieu`, `Organisateur`, `Description`
- Provides `load()`, `insert()`, `update()`, `getValue()`, `setValue()` based on table name
- Uses `$connector->sanitize()` (mysqli_real_escape_string) for SQL injection protection

Domain objects have static dir/URL path properties set in bootstrap:
```php
Evenement::$systemDirPath = $rep_images_even;
```

### User permission system

`UserLevel` constants (lower = more privileged):
- `SUPERADMIN = 1`, `ADMIN = 4`, `AUTHOR = 6`, `ACTOR = 8`, `MEMBER = 12`

`Sentry` manages session/login/remember-me cookie. `Authorization` checks per-resource permissions (event editing, lieu management). Access checks use `$_SESSION['Sgroupe']`.

### Database access

Two connectors coexist:
- `DbConnector` — mysqli wrapper, used in most legacy code via `$connector` global
- `DbConnectorPdo` — PDO singleton, used in newer code

Always cast IDs to `(int)` and use `$connector->sanitize()` on strings in raw SQL queries.

### Template engine

`TemplateEngine` renders `resources/*.txt` files replacing `%placeholder%` tokens. Used for email bodies. Not used for HTML pages, which mix PHP and HTML directly.

## Key conventions

- **Commits**: Follow [Conventional Commits](https://www.conventionalcommits.org/fr/v1.0.0/) (`fix:`, `feat:`, `refactor:`, etc.)
- **SQL**: Use `(int)` cast for integer IDs and `$connector->sanitize()` for strings in any raw query
- **HTML output**: Use `sanitizeForHtml()` (from `librairies/Utils/utils_functions.php`) when echoing user data
- **Legacy globals**: `$connector`, `$authorization`, `$videur`, `$logger` are available globally after bootstrap
- **Config**: Feature flags and credentials live in `app/env.php` (not committed); see `app/env_model.php` for the full list
- **PHP version**: Target PHP 8.3 (`phpVersion: 80300` in phpstan.neon)
