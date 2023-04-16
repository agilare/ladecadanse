# TESTS

## Criterias considered to build scope and level of tests suites
- features
    - priority according to importance and frequency of use of feature
    - most frequent user actions scenarios
- variables
    - environnement (dev, prod...)
    - user logged in/out
    - screen size (desktop, mobile)

## Depth of checks in order to meet the user expectations of user actions, by order of detail and complexity :
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
1. users are guided in expected way by handling their mistakes (error handling, particularly in forms)
1. data is changed exactly according to user action on it
1. special and unexpected cases are handled

## Map of elements to test

### caption

Evaluation of feature :
- a, b, c : importance
- 1, 2, 3 : frequency of use

(u) : user logged in

### Front

#### Global

- main menu (agenda, lieux, organisateurs, search) : tested in their appropriate section
- secondary menu
    - *contact, inscription, annoncer, login* : tested in their appropriate section
    - (u) *add event, user (my account), logout, admin (UserLevel <= 4)* : tested in their appropriate section
    - à propos
    - charte
    - don
    - fb
    - github

- *calendar*

- contact (a2)
    - submit :
        - success msg
        - mail to admin with prénom, nom, sujet, contenu

#### Home

- today events (a1)
    - list
        - filter : region
        - rss
        - prev/next category
        - item
            - event
            - lieu
            - lightbox
            - organisateur(s)
            - report
            - ical
            - (u) copy, edit, author

- latest events (b2)
    - rss
    - item
        - event
        - lightbox
        - lieu
        - date

- partenaires links menu (2c)


#### Events

- agenda (a1)
    - list
        - filter
            - region
            - date
                - calendar
                    - pick
                    - write
                    - month : prev, next
                    - week
                - prev, next (day, week)
            - category
            - page
        - order
        - item (same as Home > today events)
            - event
            - lieu
            - lightbox
            - organisateur(s)
            - report
            - ical
            - (u) copy, edit, author

- event (a1)
    - prev, next
    - ical
    - lieu
        - map
    - lightboxes
    - organisateur(s)
    - *report*
    - (u) *edit, copy, author, send*

- report (b2)
    - submit
        1. success msg
        1. mail to admin with type, URL, email author

- announce (a1)
    1. submit :
        1. success msg
        1. mail with URL, author email, message
    2. accept submit
        - mail with URL

- search (a1)
    - list
        - nb results
        - filter
        - sort
        - item : event, lieu, date

- (u) add/edit (and process announce) (a1)
...

- (u) delete (c2)
...

- (u) copy (a1)
...

- (u) send (c2)
...

- (u) unpublish (a2)
...

#### Lieux

- latests (c2)
- menu (a1)
    - filter
        - current, old
        - A-Z, Type
        - ak, lz, all
    - lieu

- (u) add (l)

##### Lieu

- item
    - (u) add event (l)
    - organizer(s)
    - lightboxes
    - map
    - description/presentation
    - events (b2)
        - rss
        - filter
        - event

- (u) add/edit (b3)
...

- (u) add room (b3)
...

- (u) add/edit description/presentation (b2)
...

#### Organizers
- latests
- menu
    - filter
        - ak, lz, all

##### Organizer

- item (a2)
    - lightboxes
    - presentation
    - events (b3)
        - rss
        - filter
        - event

- (u) add (b1)
- (u) edit (b2)


#### Users

- register (a2)
    - success msg
    - mail to user

- login (a1)
    - rememberme
    - link to register

- password reset (a2)
    - success msg
    - mail to user
    - reset

- (u) view profile (b2)
    - profile data
    - elements added
         - menu
            - events
            - lieux
            - organisateurs
            - texts
        - list
            - page
            - nb items
            - sort

- edit profile (a2)

- logout (a1)

### Back-office (admin/)

#### Dashboard

- latest registrations
- latest events
- latest texts

#### Elements
    - events
        - filters
            - category
            - title
            - page
            - nb items
            - sort
        - list
            - select
        - replace form...
    - lieux...
    - organisateurs...
    - users...