#!/bin/sh
set -eu
php artisan config:clear --no-interaction
php artisan syncops:production-check --web --ready --no-interaction
php artisan config:cache --no-interaction
exec apache2-foreground
