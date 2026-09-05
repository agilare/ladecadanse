# Tests

There is currently 2 automated tests available for :
- user application (index.php) : end to end, with Selenium IDE
- API (api.php) : functional with a Codeception script

## Automated tests

### End to end (user application)

The scope of the application and its features are basically covered; these tests are not yet very detailed but already allow to check the essential. It follows 3 dimensions, according to [Strategy](#Strategy):
- criteria
- depth : thus far level 1. of "Depth of checks in order..."; doesn't go into too much depth due to lack of time/knowledge of the tool and because the feat will probably change a lot in the coming time
- scope : "Map"

#### Prerequisites

- [Selenium IDE](https://www.selenium.dev/selenium-ide/) browser extension installed
- fake data in the instance to test (to create manually) :
    - users : an admin, an actor (the most important profiles)
    - entities : dozen of events (of 2-3 categories), some venues and organizers (try to link these entities, like an Event in a Venue with some Organizers)
    - in pages :
        - home : 1st event is in a registered Venue, min. 2 events in category "Fêtes"
        - agenda : the Saturday of the 5th week of the current months has min 2 events
- mail sending parameters are complete in `env.php` and mails can be sent (really or using a mail catcher for development like [FakeSMTP](https://nilhcem.com/FakeSMTP/))

#### The tests

Suites available by user type (screen size used) :
- public (small)
- actor (large)
- admin (large) : avoid tests editing and deleting items on prod !

Tests have some name conventions :
- "vars" : could need some adaptations in values filled by user
- "r" : data is added, edited or deleted; restoration could be needed after the test

> **À re-enregistrer** — la partie « lieu » du projet clique des cases `id=categorie_bistrot`,
> `id=categorie_salle`… que le formulaire ne porte plus : les catégories sont depuis la 3.13.0 un
> Select2 multiple `#categories` (#117). Le reste du scénario suit le déplacement de la page vers
> `/lieu/edit.php` et le renommage de `determinant` en `preposition_nom`, mais ces clics-là ne
> peuvent pas être transposés sans repasser par l'enregistreur.

#### Running the tests on an instance

1. in your browser, launch Selenium IDE and open project `tests/ladecadanse.side`
2. disable website CSP and to detect PHP errors ensure `ENV` equals 'dev' (when Whoops error page appears, a test is garanteed to fail)
3. select a test in a suite
4. enter the URL to test (local, prod...)
5. run all tests in suite

#### Edit
...

### Functional (API)

#### Prerequisites

The application API must be configured with its access credentials defined in `app/env.php` (`LADECADANSE_API_USER` and `LADECADANSE_API_KEY`)

#### Setup

Copy `tests/.env_model` to a new file `tests/.env` and enter the values used by your tests (URL targeted and submitted credentials)

#### The tests

- authentication
- request parameters validation
- get events, with response :
    - as JSON
    - correct structure
    - required values

#### Running the tests on an instance

`composer test:api`

### Functional (site)

Server-side tests of the user application, run with Codeception's `site` suite (PhpBrowser). They cover the areas where a regression is silent and costly — permission rules, form validation, and the server-rendered contract the recent JavaScript relies on.

They are **read-only**: every POST is deliberately invalid, so validation fails before any `UPDATE` and no mail is ever sent. The suite can be replayed indefinitely on the same instance.

The one deliberate exception is `UserRegisterCest::potDeMielRempliBloqueInscription`, whose payload is valid except for the honeypot — the only way to prove that guard alone blocks the submission. If the honeypot ever disappears, that test fails *and* registers a `zz-codeception-pot-de-miel` account: delete it before replaying the suite.

JavaScript is out of scope here (PhpBrowser does not execute it): the `beforeunload` guard and the datepicker remain covered by the Selenium IDE project, while the keyboard shortcuts and the mouseless mode have unit tests in `tests/js/` (vitest + jsdom, `npm test`).

#### Prerequisites

- an instance populated with the fake data described in [End to end](#end-to-end-user-application), reachable at `LADECADANSE_SITE_URL`
- `BOT_MONITORING_ENABLED` set to `true` in `app/env.php` (for `BotMonitoringCest` and `AdminBotsCest`)
- an admin account (`groupe` <= 4) and an actor account (`groupe` 8)
- three known event ids, see below

Note: `tests/Api/ApiCest.php` requires `app/env.php` at file level and `codeception.yml` has `lint: true`, so a missing `app/env.php` breaks even a `site`-only run.

#### Setup

In `tests/.env` (copied from `tests/.env_model`), fill in:

- `LADECADANSE_SITE_ADMIN_USER` / `LADECADANSE_SITE_ADMIN_PASS`
- `LADECADANSE_SITE_ACTOR_USER` / `LADECADANSE_SITE_ACTOR_PASS`
- `LADECADANSE_TEST_EVENT_ID_AUTEUR` — an event submitted by an author (`groupe` >= 6) or anonymously, i.e. one for which the "E-mail à l'auteur" fieldset shows up
- `LADECADANSE_TEST_EVENT_ID_ACTOR_OWN` — an event whose author is the actor account
- `LADECADANSE_TEST_EVENT_ID_FOREIGN` — an event the actor account may not edit
- `LADECADANSE_TEST_EVENT_ID_ACTOR_OWN_PAST` — a **past** event whose author is the actor account
- `LADECADANSE_TEST_LIEU_ID_WITH_PAST_EVENTS` — a lieu holding several past events, spread over several dates
- `LADECADANSE_TEST_ORGA_ID_WITH_PAST_EVENTS` — an organisateur holding at least one past event
- `LADECADANSE_TEST_LIEU_ID_WITH_LONG_TEXT` — a lieu whose description is long enough (over 550 characters) for the server to render it collapsed
- `LADECADANSE_TEST_ORGA_ID_ACTOR_OWN` — an organisateur the actor account authored, or is a member of
- `LADECADANSE_TEST_ORGA_ID_FOREIGN` — an organisateur the actor account may not edit

A past event is a read-only archive for anyone below `groupe` 6, so the first three fixtures must
point at **future** events — a past one would make the "can edit" tests fail for the wrong reason.
An event counts as past once `06:00:00` has struck on the day after `dateEvenement`.

Tests whose variables are left empty are reported as **skipped**, not failed.

#### The tests

- `EvenementNotifierAuteurCest` — "E-mail à l'auteur" (issue #149): who sees the fieldset, the fact that motif and message are both optional, and that forged motif keys are never echoed back
- `EvenementEditPermissionsCest` — who may edit an event (anonymous, actor, admin), and when: a past event is a read-only archive below `groupe` 6, though Copier and Dépublier stay available
- `EvenementStatutCest` — which status radios are rendered, and to whom
- `AdminBotsCest` — the three views of the bot dashboard and its access control
- `FormulairesRegressionCest` — server-side contract of the recent JS: `body[data-page]`, the `formulaire=ok` hidden field of `event/copy.php`, the clear-search button
- `BotMonitoringCest` — honeypot (204, footer link, robots.txt)
- `EvenementsPassesTriCest` — the sort menu of the "Passés" tab on the lieu and organisateur pages: which tab offers it, that both directions actually reorder the query, that either one lands on the most recent past events, and that the choice is remembered in session across both pages
- `UserRegisterCest` — public registration: the form's guards (CSRF token, single use, honeypot, both affiliation selects), the password rules, an already taken login, and two malformed POSTs that used to raise a PHP warning (missing `organisateurs[]`, scalar fields posted as arrays). The "email already taken" branch — which renders the success message without inserting anything, to avoid email enumeration — is knowingly left uncovered: it would need a fixture address really present in the database, and a stale one would turn the test into an account creation
- `LieuTexteRepliableCest` — server-side contract of the collapsible descriptions: the text is served whole (never truncated in PHP), the toggle is a sibling of the capped block and wired to it by `aria-controls`, and the `js` marker that gates the whole collapse is in the `<head>`
- `OrganisateurEditFormulaireCest` — the organisateur edit form (issue #115): the fields the JS and the processing depend on, the title that links back to the fiche, the "Publié / Dépublié" status labels, the fact that the status is offered to admins only, and that an actor may no longer edit someone else's fiche

#### Running the tests on an instance

`composer test:site` (or `composer test` for every suite)

## Strategy

### Criteria considered to build tests suites (their scope and depth)

- features
    - priority according to importance and frequency of use of feature
    - most frequent user actions scenarios
- variables
    - environnement (dev, prod...)
    - user logged in/out
    - screen size (desktop, mobile)

### Depth of checks in order to meet the user expectations of user actions, by order of precision and complexity to test :

1. links respond and in their content the **basic** data are displayed, according to user :
    - selection by
        - filtering
            - entity type : event, lieu...
            - entity values : region, date
            - format (html, json, rss...)
        - scope
            - detail : collection or single item
            - entity detail (values selection) : summary or detailed
    - intention : view, add/edit, delete, other (report, export...)
1. data displayed is **relevant** according to filter (events : date, category, etc.) and scope (as in the previous point)
1. users are guided in expected way by handling their **mistakes** (error handling, particularly in forms)
1. data is **modified exactly** according to user action on it
1. special and unexpected cases are handled
