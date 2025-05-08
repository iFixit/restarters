#
# This is what gets run when we start the restarters Docker container.
#
# We install composer dependencies in here rather than during the build step so that if we switch branches
# and restart the container, it works.
rm -rf vendor
composer install

mkdir storage/framework/cache/data
php artisan migrate
npm install --legacy-peer-deps
npm rebuild node-sass
php artisan lang:js --no-lib resources/js/translations.js
chmod -R 777 public

php artisan key:generate
php artisan l5-swagger:generate
php artisan cache:clear
php artisan config:clear

# Ensure we have the admin user
php artisan user:create "Jane Bloggs" "jane@bloggs.net" "passw0rd" "en" "1" --role=2 --auto-consent

php artisan dev --no-logs

# In case everything else bombs out.
sleep infinity
