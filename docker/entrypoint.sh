#!/bin/bash
set -e

# Source the utility functions
source "$(dirname "$0")/bash_utils.sh"

# Fix permissions on startup using Task
log_info "Fixing permissions using Task"
task fix-permissions || {
    log_error "Failed to fix permissions. Check the logs for details."
    exit 1
}

# Check if we need to run the initialization
if [ ! -f /var/www/storage/framework/initialized ] || [ "${FORCE_INIT}" = "true" ]; then
    log_info "Running startup tasks..."
    task startup || {
        log_error "Startup tasks failed. Check the logs for details."
        exit 1
    }
else
    log_info "Application already initialized. Skipping initialization."
fi

# Check if the command is php-fpm
if [ "$1" = "php-fpm" ]; then
    # Make sure our script is executable
    if [ -f /var/www/docker/run-services.sh ]; then
        chmod +x /var/www/docker/run-services.sh
        # Execute our custom script that runs both webpack and php-fpm
        log_info "Starting services with run-services.sh..."
        exec /var/www/docker/run-services.sh
    else
        log_warn "run-services.sh not found. Starting PHP-FPM directly..."
        exec php-fpm
    fi
else
    # Execute the original command
    log_info "Executing command: $@"
    exec "$@"
fi 