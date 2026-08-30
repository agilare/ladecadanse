# Changelog

## [Non publié]

Upgrade steps (redirects, side effects) : [UPGRADE.md](UPGRADE.md).
Feature documentation : [docs/](docs/).

### Added
- agenda : on the day's own listing, each event card says where it stands against the moment the page was loaded #51 — a countdown before it starts, the share elapsed while it runs, `terminé` once it is over ; off by default behind `EVENT_TIME_STATUS_ENABLED` — see [docs/agenda.md](docs/agenda.md)
- config : feature flags gain a third state through `Ladecadanse\FeatureFlag` — `false`, `'preview'` (administrators only), `true` — to try a substantial feature on the live site before opening it to everyone, the preview saying so in the interface — see the [README](README.md#accepter-les-pdf-dans-les-champs-image)
- event edit, admin events : the flyer and image fields accept a PDF, first page only, converted to WebP — organisers are usually sent their poster as a PDF, and converting it themselves lost flyers ; off by default behind `PDF_CONVERSION_ENABLED` — see the [README](README.md#accepter-les-pdf-dans-les-champs-image)
- admin events, user profile : an "Image" column opens the event tables, just before the title — recognising an event, or checking that a flyer did get uploaded, meant opening its page until now — see [docs/admin-evenements.md](docs/admin-evenements.md)
- local development : `composer prod-copy` builds an anonymised local copy of the production database — the last N events added and their whole referential closure, identifiers preserved because flyer filenames encode them, with the matching images fetched over plain HTTPS from the public site ; passwords, e-mails, cookies, the legacy sha1 salt and the private admin notes are replaced between the SELECT and the INSERT, so no intermediate file ever holds real personal data and the 1 GB dump never has to travel ; the files come down over a single SSH connection, which also reaches what the server refuses to serve — see [docs/prod-copy.md](docs/prod-copy.md)

### Fixed
- lieux : the localité filter no longer takes the whole listing down when the `localite` table holds a canton the configuration does not name — the fourre-tout localité keeps an empty canton until `v3-12-0_localite-france.sql` has been played, and `lieu/lieux.php` answered a fatal `Undefined array key ""` ; the code is now shown as the group's label, escaped, so the mismatch is visible rather than fatal
- lieu edit, organisateur edit : a new fiche's logo and photo are named after the identifier the database actually assigned, not after `MAX(id) + 1` read before the INSERT, and their extension follows the real format instead of the name they were sent under — same two defects already fixed on events ; the photo also took the *logo's* extension, so a fiche given a photo alone got a file named after an extension that was never there
- organisateur edit : modifying a fiche no longer makes the editor its author — `idPersonne` was overwritten on every update, so the first administrator to fix a typo dispossessed the organiser of their own fiche
- organisateur edit : the duplicate-name check runs — it waited for an `insert` action the page never gave it, and had therefore never fired since it was written ; it now covers renames too, and asks the database instead of walking every active organisateur in PHP
- organisateur edit : an actor modifying a dépubliée fiche no longer republishes it without knowing — the form posted a hidden `statut=actif` for anyone not allowed to choose
- organisateur edit : the action parameter was validated against an undefined variable, the status radios were built from the *lieu* statuses, and the fieldsets were unbalanced — a `<legend>` outside any `fieldset`, a stray `</fieldset>`
- event edit : a new event's flyer is named after the identifier the database actually assigned, not after `MAX(idEvenement) + 1` read before the INSERT — the two drift apart as soon as an event has been deleted — see [docs/evenements.md](docs/evenements.md)
- event copy : the copies' flyers are named after each new event's own identifier, same defect as above — and their source is now the original event's file, where each copy took the name left by the previous one and copied from that
- admin events : a bulk image replacement names the file after its real format instead of the extension of the name it was sent under, as `evenement-edit.php` already did — a PNG sent as `poster.jpg` produced a `.jpg` holding PNG, then served under a type browsers refuse
- event edit : a PDF pasted into "ou coller une URL" is converted like one uploaded through the file field — only one of the two ways of providing an image took PDFs ; this path renders server-side with Imagick — see the [README](README.md#accepter-les-pdf-dans-les-champs-image)
- server config : the yearly redirect of event images covers `.webp`, which it had never listed alongside jpg, png and gif — a webp flyer moved by the January archiving answered 404 where the other formats got their 301 — see [docs/evenements.md](docs/evenements.md)
- search : the actions column no longer reserves its 80px for a visitor who is not logged in, where it holds the calendar export alone ; the width stays on the lieu and organisateur listings, which share the class
- admin events : a bulk replace no longer wipes the organisateurs of the selected events #125 — the DELETE ran after every successful UPDATE, against the page's own promise that only non-empty fields overwrite — see [docs/admin-evenements.md](docs/admin-evenements.md)
- admin events : the "delete the image" branch removed the file read by the flyer branch (`$affImg` for `$affimage`), the schedules were re-read from `$_POST` inside the loop, and the posted statut was not validated
- admin events : an event without a localité is listed again — the `JOIN localite` was an inner join, so such an event vanished from the very screen where it could be fixed
- admin events : the bulk form carries a CSRF token, which it lacked although it deletes ; the number of rows is validated against a list instead of being any integer, so `LIMIT 0,999999` no longer passes
- forms : the clear button of a search field empties that field alone — it emptied every `input[type=search]` of the form, which went unnoticed as long as no page carried more than one filter
- events, lieux, users edit : deselecting an organisateur now sticks after a validation error — the multiple select merged the posted selection with the organisateurs read back from the database, so one just removed came back ticked ; the hidden `formulaire=ok` witness now decides alone, a fully deselected select posting no key at all
- forms : clicking the × of a Select2 field deselects without unrolling the dropdown — the global × ended its work with an explicit toggle, and a tag's × let the click reach the container, which read it as a request to open

### Changed
- organisateurs : `organisateur-edit.php` becomes `organisateur/edit.php` #115, with a 301 — the form is processed before the first byte of HTML, `OrganisateurEdition` moves to PDO on the model of `SalleEdition`, and the two copies of the image block become one included partial — see [UPGRADE.md](UPGRADE.md) and [docs/lieux-organisateurs.md](docs/lieux-organisateurs.md)
- organisateur edit : the title names the fiche and links back to it, the status radios read « Publié / Dépublié / Ancien » rather than the database's own words, the presentation editor sits beside its label instead of below it, and the fields no longer run off a phone's screen
- edition : TinyMCE speaks French, and pasted content is stripped of the styles, classes and wrapper tags the server discards anyway — the author saw a layout in the editor that vanished on save
- uploads : the image-field lifecycle — naming, replacement, deletion, thumbnail — is shared by `LieuEdition` and `OrganisateurEdition` through `HandlesImageUploads`, and `evenement-edit.php` takes its MIME-to-extension table from the same place
- admin : `admin/gererEvenements.php` becomes `admin/events.php` #125, with a 301 — the whole request is processed before the first byte of HTML, an action redirects instead of leaving a F5 to replay it, and the listing moves to `EvenementCollection` — see [docs/admin-evenements.md](docs/admin-evenements.md)
- admin events : filters, sort and rows-per-page are remembered in the session as in `admin/users.php` ; two filters are added, on the lieu name and on the author, the category filter goes, and 100 leaves the rows-per-page menu — see [docs/admin-evenements.md](docs/admin-evenements.md)
- admin events : the bulk form keeps visible only what bulk editing uses — statut, catégorie, lieu, horaires, organisateurs — and folds the rest into three `<details>` : a lieu typed by hand, the event content, the files — see [docs/admin-evenements.md](docs/admin-evenements.md)
- events : the lieu and organisateur selects, the lieu resolution and the hh:mm to datetime conversion move to the domain classes — `evenement-edit.php` and the admin form each carried their own copy, but only the first had received the 3.12.0 fixes
- tests : unit coverage for the lieu and organisateur options and for the 301 of every moved page ; the `site` suite covers the admin events screen, the organisateurs preselection of the three edit forms, and the organisateur edit form
- docs : `resources/database/README.md` inventories every sql script — the version that shipped it, what it does, and whether `ladecadanse.sql` already carries it — with a query telling a database which migrations it is missing ; the file name is not the answer, the two `v3-6-3_*` shipped with 3.7.0, and the install steps no longer tell a fresh database to replay migrations the dump already holds — see [UPGRADE.md](UPGRADE.md)
- database : `ladecadanse.sql` is the current schema again — it had fallen four versions behind, so every database created from it lacked the 3.8 and 3.9 indexes, event search included, and the `bot_monitor` table ; a fresh install imports the dump alone and replays no migration, the Docker seed mounts none, and the file says which version it matches — see [resources/database/README.md](resources/database/README.md)
- docker : the dev web image ships Composer, so `make shell` then `composer phpstan`, `composer test:api` or `composer config:build` run on the application's own PHP 8.4 and extensions — the `composer-dev` container installs `vendor/` at startup but carries a PHP of its own ; the Composer image goes from 2.7 to 2.8, which no longer opens every command with the `E_STRICT` deprecation on PHP 8.4 — see the [README](README.md#composer)

### Security
- organisateur edit : an organiser may no longer modify another organiser's fiche #115 — the check let through any account of level ACTOR, so every one of them could edit all 500 fiches ; it now asks the same question the page's own "Modifier cet organisateur" link asks — see [UPGRADE.md](UPGRADE.md)
- organisateur edit : the file already on the fiche is read from the database instead of from a hidden field of the form, and the posted statut is checked against the column's own values

## [3.12.0] - 2026-08-23

Upgrade steps (redirects, side effects) : [UPGRADE.md](UPGRADE.md).
Feature documentation : [docs/](docs/).

### Added
- events : the calendar export leaves the home and the event page for every listing — search results, lieu and organisateur pages #150 (PR #173) ; shown to all visitors but only on future events
- lieux, organisateurs : both listings get twelve columns counting the events added month by month by members, from AUTHOR up #178 — see [docs/lieux-organisateurs.md](docs/lieux-organisateurs.md)
- events : the wide layout of the event page, given to admins in 3.11.0 while the idea was being tried out, is now served to every visitor — see [docs/interface.md](docs/interface.md)
- home : below 800px the partners and the latest added events line up with the gutter `<main>` already keeps, and the partners' logos become a centred flex row — see [docs/interface.md](docs/interface.md)
- tests : the `site` suite covers the register form, the logout, the monthly counters and the event edit form ; new unit suite for `PasswordPolicy`

### Changed
- users : register, password reset and logout join `user/` next to login and dashboard, each page reworked as login was (#121, #123) ; the old urls need a 301 — see [UPGRADE.md](UPGRADE.md) and [docs/comptes.md](docs/comptes.md)
- users : the password rules move to the shared `PasswordPolicy`, which the three forms setting a password each copied, and the rejected list goes from 22 to 19 999 entries — see [docs/comptes.md](docs/comptes.md)
- ui : the legacy 2000's PNG icons (famfamfam Silk) give way to Font Awesome, and `web/interface/icons/` drops from 1469 files (~5 MB) to the 4 kept on purpose #151 — see [docs/interface.md](docs/interface.md)
- events : under 450px wide the description wraps around the illustrations column instead of stopping beside it, which shortens the page noticeably — see [docs/interface.md](docs/interface.md)
- ui : on mobile the fields of the event form take the full width and the day navigation of the agenda shows text rather than the date — see [docs/interface.md](docs/interface.md)
- events edit : the four near-identical flyer/image blocks are reduced to one definition and its calls, dead code is dropped, and the salles of the select load in one query instead of one per lieu displayed
- events : on the event page and the ics export, `idE` is validated before bootstrap — answering 400 no longer opens two MySQL connections, starts the session and mounts the log handlers, and bots produce these urls in bulk
- librairies : the dead `Collection` hierarchy is removed #216 — a proto-repository never adopted, whose public API had no caller ; `Evenement` no longer extends `Element`, and the organisateurs listing loses one SQL query per page view
- resources : the mail bodies rendered by `TemplateEngine` move to `resources/templates/`, the sql scripts (schema and migrations) to `resources/database/`
- contact : drop the "nom" and "affiliation" fields, which served nothing
- analyzers : repair the Rector configuration, which pointed at a test file moved long ago — `composer rector:dry-run` failed before analysing anything, and now runs in ~25 s where it used to exceed the 300 s composer timeout
- deployment : `.htaccess` and `.user.ini` are composed from fragments by `composer config:build` and sent with the code, where they used to be edited by hand on the server — see [docs/config-serveur.md](docs/config-serveur.md)

### Fixed
- users : for anyone who had ticked "Rester connecté-e", clicking "Sortir" had no effect at all — the cookie was cleared by a `setcookie()` whose `path` was omitted, so the deletion aimed at `/user` once the page moved there — see [docs/comptes.md](docs/comptes.md)
- users : a reset request made for a deactivated account led to a form that could not be submitted #123, and an account with the "demande" status could have its password reset yet stay stuck at the login screen — see [docs/comptes.md](docs/comptes.md)
- users : a reset link cut by a mail client threw in the middle of the page, that is a 500 in production ; a missing or malformed token now reads as an invalid request, like an expired one
- lieux, organisateurs, search, admin : a malformed `page` url parameter threw an uncaught exception — bots follow urls where the `&region` of `?page=3&region=vd` has been read as the `&reg` html entity ; the 8 paginating pages go through the new non-throwing `QueryParamValidator::pageFromQuery()`
- events send : sharing or reporting an event whose lieu was deleted, or whose location was typed as free text, logged a warning ; the fallback returned by `Evenement::getLieu()` was missing the `determinant` key
- lieux edit : saving the form as ACTOR or MEMBER logged a warning ; `image_galerie` is declared as a file field although its input is only rendered from AUTHOR up
- events edit : the "Supprimer" checkbox of the image never got re-checked after a validation error, and the preview of the saved file is now kept even when the field is in error — without it the old file could no longer be deleted
- edition : a stray character had slipped in front of the `<?php` of `librairies/Edition.php` — PHP echoed it as soon as the class was included, so text came before any header on the edit pages, and PHPStan refused to finish its run
- ui : the footer menu lit more than the current link — `$ici` was never emptied at the start of each turn and already arrived filled from the header
- ui : on mobile the action bar of an event scattered its labels across lines, the actions menu of a lieu was hidden, and the icons of the message banners were misplaced — see [docs/interface.md](docs/interface.md)
- assets : the stylesheet of the page was loaded before the extra ones, which it could therefore not override
- monitoring : `Sentry.init()` threw a `ReferenceError` in the console of visitors running an ad blocker, uBlock Origin returning the CDN bundle empty — which also fails the SRI check ; the call goes behind an existence test

### Security
- users : logging out was a GET, so any link prefetch (browser, antivirus, mail scanner) or any third-party site could close a member's session ; `user/logout.php` accepts POST only, protected by a token valid for the whole session — see [docs/comptes.md](docs/comptes.md)
- events edit, admin : six queries concatenated values coming from `$_POST` without cast nor quotes, four of them written `WHERE id=" . $connector->sanitize(...)` where the escaping is inert — outside quotes, only the `(int)` cast protects in a numeric context
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
