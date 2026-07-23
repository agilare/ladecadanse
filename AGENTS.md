# AGENTS.md

This file provides guidance to AI coding agents (Claude Code, Codex, Cursor, etc.) when working with code in this repository.

## Project overview

La décadanse is a PHP 8.4 cultural events agenda for Geneva and surroundings. It is a legacy codebase undergoing active modernization toward OOP and better architecture.

## Commands

Static analysis and test commands are defined as `composer` scripts (see `composer.json`); Docker targets are in the `Makefile` (`make help` lists them).

E2E tests use Selenium IDE — open `tests/ladecadanse.side` in the browser extension. Copy `tests/.env_model` to `tests/.env` before running API tests.

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

`Sentry` manages session/login/remember-me cookie. `Authorization` checks per-resource permissions (event editing, lieu management). Access checks use `$_SESSION['Sgroupe']`.

### Database access

Two connectors coexist:
- `DbConnector` — mysqli wrapper, used in most legacy code via `$connector` global
- `DbConnectorPdo` — PDO singleton, used in newer code

### Template engine

`TemplateEngine` renders `resources/*.txt` files replacing `%placeholder%` tokens. Used for email bodies. Not used for HTML pages, which mix PHP and HTML directly.

## Key conventions

- **Commits**: Follow [Conventional Commits](https://www.conventionalcommits.org/fr/v1.0.0/) (`fix:`, `feat:`, `refactor:`, etc.)
- **SQL**: Use `(int)` cast for integer IDs and `$connector->sanitize()` for strings in any raw query
- **HTML output**: Use `sanitizeForHtml()` (from `librairies/Utils/utils_functions.php`) when echoing user data
- **Legacy globals**: `$connector`, `$authorization`, `$videur`, `$logger` are available globally after bootstrap
- **Config**: Feature flags and credentials live in `app/env.php` (not committed); see `app/env_model.php` for the full list
