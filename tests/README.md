# Tests

There is currently 2 automated tests available for :
- user application : end to end, with Selenium IDE (see below)
- API : functional with a Codeception script

## End to end (user application)

The scope of the application and its functionalities are basically covered; these tests are not yet very detailed but already allow to check the essential.

According to (tests/TESTS.md)[TESTS.md] :
- criterias
- depth : thus far level 1. of "Depth of checks in order..."; doesn't go into too much depth due to lack of time/knowledge of the tool and because the feat will probably change a lot in the coming time
- scope : "Map"

### Prerequisites

- [Selenium IDE](https://www.selenium.dev/selenium-ide/) browser extension insalled
- fake data in the instance to test :
    - users : an admin, an actor
    - entities : about a hundred events (of 2-3 categories), some lieux and organizers (try to link these entities like an Event in a Lieu with some Organizers)
- mail sending is configured in `env.php` and mails can be sent, really or using a mail catcher for development like [FakeSMTP](https://nilhcem.com/FakeSMTP/)

### The tests

Suites availables by user type (screen size) :
- public (small)
- actor (large)
- admin (large) : avoid edit and delete tests on prod !

Tests names contains some codes :
- vars : could need some adaptations in values filled by user
- r : data is added, edited or deleted; restoration needed after the test

### Running the tests on an instance

1. in your browser, launch Selenium IDE and open project `tests/ladecadanse.side`
1. select a test in a suite
1. enter url to test (local, prod...)
1. run all tests in suite

### Edit
...

## Functional (API)

### Prerequisites

The application API must be enabled with the credentials defined in `app/env.php` (`LADECADANSE_API_USER` and `LADECADANSE_API_KEY`)

### Setup

Define the URL targeted and the credentials used by the tests :

1. in the root folder create a `codeception.yml` (which will be merged with base configuration `codeception.dist.yml`, at execution) containing your [custom configuration](https://codeception.com/docs/reference/Configuration#Configuration-Templates-distyml), especially of the REST test module with your URL of the API[1], for ex. (do not change the values `depends` and `part`) :
    ```yml
        suites:
            api:
                modules:
                    enabled:
                        - REST:
                            url: http://ladecadanse.local/api.php
                            depends: PhpBrowser
                            part: Json
    ```
1. fill your credentials in your test configuration

[1]: As for API credentials, in the future, the URL above will be stored in an env variable


### The tests

- authentication
- request parameters
- get events, with response :
    - as JSON
    - correct structure of events
    - required values of events

### Running the tests on an instance

`php vendor/bin/codecept run`

## Strategy
...
