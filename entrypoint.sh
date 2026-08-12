#!/bin/sh
set -e
php artisan storage:link || true
php artisan migrate --force
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
