#!/bin/sh
# Composes the harness (this core + every driver), installs it when it
# changed, then runs the command: "test" for PHPUnit, "bare" for the script
# that uses the packages with no bundle, no container and no console,
# "phpstan" for PHPStan at level max on each package (its
# baseline included), "composer ..." or "sh" as they are.
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
    phpstan)
        status=0
        for dir in /omnisearch/core /omnisearch/plugins/*; do
            [ -f "$dir/phpstan.neon" ] || continue
            [ "$dir" != /omnisearch/plugins/core ] || continue
            echo "== $dir"
            vendor/bin/phpstan analyse --no-progress --memory-limit=1G -c "$dir/phpstan.neon" --autoload-file=/harness/vendor/autoload.php "$dir" || status=1
        done
        exit $status ;;
    composer|sh|php) exec "$@" ;;
    *) echo "test, bare, phpstan, composer, sh or php" >&2; exit 2 ;;
esac
