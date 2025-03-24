#!/bin/bash
set -e

# Source the utility functions
source "$(dirname "$0")/bash_utils.sh"

# Function to handle graceful shutdown
cleanup() {
    log_info "Shutting down services..."
    
    # Kill npm watch process if running
    if [ -n "$NPM_PID" ]; then
        log_info "Terminating npm watch process..."
        kill -TERM "$NPM_PID" 2>/dev/null || true
    fi
    
    # Kill PHP-FPM if running
    if [ -n "$PHP_FPM_PID" ]; then
        log_info "Terminating PHP-FPM process..."
        kill -TERM "$PHP_FPM_PID" 2>/dev/null || true
    fi
    
    log_info "Shutdown complete"
    exit 0
}

# Set up signal handling
trap cleanup SIGTERM SIGINT

# Ensure our environment is properly set up
log_info "Setting up environment for webpack..."

# Generate webpack-specific .env from our environment files
if [ -f /var/www/config/generate-webpack-env.php ]; then
    log_info "Running generate-webpack-env.php..."
    php /var/www/config/generate-webpack-env.php
fi

# Start npm watch in the background if available
if [ -f /var/www/package.json ]; then
    if grep -q '"watch"' /var/www/package.json; then
        log_info "Starting npm watch process..."
        cd /var/www && npm run watch &
        NPM_PID=$!
        log_info "npm watch started with PID: $NPM_PID"
    elif grep -q '"dev"' /var/www/package.json; then
        log_info "Starting npm dev process..."
        cd /var/www && npm run dev &
        NPM_PID=$!
        log_info "npm dev started with PID: $NPM_PID"
    else
        log_warn "No watch or dev script found in package.json"
    fi
else
    log_warn "No package.json found. Skipping npm watch."
fi

# Start PHP-FPM
log_info "Starting PHP-FPM..."
php-fpm &
PHP_FPM_PID=$!
log_info "PHP-FPM started with PID: $PHP_FPM_PID"

# Wait for any process to exit
wait -n

# If we get here, one of the processes died
log_error "A process has unexpectedly terminated"

# Perform cleanup
cleanup 