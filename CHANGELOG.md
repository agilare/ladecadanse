# Changelog

## [Unreleased]

### Removed

- [ ] usage of globals

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
- header files (home latests, lieu, organisateur)
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
- moved most of functions into classes
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
