#!/bin/bash

php artisan health:check || exit 1

echo "Health check passed"
exit 0 