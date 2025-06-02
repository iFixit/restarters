#!/bin/bash
set -e

export IN_DOCKER=true

if [ ! -d "storage/framework/cache/data" ]; then
  mkdir -p storage/framework/cache/data
fi

IN_DOCKER=true task setup:dev

chmod -R 777 public

task app:user:create -- "Jane Bloggs" "jane@bloggs.net" "passw0rd" "en" "1" --role=2

php artisan dev --no-logs

# In case everything else bombs out.
sleep infinity
