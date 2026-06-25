#!/bin/bash
set -e

echo "Prod deployment started ..."

php artisan down

git submodule update --init --recursive

composer install --no-interaction --prefer-dist --optimize-autoloader

php artisan optimize

php artisan migrate --force

source "$(dirname "$0")/horizon_terminate_wait.sh"

php artisan up

echo "Prod deployment finished!"
