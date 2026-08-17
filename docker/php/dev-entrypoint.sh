#!/bin/sh
chown -R ${UID:-1000}:${GID:-1000} /var/www/html/storage /var/www/html/bootstrap/cache
exec php-fpm "$@"
