#!/bin/sh
set -eu
php artisan config:clear --no-interaction
php artisan syncops:production-check --worker --ready --no-interaction
php artisan config:cache --no-interaction
exec php artisan queue:work database --queue=webhooks --sleep=1 --tries=3 --timeout=30 --max-time=3600 --no-interaction
