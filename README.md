# Goshuin

A self-hosted manager for a collection of goshuin — the seals brushed by hand at Japanese temples and shrines — and the goshuincho they are collected in.

One goshuincho is one journey. Record the temple, the date and the photograph of the seal, and the application works out the rest: when the trip happened, which region it covered, how much it cost. Then it shows you the book rather than its database row.

## Running an instance

You need Docker and nothing else. No PHP, no Composer, no Node on the host.

Create a `compose.yaml`:

```yaml
services:
    goshuin:
        image: benjaminjonard/goshuin:latest
        container_name: goshuin
        restart: unless-stopped
        ports:
            - "8080:80"
        environment:
            - DB_HOST=database
            - DB_PORT=5432
            - DB_NAME=goshuin
            - DB_USER=goshuin
            - DB_PASSWORD=change-me
            - DB_VERSION=18
            - PHP_TZ=Europe/Paris
            - UPLOAD_MAX_FILESIZE=100M
            # Set to 1 when the instance is served over HTTPS
            - HTTPS_ENABLED=0
            # Match your host user so uploaded files are yours
            - PUID=1000
            - PGID=1000
            # The only two outbound requests the instance makes. Map tiles come
            # from OpenStreetMap; the place search from a Photon instance, and
            # Photon is the only geocoder this speaks to. Empty PHOTON_HOST_URL to
            # remove the search altogether, or point it at your own Photon so
            # nothing leaves your network.
            - MAP_TILE_URL=https://tile.openstreetmap.org/{z}/{x}/{y}.png
            - MAP_ATTRIBUTION=© OpenStreetMap contributors
            - PHOTON_HOST_URL=https://photon.komoot.io
        depends_on:
            - database
        volumes:
            - ./uploads:/uploads

    database:
        image: postgres:18
        container_name: goshuin-database
        restart: unless-stopped
        environment:
            - POSTGRES_DB=goshuin
            - POSTGRES_USER=goshuin
            - POSTGRES_PASSWORD=change-me
        volumes:
            - ./postgres:/var/lib/postgresql
```

Then:

```
docker compose up -d
```

Open the instance and it will ask you to create the administrator account. There is no default password, and the setup page stops being reachable once that account exists.

### What to back up

Two things: the PostgreSQL volume, and `./uploads`. Everything else is rebuilt from the image.

`uploads/thumbnails` holds generated derivatives and can be excluded — they are regenerated from the originals.

### It does not phone home

No telemetry, no update check, no external fonts or scripts. The one outbound request the application makes is for map tiles, from OpenStreetMap by default and configurable. With no tile source reachable, maps still show their markers over a plain background.

## Developing

```
git clone …
cd goshuin
docker compose up
```

`docker-compose.yml` in this repository is a development file: it builds `Dockerfile.dev`, mounts the working tree, installs Composer and Yarn dependencies on start, and runs the FrankenPHP worker with `watch` so a PHP change is picked up without a restart.

Rebuild assets on change:

```
docker compose exec goshuin sh -c 'cd assets && yarn watch'
```

Run the test suite:

```
docker compose exec goshuin composer test:phpunit
docker compose exec goshuin composer test:paratest
```

### How the project is put together

Planning artifacts live in `_bmad-output/planning-artifacts/`: the product brief, the PRD with its numbered requirements, the architecture spine with its invariants, and the epic and story breakdown. The spine is the one to read before changing anything — it records the decisions that are not visible in the code, and why.

## Licence

MIT.
