#!/bin/bash
set -e

echo "Dev deployment started ..."

# Enter maintenance mode or return true if already in maintenance mode
(php artisan down) || true

git submodule update --init --recursive

composer install --no-interaction --prefer-dist --optimize-autoloader

php artisan optimize

php artisan migrate --force

source "$(dirname "$0")/horizon_terminate_wait.sh"

php artisan up

echo "Dev deployment finished!"
