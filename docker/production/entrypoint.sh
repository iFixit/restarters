#!/bin/bash
set -e

export IN_DOCKER=true

# Check if APP_KEY is set
if [ -z "${APP_KEY}" ]; then
  echo "APP_KEY is not set in the environment"
  exit 1
fi

if [ "${MIGRATE:-false}" = "true" ]; then
  task app:db:migrate -- --force
fi

task app:clear:caches

# Pass control to the command
exec "$@" 