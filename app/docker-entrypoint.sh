#!/bin/sh
# Creates the payments database and Messenger's queue on the volume, then
# starts the command (FrankenPHP by default, the worker's consumer for it).
set -e
php bin/console app:db:init --quiet
php bin/console messenger:setup-transports --quiet
exec docker-php-entrypoint "$@"
