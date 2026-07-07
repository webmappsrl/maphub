#!/bin/bash
set -e

export GIT_CONFIG_COUNT=2
export GIT_CONFIG_KEY_0=safe.directory
export GIT_CONFIG_VALUE_0=/var/www/html/maphub
export GIT_CONFIG_KEY_1=safe.directory
export GIT_CONFIG_VALUE_1=/var/www/html/maphub/wm-package

echo "Prod deployment started ..."

php artisan down

composer install --no-interaction --prefer-dist --optimize-autoloader

php artisan optimize

# Le migration wm-package arrivano gia' committate via git.
# Gate CI: publish-missing-migrations --dry-run nel job tests (run-tests.yml).
# Questo script non genera mai file.
php artisan migrate --force

php artisan permission:cache-reset

source "$(dirname "$0")/horizon_terminate_wait.sh"

php artisan up

echo "Prod deployment finished!"
