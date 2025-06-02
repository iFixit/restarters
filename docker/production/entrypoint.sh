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

# Create admin user if enabled
if [ "${CREATE_ADMIN_USER:-false}" = "true" ]; then
  echo "Setting up admin user..."

  # Use artisan command to create the admin user
  task app:user:create -- "${ADMIN_NAME}" "${ADMIN_EMAIL}" "${ADMIN_PASSWORD}" "en" "1" --role=${ADMIN_ROLE}
    
  echo "Admin user setup completed"
fi

if [ "${SEED_SKILLS:-false}" = "true" ]; then
  echo "Seeding skills..."
  php artisan db:seed --class="DefaultSkills" --force
  echo "Skills seeded"
fi

task app:clear:caches

# Pass control to the command
exec "$@" 