#!/bin/sh
set -eu
php artisan syncops:production-check --database --no-interaction
php artisan syncops:demo-reset --force --exclusive --no-interaction
php artisan syncops:production-check --ready --no-interaction
