#!/bin/sh
# Creates the payments database on the volume, then starts FrankenPHP.
set -e
php bin/console app:db:init --quiet
exec docker-php-entrypoint "$@"
