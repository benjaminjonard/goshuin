#!/bin/bash

set -e

echo "**** 1/10 - Make sure the /uploads folder exists ****"
mkdir -p /uploads

echo "**** 2/10 - Create the symbolic link for the /uploads folder ****"
if [ ! -L /app/public/public/uploads ]; then
	mkdir -p /app/public/public/uploads
	cp -a /app/public/public/uploads/. /uploads/ 2>/dev/null || true
	rm -rf /app/public/public/uploads
	ln -s /uploads /app/public/public/uploads
fi
mkdir -p /uploads/thumbnails

echo "**** 3/10 - Setting env variables ****"
rm -rf /app/public/.env.local
touch /app/public/.env.local

echo "APP_ENV=${APP_ENV:-prod}" >> "/app/public/.env.local"
echo "APP_DEBUG=${APP_DEBUG:-0}" >> "/app/public/.env.local"
echo "APP_SECRET=${APP_SECRET:-$(openssl rand -base64 21)}" >> "/app/public/.env.local"

echo "DB_HOST=${DB_HOST:-}" >> "/app/public/.env.local"
echo "DB_PORT=${DB_PORT:-5432}" >> "/app/public/.env.local"
echo "DB_NAME=${DB_NAME:-}" >> "/app/public/.env.local"
echo "DB_USER=${DB_USER:-}" >> "/app/public/.env.local"
echo "DB_PASSWORD=${DB_PASSWORD:-}" >> "/app/public/.env.local"
echo "DB_VERSION=${DB_VERSION:-18}" >> "/app/public/.env.local"

echo "SYMFONY_TRUSTED_PROXIES=${SYMFONY_TRUSTED_PROXIES:-private_ranges}" >> "/app/public/.env.local"
echo "SYMFONY_TRUSTED_HEADERS=${SYMFONY_TRUSTED_HEADERS:-forwarded,x-forwarded-for,x-forwarded-host,x-forwarded-proto,x-forwarded-port,x-forwarded-prefix}" >> "/app/public/.env.local"

echo "**** 4/10 - Setting PHP configuration ****"
echo "session.cookie_secure=${HTTPS_ENABLED:-0}" >> /usr/local/etc/php/conf.d/php.ini
echo "date.timezone=${PHP_TZ:-UTC}" >> /usr/local/etc/php/conf.d/php.ini
echo "memory_limit=${PHP_MEMORY_LIMIT:-512M}" >> /usr/local/etc/php/conf.d/php.ini
echo "upload_max_filesize=${UPLOAD_MAX_FILESIZE:-20M}" >> /usr/local/etc/php/conf.d/php.ini
echo "post_max_size=${UPLOAD_MAX_FILESIZE:-100M}" >> /usr/local/etc/php/conf.d/php.ini

echo "**** 5/10 - Migrate the database ****"
cd /app/public && \
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "**** 6/10 - Fill in the administrative code of every located location ****"
cd /app/public && \
php bin/console app:locate || echo -e " \tSome locations could not be coded; the statistics map will be incomplete."

echo "**** 7/10 - Apply PUID and PGID ****"
PUID=${PUID:-1001}
PGID=${PGID:-1001}
if [ ! "$(id -u "$USER")" -eq "$PUID" ]; then usermod -o -u "$PUID" "$USER" ; fi
if [ ! "$(id -g "$USER")" -eq "$PGID" ]; then groupmod -o -g "$PGID" "$USER" ; fi
echo -e " \tUser UID :\t$(id -u "$USER")"
echo -e " \tUser GID :\t$(id -g "$USER")"

echo "**** 8/10 - Set permissions on /uploads ****"
find /uploads -type d \( ! -user "$USER" -o ! -group "$USER" \) -exec chown -R "$USER":"$USER" \{\} \;
find /uploads \( ! -user "$USER" -o ! -group "$USER" \) -exec chown "$USER":"$USER" \{\} \;
usermod -a -G "$USER" www-data
find /uploads -type d \( ! -perm -ug+w -o ! -perm -ugo+rX \) -exec chmod -R ug+w,ugo+rX \{\} \;
find /uploads \( ! -perm -ug+w -o ! -perm -ugo+rX \) -exec chmod ug+w,ugo+rX \{\} \;

echo "**** 9/10 - Create the log file ****"
mkdir -p /app/public/var/log
touch /app/public/var/log/prod.log
chown -R "$USER":"$USER" /app/public/var
chown "$USER":"$USER" /app/public/.env.local

echo "**** 10/10 - Setup complete, starting the server ****"
frankenphp run --config /etc/caddy/Caddyfile
exec "$@"
