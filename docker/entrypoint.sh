#!/bin/sh
# Composes the harness (this core + every driver), installs it when it
# changed, then runs the command: "test" for PHPUnit, "bare" for the script
# that uses the packages with no bundle, no container and no console,
# "composer ..." or "sh" as they are.
set -e
php /omnisearch/core/docker/harness/setup.php
cd /harness
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --no-progress
elif [ composer.json -nt composer.lock ]; then
    composer update --no-interaction --no-progress
fi
case "${1:-test}" in
    test) shift || true; exec vendor/bin/phpunit "$@" ;;
    bare) shift; exec php /omnisearch/core/docker/harness/bin/bare "$@" ;;
    composer|sh|php) exec "$@" ;;
    *) echo "test, bare, composer, sh or php" >&2; exit 2 ;;
esac
