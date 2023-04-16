# Tests

The only automated tests currently available are end to end, with Selenium IDE (see below), there are no unit tests or other

## End to end

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
- admin (large)

Tests that could need some adaptations in values filled by user have a name with `(vars)`

### Running the tests on an instance

1. open project `tests/ladecadanse.side`
1. select a test in a suite
1. enter url to test (local, prod...)
1. run all tests in suite

### Edit
...

## Strategy
...
