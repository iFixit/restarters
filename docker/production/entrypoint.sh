#!/bin/bash
set -e

export IN_DOCKER=true

if [ ! -f /var/www/.env ]; then
  echo "No .env file found"
  exit 1
fi

if ! grep -q "APP_KEY=" /var/www/.env; then
  echo "APP_KEY is not set in the environment"
  exit 1
fi


if [ "${MIGRATE:-false}" = "true" ]; then
  task app:db:migrate -- --force
fi

task app:clear:caches

# Pass control to the command
exec "$@" 