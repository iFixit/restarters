#!/bin/bash
set -e

# Wait for database connection (if enabled)
wait_for_db() {
  DB_HOST=${DB_HOST:-${MYSQL_HOST:-localhost}}
  DB_PORT=${DB_PORT:-${MYSQL_PORT:-3306}}
  DB_DATABASE=${DB_DATABASE:-${MYSQL_DATABASE:-restarters}}
  DB_USERNAME=${DB_USERNAME:-${MYSQL_USER:-root}}
  DB_PASSWORD=${DB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-""}}}
  
  echo "Waiting for database connection..."
  max_tries=30
  try=0
  
  until [ $try -ge $max_tries ] || php -r "try { new PDO('mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_DATABASE', '$DB_USERNAME', '$DB_PASSWORD'); echo 'DB Connection successful!'; } catch(PDOException \$e) { exit(1); }"; do
    try=$((try+1))
    echo "Database not ready yet (attempt $try/$max_tries)..."
    sleep 2
  done
  
  if [ $try -ge $max_tries ]; then
    echo "Could not connect to database after $max_tries attempts. Continuing anyway..."
  fi
}

# Initialize Laravel application
init_laravel() {
  cd /var/www
  
  # Create .env from environment if not mounted
  if [ ! -s /var/www/.env ]; then
    echo "Creating .env file from environment variables..."
    env | grep -E '^(APP_|DB_|CACHE_|QUEUE_|MAIL_|AWS_|REDIS_|LOG_|BROADCAST_|PUSHER_)' > .env
  fi
  
  # Generate app key if needed
  if grep -q "^APP_KEY=$" .env 2>/dev/null || ! grep -q "^APP_KEY=" .env 2>/dev/null; then
    echo "Generating application key..."
    php artisan key:generate --force
  fi
  
  # Run migrations if enabled
  if [ "${MIGRATE:-false}" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
  fi
  
  # Optimize Laravel if enabled
  if [ "${OPTIMIZE:-true}" = "true" ]; then
    echo "Optimizing Laravel..."
    php artisan optimize
  fi
  
  # Ensure storage directory is writable
  chmod -R 777 /var/www/storage /var/www/bootstrap/cache
}

# Main execution flow
if [ "${WAIT_FOR_DB:-false}" = "true" ]; then
  wait_for_db
fi

if [ "${INIT_LARAVEL:-true}" = "true" ]; then
  init_laravel
fi

# Pass control to the command
exec "$@" 