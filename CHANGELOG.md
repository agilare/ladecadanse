# Changelog

## [3.11.0] - Unreleased

### Fixed
- events : hide calendar-export-menu by default for browsers without Popover API or JS
- home : restore render broken by a call to the removed `date_lendemain()` (time-based event separators, PR #133)
- events : protect the copy form ("Coller") submit button against double-click
- lieux, organisateurs : "Passés" tab now opens on the most recent past events instead of the oldest ones
- edition : in TinyMCE texts, links to the site itself lost their `href` (relative URLs rejected by the sanitizer) ; texts saved before this fix must be edited again to restore their links
- lieux, organisateurs, events : an unknown id returned an empty 200 or a fatal error instead of the 404 the pages already had ready (`lieu.php?idL=`, `organisateur.php?idO=`, `to-ics.php?idE=`) ; `evenement-edit.php?action=editer|update` without `idE` now answers 400
- search, lieux, organisateurs, admin, events : an unexpected value in an optional url parameter (`periode`, `statut`, `tri`, `order_dir`, `seuil`, `action`) threw an uncaught exception or logged a warning instead of falling back on the default ; guards now use the new non-throwing `Validateur::isAcceptedUrlQueryValue()`
- rss : `/event/rss.php` called without a `type` parameter logged a warning before returning its 400
- rss : the channel `pubDate` was assigned inside the items loop, so it kept the date of the *oldest* addition ; the "events today" feed announced a date five months old. It is now the most recent addition, and an empty feed falls back on the current time instead of a raw Unix timestamp, invalid as RFC 822. `lastBuildDate` added, and the channel `description`, left empty, is now filled
- rss : the `$item` array was not reset between iterations, so an event without a flyer nor an image inherited the illustration of the previous one (2 of the 20 items of the "latest added" feed)
- rss : autodiscovery `<link rel="alternate">` tags and links to the feeds used a relative `href`, resolved against the current page ; a visitor arriving on `http://` had their subscription recorded in `http://` and paid a 301 on every poll (39.7% of the feeds requests). They now use the new `SITE_CANONICAL_URL` constant, overridable from `app/env.php`
- rss : the autodiscovery tag of a lieu feed was never emitted, `HtmlShrink::showLinkRss()` comparing `$nom_page` to `lieu` where `bootstrap.php` gives it the `folder/file` form
- rss : links inside item descriptions (lieu page) and the `rel="self"` are now absolute ; the latter is rebuilt from the validated parameters instead of reflecting `REQUEST_URI`, which gave a different self-link per URL variant
- events : a `genre` absent from the configuration crashed the home page and logged warnings in the listings ; it now displays as "divers" through the new `Evenement::genreLabel()`
- events : an event referencing a deleted lieu crashed the pages listing it ; its location now falls back on the free text fields stored in the event
- csp : two scripts blocked in production — the bots tracker (darkvisitors.com now redirects to knownagents.com) and the AssetManager import map, an inline tag missing its nonce
- csp : front-end errors never reached GlitchTip, the DSN host was missing from `connect-src` while only the CDN was allowed in `script-src`
- forms : the date field is usable with the keyboard again
- events edit : no longer assumes the file fields are present in `$_FILES`
- admin : in index, "Latest texts added" called `texteHtmlReduit` with a missing argument
- auth : remove the obsolete warning about email in the login field
- lieu : typo on the lieu page when there is no incoming event
- header : spacing and alignment of the search area
- events : on the event page, move "Ajouter à un agenda" left to the action links
- tests : adapt the Selenium cases to the changes of this release

### Added
- users, events edit : personal default values for adding an event — category, start and end time, lieu, organisateur(s) and price, set in the profile ("Événements" fieldset) and applied to the "Ajouter un événement" form at creation only, where a discreet note links back to the settings. `?idL=` and `?idO=` still take precedence, editing an existing event is untouched, and a default pointing at a lieu or organisateur since deactivated is simply ignored. Settings live in a new JSON column `personne.settings` (`events > new_defaults`), so later preferences need no further migration ; create the column with `resources/v3-11-0_personne-add-settings.sql`
- events : a past event is now a read-only archive below `groupe` 6 — its edit form and every "Éditer" button are gone, and deletion is refused, so an old event can no longer be recycled into a new one (which silently overwrote the original) ; Copier — the right way to reprogram it — and Dépublier stay available, and editors keep full access. An event counts as past once `06:00:00` has struck on the day after `dateEvenement`, its end time being taken into account when it is later. In the user page, archived rows are dimmed, at full opacity on hover
- lieux edit : optional latitude/longitude fields, so the map coordinates can be set from the site instead of directly in the database (a map picker will come later)
- events : in forms add <optgroup> by canton for lieux select
- lieux, organisateurs, gererEvenements, users : add button inside search fields to clear and resubmit the filter in one click
- bots : internal monitoring of bots and suspicious IPs (table `bot_monitor`, honeypot, admin dashboard) ; before enabling `BOT_MONITORING_ENABLED`, complete `app/env.php` (see `env_model.php`) and create the table (`resources/v3-11-0_bot_monitor-create-table.sql`)
- ui : keyboard shortcuts for the most common actions #112 : h (accueil), s (recherche), a (ajout), l (lieux), o (organisateurs), d (dashboard), e (édition), f (flyer), c (copie), arrows for day navigation, / to focus the search field ; bindings match on `event.key`, so they work whatever the keyboard layout
- ui : "mouseless" mode to learn those shortcuts, for ADMIN and SUPERADMIN only. `?mouseless=1` turns it on (`?mouseless=0` off), it survives navigation through `localStorage`, and while active the elements a shortcut can reach no longer answer the mouse : each one displays its key in a `<kbd>` badge (in the placeholder for text fields), and a blocked click makes that badge flash. Keyboard navigation is untouched — Tab + Enter still follows a link — and a fixed banner offers Échap or a link to leave. Ignored on coarse pointers, where there is no physical keyboard. The shortcut list now lives in a single registry (`web/js/shortcuts.js`) shared by both features, so they cannot drift apart
- events : admins can notify the event author by email of the changes made, picking pre-written motifs and/or writing a free-text message #149
- ui : `j` and `k` walk the entity listings one entity at a time — home agenda, lieux, organisateurs, gererEvenements and users — instead of tabbing through every secondary link of a row. They move the focus to the row's main link, so Enter opens it, screen readers announce it and Tab carries on from there ; the current row is highlighted through `:focus-within`. No wrap-around and no page jump : `j` on the last row of a paginated listing stays put. The listings are described per page in a `LISTS` registry next to the shortcut one (`web/js/shortcuts.js`). In mouseless mode the row links stay clickable and unbadged — badging fifty rows would drown the page — and the banner announces the two keys instead
- events : the ajax "Dépublier" button, so far limited to the home, lieu and organisateur listings, is now also on the event page, the user profile, gererEvenements and the search results (SUPERADMIN only)
- events edit : POC of an always-visible Zebra Datepicker calendar under the date field, for SUPERADMIN only
- events edit : the flyer and the photo can be added by pasting an image URL ; open to every logged-in user (was admins only), not on the public "Proposer un événement" form
- events edit : confirm before leaving the form with unsaved changes
- events edit : the "Références" field becomes a textarea, one URL per line (stored format unchanged)
- search : the results page keeps the query in the header field and offers a "Copier" button on each result ; on mobile the search field stays open on that page
- home : time-based separators in the events list #105 (PR #133)
- don : add Postfinance and Twint payment methods, add Bernex to "Soutiens"
- tests : new Codeception `site` suite (PhpBrowser) covering the bots honeypot, the author notification, permissions, statuses and the bots dashboard ; set `LADECADANSE_SITE_URL` in `tests/.env`
- tests : Vitest setup and first JS unit tests (`npm test`)
- tests : new `RssCest` in the `site` suite covering the feed status contract (410 on retired feeds, 400 on unknown type or invalid id, no echo of the received value, no session started), the channel metadata, the canonical self-link, the absence of `<style>` and the conditional GET ; `SiteTester::grabResponseHeader()` added, PhpBrowser being able to set request headers but not to read response ones

### Changed
- assets : a missing file is now reported as a warning in `var/logs/activity.log` instead of the PHP error log, which it was filling one line per page view
- admin : in gererEvenements, the events list gets a fixed height (70vh) with its own scrollbar and sticky column headers ; checkboxes column moved first
- events : in the listing tables, action icons aligned right so single-icon cells line up with the others
- lieux : store `lat`/`lng` as `DECIMAL(10,7)` instead of `FLOAT(10,6)`, whose single precision lost about 0.5 m on Geneva coordinates ; apply `resources/v3-11-0_lieu-lat-lng-decimal.sql`
- events : in forms, lieux select options values are displayed as is
- edition : larger image previews in the edit forms, using the normal version of the file instead of the thumbnail
- events edit : the "E-mail à l'auteur" fieldset previews the message that will be sent, built by `AuteurNotifier` so it cannot drift from the actual mail ; the free-text message is optional
- events edit : move the "Statut de l'événement" fieldset before "Catégorie", radio options on one line on desktop, shorter labels, site badge style for "complet"/"annulé"
- events edit : temporarily disable the announce about organisateur registration
- don : disable the wemakeit widget (unavailable from July 31) and replace the "Autres moyens possibles" line with an intro paragraph
- monitoring : for GlitchTip upgrade the Sentry browser SDK from 9.14 to 10.69, pin the CDN bundle with an SRI `integrity` hash and drop `tracesSampleRate`, inert on the errors-only bundle
- analyzers : finalise the Psalm configuration (globals, taint escapes, insane-comparison plugin, baseline regenerated) and add the `composer psalm:taint` script
- analyzers : fix PHPStan config gaps (exclusions, runtime constants and feature flags, disallowed-calls presets) and regenerate the stale baseline
- build : declare the required Node version via `engines`, name the npm package, document `npm install` and `npm test`
- deps-dev : update phpmailer, phpstan, rector, rector/jack, select2, vlucas/phpdotenv, phan, psalm, spaze/phpstan-disallowed-calls
- deploy : update git-ftp-ignore for the agents and analyzers files
- docs : add AGENTS.md as the single source of project guidance for coding agents, CLAUDE.md now points to it
- ui : on desktop, align the back-to-top button with the `#global` container corner instead of the screen corner, where it blended into the identical body background
- user-edit : remove redundant isset() check on always-present `organisateurs` field
- rss : the `type` whitelist moved above `require_once bootstrap.php`, so the 400 and 410 answers no longer start a session nor open the two database connections ; they carry half of the script traffic. Retired feeds (`evenement_commentaires`, removed with the comments feature about three years ago, still 30.5% of the requests) answer `410 Gone` rather than 400, so readers flag the subscription in error and crawlers drop the URL. The id parameter moves from `is_numeric()` to `FILTER_VALIDATE_INT`, which rejects `1e3`, `1.9` and null or negative values
- rss : responses are cached in `var/cache/rss/` for 900 s and served with `ETag` and `Last-Modified`, so a reader that comes back unchanged gets a 304 without body, without SQL query and without rendering — there was no 304 at all before. The cache is read before bootstrap, so a hit boots nothing ; both validators are derived from the content rather than from `filemtime()`, so they stay stable while the agenda does not move. A non-writable cache degrades to direct generation instead of a 500
- rss : drop the `<style>` block embedded in every item description, dead weight for readers that sanitize HTML and, for the others, an unscoped selector leaking onto other feeds ; the lieu feed loses 22% of its size

### Security
- events edit : stored XSS in the "Organisateur(s)" select2 list, whose renderer interpolated decoded values into an HTML string ; the template is now built as DOM nodes
- deps : bump guzzlehttp/guzzle, guzzlehttp/psr7, symfony/dom-crawler, symfony/html-sanitizer, symfony/yaml to fix 20 known security advisories (cookie handling, CRLF/host-confusion injection, XSS bypass, XXE, ReDoS/DoS)
- deps-dev : bump js-yaml, brace-expansion


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


## [3.5.5] - 14.02.2025

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
