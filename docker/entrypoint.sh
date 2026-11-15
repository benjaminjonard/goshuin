#!/bin/bash

set -e

if [ "$(id -u)" -eq 0 ]; then
    RUN_AS_USER="setpriv --reuid $USER --regid $USER --init-groups"
else
    RUN_AS_USER=""
fi

echo "**** 1/8 - Make sure the /uploads folder exists ****"
mkdir -p /uploads

echo "**** 2/8 - Create the symbolic link for the /uploads folder ****"
if [ ! -L /app/public/public/uploads ]; then
	mkdir -p /app/public/public/uploads
	cp -a /app/public/public/uploads/. /uploads/ 2>/dev/null || true
	rm -rf /app/public/public/uploads
	ln -s /uploads /app/public/public/uploads
fi
mkdir -p /uploads/thumbnails

echo "**** 3/8 - Setting env variables ****"
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

if [ "$(id -u)" -eq 0 ]; then
	echo "**** 4/8 - Apply PUID and PGID ****"
	PUID=${PUID:-1001}
	PGID=${PGID:-1001}
	if [ ! "$(id -u "$USER")" -eq "$PUID" ]; then usermod -o -u "$PUID" "$USER" ; fi
	if [ ! "$(id -g "$USER")" -eq "$PGID" ]; then groupmod -o -g "$PGID" "$USER" ; fi
	echo -e " \tUser UID :\t$(id -u "$USER")"
	echo -e " \tUser GID :\t$(id -g "$USER")"

	echo "**** 5/8 - Set permissions ****"
	find /uploads -type d \( ! -user "$USER" -o ! -group "$USER" \) -exec chown -R "$USER":"$USER" \{\} \;
	find /uploads \( ! -user "$USER" -o ! -group "$USER" \) -exec chown "$USER":"$USER" \{\} \;
	usermod -a -G "$USER" www-data
	find /uploads -type d \( ! -perm -ug+w -o ! -perm -ugo+rX \) -exec chmod -R ug+w,ugo+rX \{\} \;
	find /uploads \( ! -perm -ug+w -o ! -perm -ugo+rX \) -exec chmod ug+w,ugo+rX \{\} \;
	mkdir -p /app/public/var/log
	chown -R "$USER":"$USER" /app/public/var /app/public/.env.local /data /config
else
	echo "**** 4/8 - Running as UID $(id -u), skipping PUID and PGID ****"
	echo "**** 5/8 - Running as UID $(id -u), skipping permissions ****"
fi

echo "**** 6/8 - Migrate the database ****"
cd /app/public && \
$RUN_AS_USER php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "**** 7/8 - Fill in the administrative code of every located location ****"
cd /app/public && \
$RUN_AS_USER php bin/console app:locate || echo -e " \tSome locations could not be coded; the statistics map will be incomplete."

echo "**** 8/8 - Setup complete, starting the server ****"
exec $RUN_AS_USER frankenphp run --config /etc/caddy/Caddyfile
