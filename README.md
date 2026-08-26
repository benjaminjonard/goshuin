# Goshuin

A self-hosted manager for a goshuin — the seals brushed by hand at Japanese temples and shrines — and the goshuincho they are collected in.

## Screenshots
<p align="center">
    <img width="400px" src="https://github.com/user-attachments/assets/1114515f-412c-43d8-9759-3b9f778b5a4d">
    <img width="400px" src="https://github.com/user-attachments/assets/cf991896-2237-403f-8a6c-2f6574a38678">
    <img width="400px" src="https://github.com/user-attachments/assets/c6a80187-a211-40b3-92f4-afeb61a71e93">
    <img width="400px" src="https://github.com/user-attachments/assets/dac4f57d-a8a7-42a8-a9f8-c2e234f419ce">
</p>

## Installation

#### Step 1 -> Create a `docker-compose.yml` file

```yaml
services:
    goshuin:
        image: benjaminjonard/goshuin:latest
        container_name: goshuin
        restart: unless-stopped
        ports:
            - "8080:80"
        env_file:
            - .env    
        depends_on:
            - goshuin_postgresql
        volumes:
            - "./volumes/goshuin/uploads:/uploads"

    goshuin_postgresql:
        image: postgres:18
        container_name: goshuin_postgresql
        restart: unless-stopped
        environment:
            - POSTGRES_DB=goshuin
            - POSTGRES_USER=goshuin
            - POSTGRES_PASSWORD=change-me
        volumes:
            - "./volumes/postgresql:/var/lib/postgresql/data"
```

####  Step 2 -> Create a `.env` file
```
########################################################################################################
#                                                WEB
#
# APP_DEBUG=1 displays detailed error messages
#
# APP_SECRET is a random string used for security, you can use for example openssl rand -base64 21
# APP_SECRET is automatically generated when using Docker
#
# PHP_TZ, see possible values here https://www.w3schools.com/php/php_ref_timezones.asp
#
# FRANKENPHP_THREADS_NUMBER, must be strictly superior to FRANKENPHP_WORKERS_NUMBER
########################################################################################################

APP_DEBUG=0
APP_ENV=prod
#APP_SECRET=

HTTPS_ENABLED=1
UPLOAD_MAX_FILESIZE=20M
PHP_MEMORY_LIMIT=512M
PHP_TZ=Europe/Paris

PHOTON_HOST_URL=https://photon.komoot.io

FRANKENPHP_WORKERS_NUMBER=1
FRANKENPHP_THREADS_NUMBER=2

########################################################################################################
#                                                DATABASE
########################################################################################################

DB_NAME=goshuin
DB_HOST=goshuin_postgresql
DB_PORT=5432
DB_USER=goshuin
DB_PASSWORD=goshuin
DB_VERSION=18

```

####  Step 3 -> Review both files and update values if required

####  Step 4 -> Start Goshuin
`docker compose up -d`

## Support Goshuin

There are a few things you can do to support Goshuin :

* If you like Goshuin please consider leaving a ⭐, it gives additional motivation to continue working on the project
* Report any bug or error you see
* English is not my first language, it would be a huge help if you could report any mistakes in Goshuin.

## Translations

Goshuin ships in English, French and Japanese.

You can contribute and edit translations on [Crowdin](https://crowdin.com/project/goshuin).
If you wish to contribute to a new language, please open a discussion on GitHub or Crowdin and I'll gladly add it.
You are also welcome if you want to proofread existing translations.

Do not edit the files under `translations/` by hand: Crowdin owns them, and the next sync overwrites any local change. English is the source language.

### Translations status
<!-- CROWDIN-TRANSLATIONS-PROGRESS-ACTION-START -->


#### Available

<table><tr><td align="center" valign="top"><img width="30px" height="30px" title="English" alt="English" src="https://raw.githubusercontent.com/benjaminjonard/crowdin-translations-progress-action/1.0/flags/en.png"></div><div align="center" valign="top">100%</td><td align="center" valign="top"><img width="30px" height="30px" title="French" alt="French" src="https://raw.githubusercontent.com/benjaminjonard/crowdin-translations-progress-action/1.0/flags/fr.png"></div><div align="center" valign="top">100%</td><td align="center" valign="top"><img width="30px" height="30px" title="Japanese" alt="Japanese" src="https://raw.githubusercontent.com/benjaminjonard/crowdin-translations-progress-action/1.0/flags/ja.png"></div><div align="center" valign="top">96%</td></tr></table>
<!-- CROWDIN-TRANSLATIONS-PROGRESS-ACTION-END -->

## Licence

Goshuin is an Open Source software, released under the MIT License.
