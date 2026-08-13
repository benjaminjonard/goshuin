# Goshuin

A self-hosted manager for a goshuin — the seals brushed by hand at Japanese temples and shrines — and the goshuincho they are collected in.

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
########################################################################################################

APP_DEBUG=0
APP_ENV=prod
#APP_SECRET=

HTTPS_ENABLED=1
UPLOAD_MAX_FILESIZE=20M
PHP_MEMORY_LIMIT=512M
PHP_TZ=Europe/Paris

PHOTON_HOST_URL=https://photon.komoot.io

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

## Licence

Goshuin is an Open Source software, released under the MIT License.