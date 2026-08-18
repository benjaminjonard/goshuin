FROM dunglas/frankenphp:php8.5 AS goshuin-base

ENV APP_ENV=prod
ENV PUID=1001
ENV PGID=1001
ENV USER=goshuin
ENV FRANKENPHP_SERVER_NAME=":80"
ENV COMPOSER_ALLOW_SUPERUSER=1

# Symfony 8.1 clones the application after each request so no state survives it.
# Removing this reopens the leak described in AD-2 of the architecture spine.
ENV FRANKENPHP_RESET_KERNEL=1

COPY ./ /app/public
COPY ./docker/Caddyfile /etc/caddy/Caddyfile

RUN set -eux ; \
    addgroup --gid "$PGID" "$USER" ; \
    adduser --gecos '' --no-create-home --disabled-password --uid "$PUID" --gid "$PGID" "$USER" ; \
    apt-get update -qq ; \
    apt-get install -qqy --no-install-recommends \
    ca-certificates \
    curl \
    openssl \
    unzip ; \
    install-php-extensions opcache pdo_pgsql intl gd zip apcu exif ; \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer ; \
    cd /app/public ; \
    COMPOSER_MEMORY_LIMIT=-1 composer install --classmap-authoritative ; \
    COMPOSER_MEMORY_LIMIT=-1 composer clearcache ; \
    apt-get purge -y unzip ; \
    apt-get autoremove -y ; \
    apt-get clean ; \
    rm -rf /var/lib/apt/lists/* ; \
    rm -rf /usr/local/bin/composer ; \
    chown -R "$USER":"$USER" /app/public ; \
    chmod +x /app/public/docker/entrypoint.sh ; \
    mkdir /run/php ; \
    cp /app/public/docker/php.ini /usr/local/etc/php/conf.d/php.ini

FROM node:26-bookworm AS build-node

WORKDIR /app/assets

COPY ./assets/ ./
COPY --from=goshuin-base /app/public/vendor/symfony/ux-live-component/assets/ /app/vendor/symfony/ux-live-component/assets/

RUN set -eux ; \
    npm install -g corepack ; \
    corepack enable ; \
    yarn install --immutable

# Tailwind scans the @source paths declared in assets/styles/app.css. Without
# these two trees the build emits base and theme only, and every utility class
# silently disappears from the stylesheet.
COPY ./templates/ /app/templates/
COPY ./src/ /app/src/

RUN set -eux ; \
    mkdir -p /app/public/build ; \
    yarn build

FROM goshuin-base AS goshuin-final

COPY --from=build-node /app/public/build/ /app/public/public/build/

VOLUME /uploads

EXPOSE 80
EXPOSE 443

WORKDIR /app/public

HEALTHCHECK CMD curl --fail http://localhost:80/ || exit 1

ENTRYPOINT ["sh", "/app/public/docker/entrypoint.sh"]
