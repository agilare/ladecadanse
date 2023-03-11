# TESTS

## Front

### Global

- main menu
    - agenda
    - lieux
    - organisateurs
    - search

- secondary menu
    - contact
    - à propos
    - annoncer
    - inscription
    - log in

- calendar

#### Logged in

- add event
- user (my account)
- logout
- admin


### Home

- today
    - filter : region
    - rss
    - event
    - lieu
    - lightbox
    - organisateur(s)
    - report
    - ical
    - prev/next category

- derniers événements
    - rss
    - event
    - lightbox
    - lieu
    - date

partenaires

#### Logged in

- event
    - copy, edit, unpublish, author


### Events

- agenda
    - filter
        - date
            - calendar
                - pick
                - write
                - month : prev, next
                - week       
            - prev, next (day, week)
        - region
        - category
        - page
    - lightbox image
    - event
    - lieu
    - report
    - ical

- event
    - ical
    - lieu
        - map
    - lightboxes
    - organisateur(s)
    - report
        - submit : 
            - success msg
            - mail to admin with type, URL, email author
    - prev, next

- announce
    - submit : mail with URL, author email, message
    - accept submit
        - mail with URL

- search
    - nb results
    - filter
    - sort
    - event, lieu, date

#### logged in

- home, agenda, event
    - edit (and process announce)
    - copy
    - unpublish
    - author
    - send


### Lieux
- latests
- menu
    - filter
        - current, old
        - A-Z, Type
        - ak, lz, all
    - lieu

#### Logged in
- add

#### Lieu

- organizer(s)
- lightboxes
- map
- description/presentation
- events
    - rss
    - filter
    - event

#### Logged in

- edit
- add event
- add room
- add/edit description/presentation


### Organizers
- latests
- menu
    - filter
        - ak, lz, all

#### Organizer

- lightboxes
- presentation
- events
    - rss
    - filter
    - event

#### Logged in

- add
- edit
- add/edit description


### Users

- register
    - success msg
    - mail to user

- login
    - password reset
        - success msg
        - mail to user
        - reset
    - rememberme
    - link to register

#### Logged in

- profile
    - edit
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
     
- logout

### Contact

- submit :
    - success msg
    - mail to admin with prénom, nom, sujet, contenu


## Back-office (admin/)

### Dashboard

- latest registrations
- latest events
- latest texts

### Elements
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