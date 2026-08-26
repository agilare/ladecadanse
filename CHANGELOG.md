# Changelog

## [Non publié]

Upgrade steps (redirects, side effects) : [UPGRADE.md](UPGRADE.md).

### Fixed
- admin events : a bulk replace no longer wipes the organisateurs of the selected events #125 — the `evenement_organisateur` DELETE ran after every successful UPDATE while only the posted selection was re-inserted, so changing a statut on ten events cleared the organisateurs of all ten, against the page's own promise that only non-empty fields overwrite ; the DELETE/INSERT pair now runs only when the field was filled
- admin events : the "delete the image" branch removed the file read by the flyer branch (`$affImg` for `$affimage`), the schedules were re-read from `$_POST` inside the loop — bypassing the trim and the validation, and dropping the seconds `evenement-edit.php` appends —, `$_FILES['flyer']` was read without a guard, the posted statut was not validated, and the bulk delete built error messages it never displayed
- admin events : an event without a localité is listed again — the `JOIN localite` was an inner join, so such an event vanished from the very screen where it could be fixed
- admin events : the bulk form carries a CSRF token, which it lacked although it deletes ; the number of rows is validated against a list instead of being any integer, so `LIMIT 0,999999` no longer passes

### Changed
- admin : `admin/gererEvenements.php` becomes `admin/events.php` #125, with a 301 — the whole request is processed before the first byte of HTML, a replace or a delete redirects instead of leaving a F5 to replay it, and the listing moves to `EvenementCollection` as prepared statements, its WHERE built once for both the count and the page
- admin events : filters, sort and rows-per-page are remembered in the session as in `admin/users.php` ; two filters are added, on the lieu name and on the author's pseudo or e-mail, the category filter menu goes, and 100 leaves the rows-per-page menu
- admin events : the bulk form keeps visible only what bulk editing uses — statut, catégorie, lieu, horaires, organisateurs — and folds the rest into three `<details>` : a lieu typed by hand, the event content, the files ; the statut radios and the delete checkbox go horizontal, their labels lose the parenthesised explanations, the first "Remplacer" button goes, and the overwrite warning becomes a `confirm()` at submit time
- events : the lieu and organisateur selects, the lieu resolution and the hh:mm to datetime conversion move to the domain classes, next to `Localite::getOptionsHtml()` — `evenement-edit.php` and the admin form each carried their own copy, but only the first had received the 3.12.0 fixes ; the admin page thereby gets its salles in one query instead of one per lieu displayed, and its images in 600×600 with unclamped thumbnails
- tests : unit coverage for the lieu and organisateur options and for the 301 of every moved page ; the `site` suite covers the admin events screen — access, the three filters, their memory, the sort links, the CSRF token and the folded blocks

## [3.12.0] - 2026-08-23

Upgrade steps (redirects, side effects) : [UPGRADE.md](UPGRADE.md).
Feature documentation : [docs/](docs/).

### Added
- events : the calendar export leaves the home and the event page for every listing — search results, lieu and organisateur pages #150 (PR #173) ; the menu shows for all visitors but only on future events, while Éditer, Copier and Dépublier stay reserved to those allowed to edit
- lieux, organisateurs : both listings get twelve columns counting the events added month by month, from AUTHOR up #178 — the date of the last event said neither the rhythm of a lieu nor the moment it stopped announcing ; only the additions made by members count, the team's (group ≤ AUTHOR) would measure the admins' work instead, and each cell recalls the same month a year earlier so a quiet July can be read against previous Julys — see [docs/lieux-organisateurs.md](docs/lieux-organisateurs.md)
- events : the wide layout of the event page, given to admins in 3.11.0 while the idea was being tried out, is now served to every visitor — the frame, the illustrations, the description and the title take the right column, empty on that page
- home : below 800px the partners and the latest added events line up with the gutter `<main>` already keeps ; the partners get the rounded grey background used elsewhere and their five logos become a centred flex row, instead of a baseline-aligned staircase over two uneven columns
- tests : the `site` suite covers the register form, the logout (long-lived cookie and refusal of GET included), the monthly counters and the event edit form ; new unit suite for `PasswordPolicy`

### Changed
- users : register, password reset and logout join `user/` next to login and dashboard, each page reworked as login was (#121, #123) — the whole processing runs before the first byte of HTML, the queries become prepared statements, the mail bodies become templates, the errors gather in a single summary at the top of the page with the faulty fields marked, and the three account pages share one stylesheet ; the old urls need a 301 — see [UPGRADE.md](UPGRADE.md) and [docs/comptes.md](docs/comptes.md)
- users : the password rules move to the shared `PasswordPolicy` — the three forms that set a password each copied the same rules and re-read `resources/bad_p.txt` their own way, through a path relative to the script, which breaks as soon as a page changes directory ; the rejected list goes from 22 to 19 999 entries (tarraschk/richelieu, the commonest French passwords from public leaks, CC BY 4.0, plus the four of our own it lacked — the Swiss keyboard "qwertz" among them)
- ui : the legacy 2000's PNG icons (famfamfam Silk) give way to Font Awesome and `web/interface/icons/` drops from 1469 files (~5 MB, only 49 of them referenced) to the 4 kept on purpose — `page_white_edit`, `page_white_copy`, `calendar_delete` and `map`, whose drawing has no Font Awesome equivalent we liked #151 ; the icon rules whose class is emitted nowhere (`.popup_gmaps`, `.icalendar`, `.probleme`, `.complete`, `.selection li.descendre`) are dropped rather than converted, the "événement copié" banner recovers the check mark its stylesheet had been pointing at a wrong path for years, the pagination arrows become `fa-long-arrow-*`, and in the action menus the icon becomes part of the link instead of sitting outside it
- events : under 450px wide the description now wraps around the illustrations column instead of stopping beside it, so the lines below the flyer take the full width and the page gets noticeably shorter ; the fixed minimum heights of both blocks are dropped at that breakpoint, and an event without any illustration no longer reserves an empty 120px column
- events edit : the four near-identical flyer/image blocks are reduced to one definition and its calls, dead code is dropped, and the salles of the select load in one query instead of one per lieu displayed
- events : on the event page and the ics export, `idE` is validated before bootstrap — answering 400 to a malformed url no longer opens two MySQL connections, starts the session and mounts the log handlers, and bots produce these urls in bulk
- librairies : the dead `Collection` hierarchy is removed #216 — a proto-repository never adopted, whose public API had no caller ; `Evenement` no longer extends `Element`, and the organisateurs listing loses one SQL query per page view
- resources : the mail bodies rendered by `TemplateEngine` move to `resources/templates/`, the sql scripts (schema and migrations) to `resources/database/`
- contact : drop the "nom" and "affiliation" fields, which served nothing
- analyzers : repair the Rector configuration, which pointed at a test file moved long ago — `composer rector:dry-run` failed before analysing anything ; the PHP version is read from `composer.json`, the cache joins PHPStan's under `var/cache/`, and listing the code directories instead of walking from the root brings the run from over the 300 s composer timeout down to ~25 s for the same 38 files
- deployment : `.htaccess` and `.user.ini` are composed from fragments by `composer config:build` and sent with the code — neither was versioned, the example file was never deployed and the production copy was edited by hand on the server, so the three had drifted apart : the custom 404 page had been pointing at `articles/404.php` since that file was renamed in April 2025, and Apache served its own page instead ; what follows the code (redirects, error pages, path protections, timezone, session lifetime) now lives in `htaccess/` and `userini/`, what follows the hosting (banned addresses, bots, canonical domain, error log path) stays in a private repository and never reaches this one ; `composer deploy` recomposes before pushing, and refuses to leave without the private fragments or without an explicit git-ftp scope — see [docs/config-serveur.md](docs/config-serveur.md)
- ui : on mobile the fields of the event form take the full width, Statut and Catégorie stack, the "Plusieurs dates…" help text moves next to the date field, and the day navigation of the agenda shows text rather than the date, with arrows instead of chevrons

### Fixed
- users : for anyone who had ticked "Rester connecté-e", clicking "Sortir" had no effect at all — `Sentry::logout()` cleared that cookie with a `setcookie()` whose `path` was omitted, so PHP fell back on the calling script's directory ; harmless while the page lived at the root, the deletion aimed at `/user` once the page moved there, and Sentry reopened the session on the next request
- users : a reset request made for a deactivated account led to a form that could not be submitted #123 — `reset.php` looked the account up without checking `statut` and sent the link, while `reset2.php` only listed the active ones, so "Veuillez choisir un compte" fired again on every submit with no choice to make ; both pages now read one list, established before any processing
- users : a reset link cut by a mail client threw in the middle of the page, that is a 500 in production ; a missing or malformed token now reads as an invalid request, like an expired one
- users : an account with the "demande" status, which Sentry refuses at login, could have its password reset and stayed stuck at the login screen anyway
- lieux, organisateurs, search, admin : a malformed `page` url parameter threw an uncaught exception — bots follow urls where the unescaped `&region` of `?page=3&region=vd` has been read as the `&reg` html entity, giving `?page=3®ion=vd` ; the 8 pages paginating now go through the new non-throwing `QueryParamValidator::pageFromQuery()`, which also rejects a page below 1 or passed as an array
- events send : sharing or reporting an event whose lieu was deleted, or whose location was typed as free text, logged a warning ; the fallback returned by `Evenement::getLieu()` was missing the `determinant` key
- lieux edit : saving the form as ACTOR or MEMBER logged a warning ; `image_galerie` is declared as a file field although its input is only rendered from AUTHOR up, so it never reaches `$_FILES` for those levels
- events edit : the "Supprimer" checkbox of the image never got re-checked after a validation error, and the preview of the file already saved is now kept on the flyer and the image side alike even when the field is in error — without it the old file could no longer be deleted
- edition : a stray character had slipped in front of the `<?php` of `librairies/Edition.php` — PHP echoed it as soon as the class was included, so text came before any header on the edit pages, and PHPStan refused to analyse the file, hence to finish its run
- ui : the footer menu lit more than the current link — `$ici` was never emptied at the start of each turn and already arrived filled from the header, so the Organisateurs pages lit "Faire un don" and "Contact" in the footer
- events : on mobile the action bar of an event scattered its labels across lines, the public actions and those reserved to logged-in users sharing a single right-aligned flow ; each group is now its own list, stacked and left-aligned, and the icons (16px images as well as Font Awesome glyphs) are centred on their label — "Envoyer à un ami" moves to a Font Awesome icon
- ui : on mobile the actions menu of a lieu was hidden, the icon of the message banners overlapped the text, and the check mark of the "événement copié" banner did not sit on the first line
- assets : the stylesheet of the page was loaded before the extra ones, which it could therefore not override
- monitoring : `Sentry.init()` threw a `ReferenceError` in the console of visitors running an ad blocker, uBlock Origin returning the CDN bundle empty — which also fails the SRI check ; the call goes behind an existence test

### Security
- users : logging out was a GET, so any link prefetch (browser, antivirus, mail scanner) or any third-party site could close a member's session ; `user/logout.php` accepts POST only, protected by a token valid for the whole session — the "Sortir" button is rendered on every page, therefore in every open tab, and a single-use token would block the logout from a tab left in the background
- events edit, admin : six queries concatenated values coming from `$_POST` without cast nor quotes, four of them written `WHERE id=" . $connector->sanitize(...)` where the escaping is inert — outside quotes `mysqli_real_escape_string` does not neutralise `1 OR ...`, only the `(int)` cast protects in a numeric context
- db : the password of the `DbConnector` constructor is marked `#[\SensitiveParameter]`, which keeps it out of the stack traces

## [3.11.0] - 2026-08-18

Upgrade steps (database, `app/env.php`, side effects) : [UPGRADE.md](UPGRADE.md).
Feature documentation : [docs/](docs/).

### Added
- events : a past event becomes a read-only archive — no edit form and no deletion, so it can no longer be recycled into a new one ; Copier and Dépublier stay available and editors keep full access — see [docs/evenements.md](docs/evenements.md)
- users, events edit : personal default values for adding an event (category, start and end time, lieu, organisateur(s), price), set in the profile and applied at creation only ; stored in the new `personne.settings` column — see [docs/evenements.md](docs/evenements.md)
- events : admins can notify the event author by email of the changes made, picking pre-written motifs and/or writing a free-text message #149
- events : the ajax "Dépublier" button, so far limited to the home, lieu and organisateur listings, is now also on the event page, the user profile, gererEvenements and the search results (SUPERADMIN only)
- events edit : the flyer and the photo can be added by pasting an image URL, now open to every logged-in user (was admins only), not on the public "Proposer un événement" form
- events edit : confirm before leaving the form with unsaved changes
- lieux edit : optional latitude/longitude fields, so the map coordinates can be set from the site instead of directly in the database (a map picker will come later) ; stored as `DECIMAL(10,7)` instead of `FLOAT(10,6)`
- search : the results page keeps the query in the header field and offers a "Copier" button on each result ; on mobile the search field stays open on that page
- home : time-based separators in the events list #105 (PR #133) (thanks to @lambeletjp)
- ui : keyboard shortcuts for the most common actions #112 — pages, edition, flyer, copy, day navigation, pagination, `/` to focus a filter field, `j`/`k` to walk listings and events one by one — see [README](README.md#raccourcis-clavier)
- ui : "mouseless" mode to learn those shortcuts, for ADMIN and SUPERADMIN only — `?mouseless=1` neutralises the mouse on the elements a shortcut can reach and badges each one with its key — see [README](README.md#raccourcis-clavier)
- mailing : optional copy to the admin of the messages sent to users, for short monitoring periods, off by default (see `app/env_model.php`)
- bots : internal monitoring of automated traffic, with an admin dashboard, off by default — see [docs/bots.md](docs/bots.md)
- don : add Postfinance and Twint payment methods, add Bernex to "Soutiens"
- tests : new Codeception `site` suite (PhpBrowser) covering the author notification, permissions, statuses, the bots dashboard, the rss feeds and the collapsible texts ; set `LADECADANSE_SITE_URL` in `tests/.env`
- tests : Vitest setup and first JS unit tests (`npm test`)

### Changed
- rss : feeds are cached 900 s and answer `304` through `ETag`/`Last-Modified`, the `400`/`410` status contract is settled before bootstrap without session nor database, and the `<style>` block is dropped from item descriptions — see [docs/rss.md](docs/rss.md)
- lieux, organisateurs : long descriptions and presentations are rendered already folded by the server instead of being collapsed by read-smore after the render, so they no longer flash at full length and stay readable without JS ; the read-smore CDN script is dropped, whose failure used to break `main.js`
- assets : a missing file is now reported as a warning in `var/logs/activity.log` instead of the PHP error log, which it was filling one line per page view
- admin : in gererEvenements, the events list gets a fixed height (70vh) with its own scrollbar and sticky column headers ; checkboxes column moved first
- events edit : the "E-mail à l'auteur" fieldset previews the message that will be sent, built by `AuteurNotifier` so it cannot drift from the actual mail
- don : disable the wemakeit widget (unavailable from July 31) and replace the "Autres moyens possibles" line with an intro paragraph
- monitoring : for GlitchTip upgrade the Sentry browser SDK from 9.14 to 10.69, pin the CDN bundle with an SRI `integrity` hash and drop `tracesSampleRate`, inert on the errors-only bundle
- analyzers : finalise the Psalm configuration and fix the PHPStan config gaps, both baselines regenerated ; add the `composer psalm:taint` script
- build : declare the required Node version via `engines`, name the npm package, document `npm install` and `npm test`
- deps-dev : update phpmailer, phpstan, rector, rector/jack, select2, vlucas/phpdotenv, phan, psalm, spaze/phpstan-disallowed-calls
- docs : add AGENTS.md as the single source of project guidance for coding agents, CLAUDE.md now points to it
- docs : move the upgrade steps to UPGRADE.md and the feature details to `docs/`, so this changelog stays scannable

### Fixed
- rss : wrong channel `pubDate`, illustrations leaking from one item to the next, missing lieu autodiscovery tag, relative feed URLs — now absolute through the new `SITE_CANONICAL_URL` — and a warning on a call without `type` — see [docs/rss.md](docs/rss.md)
- lieux, organisateurs, events : an unknown id returned an empty 200 or a fatal error instead of the 404 the pages already had ready ; `evenement-edit.php` without `idE` now answers 400
- search, lieux, organisateurs, admin, events : an unexpected value in an optional url parameter threw an uncaught exception or logged a warning instead of falling back on the default ; guards now use the new non-throwing `QueryParamValidator::isAcceptedUrlQueryValue()`
- edition : in TinyMCE texts, links to the site itself lost their `href` (relative URLs rejected by the sanitizer) ; texts saved before this fix must be edited again — see [UPGRADE.md](UPGRADE.md)
- events : a `genre` absent from the configuration crashed the home page and logged warnings in the listings ; it now displays as "divers" through the new `Evenement::genreLabel()`
- events : an event referencing a deleted lieu crashed the pages listing it ; its location now falls back on the free text fields stored in the event
- lieux, organisateurs : "Passés" tab now opens on the most recent past events instead of the oldest ones
- csp : unblock the bots tracker (darkvisitors.com now redirects to knownagents.com), the AssetManager import map (an inline tag missing its nonce) and the GlitchTip DSN host, missing from `connect-src`
- forms : the date field is usable with the keyboard again

### Security
- events edit : stored XSS in the "Organisateur(s)" select2 list, whose renderer interpolated decoded values into an HTML string ; the template is now built as DOM nodes
- deps : bump guzzlehttp/guzzle, guzzlehttp/psr7, symfony/dom-crawler, symfony/html-sanitizer, symfony/yaml to fix 20 known security advisories (cookie handling, CRLF/host-confusion injection, XSS bypass, XXE, ReDoS/DoS)
- deps-dev : bump guzzlehttp/guzzle, squizlabs/php_codesniffer, js-yaml and brace-expansion to fix 3 advisories (CVE-2026-69246, CVE-2026-69245, CVE-2026-67434), all reachable only through the dev tree


## [3.10.0] - 2026-04-26

### Fixed
- users : in register form, wrong field was used to hash password
- auth : restore broken "Remember me" feature #83
- events : in edit form, warn to avoid resubmit without preselected file
- edition : restore Google fonts URL in CSP for TinyMCE
- tests : in Selenium add missing "pause" commands to avoid fails

### Added
- events calendar : when changing month, instead of loading all the page, update only the calendar #104 (thanks to @lambeletjp)
- events : add export to more calendars #57 (thanks to @lambeletjp)
- events edit : move email contact field before event details fieldset #147
- events edit : add handle of flyer/image from url
- images : add handling upload of images format webp #32
- ui : add back-to-top button on long pages #99
- agenda : for small screens, add anchor link to latest events in menu
- auth : allow login with email address #103
- assets : use AssetManager for entity image cache busting (events, lieux, orgas) #109
- tests : Selenium cases for this release

### Changed
- PHP 8.4 compatibility #94
- PHP : update phpunit, rector, phpstan...
- update Symfony, Codeception, phpunit...
- logger : replace custom Logger with Monolog #64
- refactor Utils

### Security
- deps-dev : bump flatted from 3.2.9 to 3.4.2


## [3.9.4] - 2026-03-08

### Security
- authentication : bcrypt password hash handling, auto-migration when user sign in #46
- various html outpouts sanitized
- add Symfony Html Sanitizer to clean rich texts inputs of lieux and organizers
- add sanitizing against sql injections in events, lieux, orgas, users
- prevent path traversal from http or DB input when processing flyers, images, logo... of event, lieux, orgas
- add headers & attachments sanitizations in Mailing
- removed obsolete X-Frame-Options header
- deps-dev : bump minimatch from 3.1.2 to 3.1.5

## [3.9.3] - 2026-02-22

### Fixed
- events : in renderer titreSelonStatutHtml add status check
- events : rss included unpublished events
- footer : add missing link to Participer
- users : in register form don't show error if email is already used

### Added
- faire un don : section "Soutiens" showing logos of supporting orgas
- seo : set Open Graph default url and image, add to lieu & orga

### Changed
- lieux : salle edition form modernized and cleaned #132 (thanks to @lambeletjp)
- lieux : replace Google Maps by Leaflet and Open Street Map #134 (thanks to @Biboba)
- à propos : add TDG article link, update Participer section
- contribuer : add "bénévole", small texts improvements
- faire un don : describe better my work
- edition : upgrade TinyMCE 6 to 7, fix title button (h4 -> h3)
- deps-dev: bump js-yaml from 4.1.0 to 4.1.1
- analyzers : update Phan 5.4 to 6.0
- build : MariaDB requirement from 10.6 to 10.11
- build : update PHPMailer 6 to 7

### Security
- small fixes concerning database, headers, cookies, emails


## [3.9.2] - 2025-11-09

### Fixed
- admin : current page highlight in menu pratique
- texts : for textarea with TinyMCE adjust config to avoid relative paths
- header : less overflows in menu pratique

### Added
- organisateurs : table with navigation, filters
- events : in home publish daily events counter
- admin : dashboard completed with more pertinent data, better layout, refactoring
- lieu : add affiliates users to editors profiles
- mise à jour : new page (for logged in users), a simplified changelog
- participer : new page with start of CONTRIBUTING
- lieux, organisateurs : show month of latest event also when < 6 month away
- in 2 db connection setups add set of sql mode for session
- readme : db config requirement for full text search
- updates : add for this version

### Changed
- organisateurs : pages organisateurs and organisateur moved under organisateur/
- mobile : top menu with 2 links outside
- admin : users page with more useful columns, refactored, store most of browsing params in session
- admin : evenements pages with more useful columns (orgas, events reporting...); refactoring
- semantic : implement widely rel=exernal in replacement of lien_ext class
- tests : adapt Selenium tests to new Organisateurs and admin pages
- users : rm left col in login; add autofocus in 1st fields
- error, contacteznous, error, maintenance pages moved under misc/
- Docker configuration updated

### Removed
- organisateurs right menu
- readme : in db config rm requirement of ALLOW_INVALID_DATES
- nG_log.php

### Security
- add missing auth and 403 response to some pages


## [3.9.1] - 2025-10-11

### Fixed
- pagination : lieu and search pages had wrong items per page because of an error in sql
- organisateurs : in Organisateur page restore link to preselected orga in event form
- admin : restore jquery-checkboxes in gererEvenements
- tests (Selenium) : some regressions after lieux and admin menu redesign

### Added
- organisateur : passed/incoming events menu, with pagination
- organisateur : in event edit form link to Contactez-nous prefills contact form
- events : method to display an event in a html table
- lieux : in index highlight when there is an event today, show to editors latest event date of inactive lieux

### Changed
- organisateur : page refactored, cleaned, modernized
- lieux : in index catégories mv from col to below lieu name
- lieu : in events table, date links to agenda
- home : update Radio Vostok partner logo
- events : mv global to a static var in class
- users : mv global to a static var in class
- tests (Selenium) : update to new Organisateur page; replace some CSS selectors (slow) by xpath

### Removed
- organisateur : heavy db query for events count in menu (that will be replaced in a next version), reduce pages load from 3s to less than 1s


## [3.9.0] - 2025-09-21

### Fixed
- events : resource sql file name (adding index) for v3.8.0
- events : in copy form limit paste to 100 dates to avoid user errors and then overload of ressources
- events : fade out of event after action unpublish was broken
- layout : menu link highlighted when in lieu or organisateur page
- links : add missing class="lien_ext", including Text::linkify
- add php commented short_open_tag = Off in .htaccess.example
- admin : in events form, replacing of images was broken

### Added
- lieux : table with navigation, filters
- lieu : passed/incoming events menu, with pagination
- lieu : texts truncated and Readmore link with read-smore JS lib
- admin : direct pages links in header menu
- home : nb events per day for authors only
- more semantic html
- assets management versioning for custom css and js files
- tests : in search event edit link

### Changed
- events : search refactored with fulltext index (replacing LIKEs & php postprocess), PDO, cleaning, layout, html5...
- events : to ICS format refactored and moved under event/
- events : rss feeds refactored and moved under event/
- events : report and email refactored, merged into send and moved under event/
- events : copy refactored and moved under event/
- events : actions moved under event/
- events : in edit form, field Remarque label more accurate
- events : api with enabled flag and file moved under event/
- lieu : page refactored, cleaned
- lieux : pages lieux and lieu moved under lieu/
- admin : rm obsolete left col, improve admin menu in head
- css : refactor (remove obsolete, useless files)
- build : php lib upgrade

### Removed
- SEO: some "disallows" in robots.txt no longer useful
- useless multi-suppr script
- lieux right menu


## [3.8.0] - 2025-07-20

### Fixed
- users : password reset form

### Added
- home : takeover of agenda features (prev/next navig, sort menu)
- PDO config & connector
- database : composite index on evenement table for perf
- SEO : meta tag noindex, nofollow for some irrelevant events pages
- event : add width for img tags of thumbnail flyer when possible
- search : in mobile focus in input search after click search button
- styles : anti browser cache for some important css files

### Changed
- home : refactoring with n+1 removing, PDO, mv to methods, rm duplications, extended usage of html5
- event : refactoring evenement page with PDO, factorization, extended usage of html5
- donate : move description under the payment methods
- SEO : in robots.txt exclude Scrapy, increase delay for SemrushBot and meta-externalagent, restore exclusion of some annoying URL parameters
- SEO : rm some rules in robots.txt partly in favor of noindex
- refresh .htaccess.example
- security : mv bad pwd list to a file, list refreshed

### Removed
- SEO : useless vars in query string in agenda, menus lieux & orgas
- event : agenda page (replaced by home)


## [3.7.4] - 2025-05-25

### Added
- agenda, SEO : add 404 header if day has no results, nofollow to prev/next links
- calendar, SEO : disable browsing to next months if there is no events in the future
- SEO : in robots.txt exclude some pages, agenda faceted navigation params, thumbnails images and increase Crawl-delay of some bots
- À propos : link to article on GBNews.ch

### Changed
- home: refactoring; categories navigation : rm prev, add label to next
- calendar : refactoring with use of DateTime and remove useless query string parameters

### Security
- admin: sanitize some html display in admin pages

### Removed
- partners : Noctambus (discontinued)


## [3.7.3] - 2025-05-11

### Added
- opengraph: add basic tags in `<head>` + url & flyer for event page
- statistics : add users sessions to Matomo tracking
- bots : tracking for Darkvisitors service
- donation : Wemakeit donate widget

### changed
- build : upgrade Symfony yaml, var-dumper, Codeception, Whoops, phpstan, rector, phpmailer...


## [3.7.2] - 2025-04-27

### Fixed
- errors : 404.php to error.php with try to handle more cases, rm wrong "inclusions"
- users : harmonize password fields size and validation
- tests : on evenement page click Maps link and back navigation button only if presents

### Added
- statistics : Matomo site id constant and tools.ladecadanse.ch in CSP
- tests : Selenium basic for Faire un don page
- forms : options of selects with Select2 can display an additional text

### Changed
- monitoring : for GlitchTip upgrade Sentry version to 9.14

### Security
- secure lieu sql queries in affiliation table

### Removed
- statistics : Google Analytics tracking (#92)


## [3.7.1] - 2025-04-21

### Fixed
- forms : replace Chosen (not working on mobile) by Select2 (#17)
- home : events list display the missing horaire_complement
- home : Debout les braves banner link from darksite to olivedks.ch
- events : disapeared status in event page, more status bkg colors
- events : in lieu page restore "Ajouter un événement à ce lieu"
- lieux : don't display lieu map if lat & lng are empty (0.000000)
- donate : Paypal btn was disabled because of a missing URL in CSP
- forms : disabled text buttons color in Safari Mobile

### Added
- events : in edit form help text for Catégorie, clearer warn if 2 lieux filled
- donate : Liberapay button and link in FUNDING.yml
- contributing : links to "Les prochains développements suggérés"

### Changed
- PHPMailer `smtpauth` option configurable
- monitoring : for GlitchTip add Sentry URL in CSP & upgrade version

### Removed
- events : in edit form menu with copy and delete, radios for price type


## [3.7.0] - 2025-03-23

### Fixed
- donate : Paypal btn was disabled because of a missing URL in CSP

### Added
- region_covered in localite table to soften cantons strict separation (goal : Nyon district in Geneva agenda) to user
- record last_login in personne table
- home : in mobile view, donate button aside Github link
- home : closable info alert for connected users
- FUNDING.yml with paypal.me link
- analyzers baselines

### Changed
- PHP 8.3 compatibility
- donate : text completed with more explanations
- donate : Paypal btn modernized and following new & improved config; extract hosted_button_id
- footer : highlight donate button, remove search form
- Charte éditoriale, À propos : actualisation
- Docker configuration updated

### Removed
- Recaptcha
- donate : (commented) Flattr code

### Security
- add 8G firewall and logger script to record rejected requests
- add some int casts in sqls


## [3.6.2] - 2025-03-07

### Fixed
- Selenium tests : unpublish event (actor), add venue texte (admin), check Profil page (admin)
- events : event page when localite not found; castings for security
- events : in Evenement::getFilePath handle date not in filename

### Changed
- PHP 8.2 compatibility
- var_dump 6 to 7
- Analyzers : Phan config stricter
- dev: browser-sync config clearer


## [3.6.1] - 2025-03-04

### Fixed
- events: in evenement, creation date displayed was missing month
- events: when generating image URL avoid Warning if file doesn't exists

### Changed
- PHP 8.1 compatibility
- refactoring : usage of html converters, Rector's NullToStrictStringFuncCallArgRector application
- replace deprecated usage of FILTER_SANITIZE_STRING
- Codeception phpbrowser and asserts modules 2 to 3
- Analyzers : Psalm 5 to 6
- Analyzers : configurations for php 8.1


## [3.6.0] - 2025-02-28

### Fixed
- features restored (remove from partial edit mode, i.e. blocking edition of entities added before 22.10.2024) : edit events, users, salles, organisateurs, lieux texts
- legacy code cleaned up according to phpstan, rector, psalm and phan analyzes
- add .htaccess.example to .gitignore

### Added
- Selenium tests : forms values auto-generated, edit event test
- Analyzers : Phan, Psalm
- Composer : funding, scripts
- README : analyzers usage

### Changed
- events : in agenda add for robots noindex, nofollow in pages above 1 year in the future and with no events
- PHP 8.0 compatibility
- upgrade docker files for latest version
- Codeception 4 to 5
- Analyzers : Rector 0.* to 2, phpstan 1 to 2
- Analyzers : Rector, phpstan configurations for php 8.0

### Security
- headers: add Permissions-Policy


## [3.5.5] - 2025-02-14

### Fixed
- features restored (remove from partial edit mode) and more detailed logging : password reset, user edit
- deployment : git-ftp exclusion list
- HTML errors in home, contact, header
- SEO: complete robots.txt to ignore irrelevant pages
- docs : replace old home URL by ladecadanse.ch in README, CONTRIBUTING
- docs : homepage example image URL in README

### Added
- Selenium tests : some forms values auto-generated, unpublish event action test
- Apache configuration example for local env

### Security
- CSP completed (more precise and restrictive) and inline scripts in html adapted accordingly (externalizations, *nonce* implementation)
- add Referrer policy
- all cookies sent with "samesite"
- completed missing sanitization of values displayed in HTML
- removed bad usage of PHP_SELF
- add token verif to public forms to avoid multi-submits
- add session id regeneration on login and logout
- various vulnerabilities fixed in login, agenda, search


## [3.5.4] - 2025-01-22

### Fixed
- events : increase "horaire_complement", "ref", "prelocations" fields lengths which were a little too tight based on usage

### Added
- events : split images in "evenements" dir by year to ease the load of the huge amount of files
- events : in edit form, title field, warn if user pastes text too large; it happens often and results in odd titles

### Changed
- events : remove the "new" icon in the 2 infos about Noctambus which were put 1 year ago
- upgrades : PHPMailer, PHP dotenv, Whoops 2.16, PHPStan 1.12, PHP_Codesniffer 3.10, Magnific-Popup 1.1 to 1.2, Zebra Datepicker 2.2

### Security
- Utils::urlQueryArrayToString() now sanitize its output; revealed by https://www.openbugbounty.org/reports/955861/
- headers: replace outdated configs, add CSP
- admin : add sanitize of new user affiliation
- add secure, httponly and mostly samesite to cookies sent
- security guidelines in SECURITY.md


## [3.5.3] - 2024-11-10

### Fixed

- deploy : exclusions and inclusions in git-ftp-ignore
- js : main.js freshness, obsolete function calls
- mailing : in toAdmin() replace "from" by a "replyto" in order to allow sending with SMTP auth

### Added

- edition : add partial edit mode a limited edition on current db version to avoid conflicts with an other DB version
- docs : CONTRIBUTING.md
- GitHub issue templates (bug report, feat request)
- dev : Browsersync config file

### Changed

- docs : mention modernization project in README.md
- docs : clearer app/config.php
- remove obsolete attribute 'version' in docker-compose.yml
- build : mention licence in composer.json


## [3.5.2] - 2024-02-25

### Fixed

- menus : pratique links 404
- session : back to default config
- library : order of parameters in 2 functions
- forms : rectify some calls to css files
- style : avoid page's css broken link
- forms : rm calls to nonexistent validerEmail()

### Added

- maintenance page
- Glitchtip error tracker

### Changed

- contact : replace old email obfuscation method
- refactor js : reorganize by scope, use modules
- upgrade TinyMCE from 5 to 6

### Removed

- liens page : obsolete links


## [3.5.1] - 2023-11-26

### Fixed

- events : queries to fix horaires val of copied rows and some other
- events : a typo was breaking the sending process in send by email

### Added

- Noctambus : add banner in home and explanation of partnership (and also for EPIC magazine) in user registration and add event
- api : nb of items returned in logging of each request
- license (AGPL) file

### Changed

- timezone definition moved to config.php to improve portability
- update PHPMailer, whoops, phpdotenv, phpstan, var-dumper...
- update jQuery from 3.7.0 to 3.7.1
- update Zebra datepicker to v2.0
- php analyzers configs, plugins

### Removed

- jQuery Migrate

### Security

- replace apparently [unsafe shiftcheckbox jQuery plugin](https://github.com/agilare/ladecadanse/security/code-scanning/1) by [checkboxes.js](http://rmariuzzo.github.io/checkboxes.js)


## [3.5.0] - 2023-06-26

### Fixed

- users - edit : "avec affiliation" value must be sent in submit, rm conditions
- events
  - in edit and copy form config of datepicker to allow adding event for today event after 0h
  - copy of an event had horaire_fin *before* horaire_deb if original horaire_fin was after midnight; horaires of event copied were in the wrong day (the same day as dateEvenement) if horaires of original were after midnight

### Added

- events : API to get night events "fêtes" of a day
- tests : setup Codeception and basic tests of API
- Symfony VarDumper component

### Changed

- events form presentation improvements
    - increase width of some fields
    - put back horaire under date
    - complete link for tooltip by a more visible help button
    - clearer lieu manual fields
- forms : increase container width for lieu, organisateur, contact...
- tests : documentation revised, completed (readme, strategy, map)
- update jQuery from 3.6.4 to 3.7.0


## [3.4.5] - 2023-05-18

### Fixed

- users - password reset : in db table rm unique of idPersonne to avoid crash
- users - edit : affiliation text wasn't saved, display "avec affiliation" field only if pertinent
- add ini_set session.gc_probability to enable auto clean of old session in Debian
- UX : added missing icon ext links
- in small screens events lists right overflow

### Added

- tests : added assertions, for most important cases in Selenium suites
- Doc link in menu 1 for admin users

### Changed

- php libraries : whoops 2.15, phpmailer 6.8, phpstan


## [3.4.4] - 2023-04-16

### Fixed

- evenement : crash if lieu not found
- lieu : galerie image upload
- tests : some fixes in Selenium suites

### Added

- tests : in documentation, readme and strategy revised, completed

### Removed

- à propos : inactive in staff

### Security

- update [jquery link "latest"](https://cdn.jsdelivr.net/jquery/latest/jquery.min.js) (actually frozen at 3.2.1) to latest 3.6.4


## [3.4.3] - 2023-03-22

### Fixed

- home : mobile left col was unusable
- evenement : unpublish auth, in edit ref and prelocations length validation, calendar past days color
- lieux : header; in home, latest added only actives, logo size in mobile
- readme : create admin sql

### Added

- basic end-to-end tests suites for Selenium IDE

### Changed

- TESTS.md v1.1
- in home, agenda links iCal, report more visible


## [3.4.2] - 2023-03-12

### Fixed

- evenement
    - in calendar, event's date (instead of today)
    - handle if img not found

### Added

- TESTS.md v1

### Changed

- events : in edit form, larger width and inputs, more help texts for better data entered
- darken `a:visited`


## [3.4.1] - 2023-03-03

### Fixed

- agenda : event categories title weren't displayed in the list
- user levels usage
- date functions to avoid notices

### Added

- in calendar, past days half transparent
- evenement : `<time>` on event date

### Security

- restored honeypot in evenement-report


## [3.4.0] - 2023-02-27

### Fixed

- events : copy

### Added

- `UserLevel` class introduced (replaces useless table `groupes`)
- [Whoops](https://github.com/filp/whoops) error handler

### Changed

- users : level 12 (member) disabled
- sessions saved in `var/sessions`
- config for events files from images/ to web/uploads/evenements (and gitignore)
- refactor
    - data files to new dir `resources`
    - classes in `libraries` : cleaning, typehints
    - `dates.php` more generic as `utils_functions.php` and moved in `sanitizeForHtml()`
    - agenda.php to evenement-agenda.php, event to evenement-actions
    - _footer.php externalize js
    - _header.php mv script jquery to footer

### Removed

- useless fields of user (ip, session, nom, prenom, adresse, telephone, URL, notification_commentaires, remarque), personne_organisateur (role), evenement (URL1, URL2), lieu (horaire_evenement, entree, telephone, email, acces_tpg, plan, actif), organisateur (telephone)
- users : level 10
- comments (completely)
- favorites (completely)
- enlightn/security-checker

## [3.3.0] - 2023-02-19

### Fixed

- search : mots-vides.txt in utf-8

### Changed

- lieux home like organizers
- Matomo tracking code modernized and for tools.ladecadanse.ch/matomo
- refactor
    -  bootstrap, config
        - purge, rename global url, rep vars
        - class to manage region user choice
        - env : replace remaining env var by const, document
    - table temp -> user_reset_requests
    - url queries for calendar simplified
    - misc

### Removed

- cache
- breves
- favorites
- adding comments
- lieux : document upload
- app : index.html, utf8-to-utf8mb4
- events : tiny flyers, document upload
- header files (home latest's, lieu, organisateur)
- rss : even comments, lieux descriptions

### Security
- rm display of mysql errors

## [3.2.6] - 2023-02-05

### Fixed

- misc in agenda, users, contact
- gitignore

### Added

- setup [PHPMailer](https://github.com/PHPMailer/PHPMailer) (replaces PEAR Mail)
- Docker compose recipe
- analyzers setup : PHPCompatibility, phpstan, security checker
- git-ftp setup
- home : Debout les braves banner
- propose event : intro clarifications
- event form : clarifications for ask organisation registration
- changelog
- TESTS.md
- editorconfig

### Changed

- composer : complete needed PHP extensions
- php errors and timezone config externalized
- readme - partially rewritten, completed
- Zebra datepicker 1.9.19
- logs dir

### Removed

- PEAR Mail
- php obsolete functions

### Security
- rm master key

## [3.2.5] - 2023-01-12

### Fixed

- mailing with Message-ID in header (problem with Gmail rejection #58 )
- organisateurs : access to "lz" menu #55
- password reset avoid URL token rejected

### Changed

- better names of files
- better organisation
- moved most of the functions into classes
- namespaces and autolading
- `env_model.php` (ex `params_model.php`) completed
- cleaning of useless code
- clearer README
- php 7.4

## [3.2.4] - 2021-12-27

### Fixed

- remove event text styling feature to avoid layout break
-  query parameters handling
- better naming of dirs to ignore by git

### Changed

- remove old unused jquery, chosen dependencies js files


## [3.2.3] - 2021-11-21

### Fixed

- propose event : clarifications
- event form : clarifications for ask organisation registration

## [3.2.2] - 2021-11-21

### Added

- js alert in forms if user selects a file to upload > 2 Mb
- "prochains événements" in home page title

## [3.2.1] - 2019-12-21

### Added

- compat with db utf8mb4 encoding

### Fixed

- misc

### Changed

- updates of libs

## [3.2] - 2019-06-23

### Added

- better experience for visitors, contributors and admins

### Fixed

- fixes and improvements of old v3 issues

Details in issues of Milestone for v3.2 : https://github.com/agilare/ladecadanse/milestone/1?closed=1
