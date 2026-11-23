#!/bin/bash

set -e

MISSING_VARS=""
for VAR in APP_SECRET DB_HOST DB_NAME DB_USER DB_PASSWORD; do
	if [ -z "$(printenv "$VAR")" ]; then
		MISSING_VARS="$MISSING_VARS $VAR"
	fi
done

if [ -n "$MISSING_VARS" ]; then
	echo "ERROR: the following mandatory environment variables are not set:$MISSING_VARS"
	exit 1
fi

echo "**** 1/7 - Make sure the uploads folder exists ****"
mkdir -p /app/public/public/uploads/thumbnails

echo "**** 2/7 - Setting env variables ****"
rm -rf /app/public/.env.local
touch /app/public/.env.local

echo "APP_ENV=${APP_ENV:-prod}" >> "/app/public/.env.local"
echo "APP_DEBUG=${APP_DEBUG:-0}" >> "/app/public/.env.local"
echo "APP_SECRET=${APP_SECRET:-}" >> "/app/public/.env.local"

echo "DB_HOST=${DB_HOST:-}" >> "/app/public/.env.local"
echo "DB_PORT=${DB_PORT:-5432}" >> "/app/public/.env.local"
echo "DB_NAME=${DB_NAME:-}" >> "/app/public/.env.local"
echo "DB_USER=${DB_USER:-}" >> "/app/public/.env.local"
echo "DB_PASSWORD=${DB_PASSWORD:-}" >> "/app/public/.env.local"
echo "DB_VERSION=${DB_VERSION:-18}" >> "/app/public/.env.local"

echo "SYMFONY_TRUSTED_PROXIES=${SYMFONY_TRUSTED_PROXIES:-private_ranges}" >> "/app/public/.env.local"
echo "SYMFONY_TRUSTED_HEADERS=${SYMFONY_TRUSTED_HEADERS:-forwarded,x-forwarded-for,x-forwarded-host,x-forwarded-proto,x-forwarded-port,x-forwarded-prefix}" >> "/app/public/.env.local"

echo "**** 3/7 - Install composer dependencies ****"
cd /app/public && COMPOSER_MEMORY_LIMIT=-1 composer install

echo "**** 4/7 - Install and build front assets ****"
cd /app/public/assets && yarn install && yarn dev

echo "**** 5/7 - Setting PHP configuration ****"
echo "date.timezone=${PHP_TZ:-UTC}" >> /usr/local/etc/php/conf.d/php.ini
echo "memory_limit=${PHP_MEMORY_LIMIT:-512M}" >> /usr/local/etc/php/conf.d/php.ini
echo "upload_max_filesize=${UPLOAD_MAX_FILESIZE:-20M}" >> /usr/local/etc/php/conf.d/php.ini
echo "post_max_size=${UPLOAD_MAX_FILESIZE:-100M}" >> /usr/local/etc/php/conf.d/php.ini

echo "**** 6/7 - Migrate the database ****"
cd /app/public && \
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "**** 7/7 - Setup complete, starting the server ****"
frankenphp run --config /etc/caddy/Caddyfile
exec "$@"
