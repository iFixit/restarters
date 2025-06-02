#!/bin/bash
set -e

export IN_DOCKER=true

mkdir storage/framework/cache/data

IN_DOCKER=true task setup:dev

chmod -R 777 public

task app:user:create -- "Jane Bloggs" "jane@bloggs.net" "passw0rd" "en" "1" --role=2

php artisan dev --no-logs

# In case everything else bombs out.
sleep infinity
