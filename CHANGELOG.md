# Changelog

## [Unreleased]

### Fixed
- misc in agenda, users, contact
- gitignore

### Added
- home : Debout les braves banner
- propose event : clarifications
- event form : clarifications for ask organisation registration 
- changelog
- TESTS.md
- docker compose recipe
- analyzers setup : PHPCompatibility, phpstan, security checker 
- git-ftp setup

### Changed

- composer : complete php extensions needed
- php errors and timezone config externalized
- readme - installation completed
- setup PHPMailer (replaces PEAR Mail)
- Zebra datepicker 1.9.19
- logs dir

### Removed

- PEAR Mail
- php obsolete functions

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
