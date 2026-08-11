#!/bin/bash

set -e

echo "**** 1/7 - Make sure the /uploads folder exists ****"
mkdir -p /uploads /uploads/thumbnails

echo "**** 2/7 - Create the symbolic link for the /uploads folder ****"
if [ ! -L /app/public/public/uploads ]; then
	rm -rf /app/public/public/uploads
	ln -s /uploads /app/public/public/uploads
fi

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
