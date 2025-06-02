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

# Ensure proper permissions for public files (for nginx compatibility)
# Only fix permissions if we have write access (not in read-only scenarios)
if [ -w /var/www/public ]; then
  echo "Ensuring proper permissions for public files..."
  find /var/www/public -type f -exec chmod 644 {} \; 2>/dev/null || true
  find /var/www/public -type d -exec chmod 755 {} \; 2>/dev/null || true
fi

task setup:prod

# Pass control to the command
exec "$@"