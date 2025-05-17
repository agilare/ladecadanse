# Changelog

## [3.7.4] - Unreleased

### Added
- agenda : add 404 header if day has no results

### Changed
- home: refactoring

# Removed
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

# Removed
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
