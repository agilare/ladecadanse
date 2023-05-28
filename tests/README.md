# Tests

The only automated tests currently available are end to end, with Selenium IDE (see below), there are no unit tests or other

## End to end

The scope of the application and its functionalities are basically covered; these tests are not yet very detailed but already allow to check the essential. It follows 3 dimensions, according to [Strategy] :
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

1. open project `tests/ladecadanse.side`
1. select a test in a suite
1. enter url to test (local, prod...)
1. run all tests in suite

## Strategy

### Criterias considered to build tests suites (their scope and depth)

- features
    - priority according to importance and frequency of use of feature
    - most frequent user actions scenarios
- variables
    - environnement (dev, prod...)
    - user logged in/out
    - screen size (desktop, mobile)

### Depth of checks in order to meet the user expectations of user actions, by order of detail and complexity :

1. links respond and in their content the **basic** data are displayed, according to user
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