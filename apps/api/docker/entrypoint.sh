#!/bin/sh
set -e

# Single-instance deployment: safe to run migrations on every boot. If this
# ever runs as multiple replicas, move this to a one-off deploy step instead.
php artisan migrate --force

exec "$@"
