# AGENTS.md

This file provides guidance to AI coding agents (Claude Code, Codex, Cursor, etc.) when working with code in this repository.

## Replies

Use short, direct, full sentences in reasoning and replies. Keep only the context needed for clarity and precision. Avoid filler, hedging, repetition, and obvious detail.

## Token-aware polling

Avoid token-wasteful polling for long-running background work.

- Do not use watch/stream modes that repeatedly emit unchanged status, such as `gh pr checks --watch`, unless actively debugging a failure
- For CI or background jobs, check once. If still pending or running, wait with `sleep 120` or `sleep 180`, then check once again
- Increase the wait interval when the job is running normally
- Do not send user updates for unchanged pending/running states
- Fetch detailed logs only after a check fails
- Prefer meaningful updates only: pushed, CI started, CI failed, CI passed

## Project overview

La décadanse is a PHP 8.4 cultural events agenda for Geneva and surroundings. It is a legacy codebase undergoing active modernization toward OOP and better architecture.

## Commands

Static analysis and test commands are defined as `composer` scripts (see `composer.json`); Docker targets are in the `Makefile` (`make help` lists them).

Use Eslint to check JS code (`npm run lint`).

JS unit tests run on Vitest: `npm test` (or `npm run test:watch`). Tests live in `tests/js/`, not in
`web/js/` — `web/` is the docroot, and `.git-ftp-ignore` already excludes `tests*` from deployment.
They import the modules under test directly (`web/js/global.js` and `web/js/browser.js` have no
import-time side effects), run under jsdom, and use explicit `vitest` imports rather than globals so
no ESLint config change is needed.

E2E tests use Selenium IDE — open `tests/ladecadanse.side` in the browser extension. Copy `tests/.env_model` to `tests/.env` before running API tests.

### Setup (without Docker)

```sh
composer install
npm install                             # ESLint + Vitest — required for `npm run lint` and `npm test`
cp app/env_model.php app/env.php        # Configure DB, SMTP, API keys
cp app/db.config_model.php app/db.config.php
composer config:build                           # Composes .htaccess and .user.ini from their fragments
```

`npm install` is only needed to lint and test: no JS build step exists, and the site runs without
`node_modules` (assets are served as-is via `<script type="module">` and an import map). Requires
Node 20.19+, 22.13+ or 24+ — declared as `engines` in `package.json`, so npm warns on an unsupported
version. The binding constraint is jsdom; odd-numbered (non-LTS) Node lines are excluded.

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

`TemplateEngine` renders `resources/templates/*.txt` files replacing `%placeholder%` tokens. Used for email bodies. Not used for HTML pages, which mix PHP and HTML directly.

## Key conventions

- **Commits**: Follow [Conventional Commits](https://www.conventionalcommits.org/fr/v1.0.0/) (`fix:`, `feat:`, `refactor:`, etc.)
- **JavaScript**: ES2015 (ES6) at minimum — `const`/`let` and never `var`, template literals rather than
  `+` concatenation, `import`/`export` modules. This applies to the inline `<script>` blocks of PHP
  templates too, which Eslint never sees. The `var` still found in `web/js/browser.js` and
  `web/js/map.js` predates the rule; modernize such a file when you touch it, don't leave new `var` behind
- **SQL**: Use `(int)` cast for integer IDs and `$connector->sanitize()` for strings in any raw query
- **HTML output**: Use `sanitizeForHtml()` (from `librairies/Utils/utils_functions.php`) when echoing user data
- **Legacy globals**: `$connector`, `$authorization`, `$videur`, `$logger` are available globally after bootstrap
- **Config**: Feature flags and credentials live in `app/env.php` (not committed); see `app/env_model.php` for the full list
- **URL routing**: old-to-new URL redirects live in `htaccess/50-routage.conf`, not in PHP. Moving or
  renaming a page means adding its 301 there in the same commit. The root `.htaccess` is composed from
  those fragments by `composer config:build` and must never be edited directly; see `docs/config-serveur.md`
