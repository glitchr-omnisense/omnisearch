---
title: The Docker harness
order: 70
---

# The Docker harness

`docker/` runs this package with every `omnisearch/*` driver - from GitHub (branch 1.x), or from
the checkouts beside this one when `OMNISEARCH_PLUGINS=../..` is set in `docker/.env` - and a
Typesense server (30.0, data in memory).

```sh
cd docker && cp .env.dist .env       # the checkouts of the workspace
docker compose run --rm omnisearch test          # every package's tests
docker compose run --rm omnisearch bare          # plain PHP: memory, then Typesense, and what PHP loaded
docker compose run --rm omnisearch bare --memory
docker compose down -v
```

`bare` builds everything by hand: on the memory engine, notes indexed by the rule, searched,
faceted; then Doctrine entities on SQLite indexed on flush in Typesense through
omnisearch/typesense, searched (sorted, matched tokens, facets, infix, a filter), loaded back, the
collection dropped. `Tests/BareTest` runs it in a PHP process of its own and checks that nothing of
Symfony nor Doctrine is loaded on the memory engine, and no class of a framework through Typesense.

## The services

| Service | Image | |
|---|---|---|
| `omnisearch` | `omnisense/omnisearch-harness`, built from `docker/Dockerfile` (`php:8.4-cli-alpine`, the `zip` extension, Composer 2) | this checkout at `/omnisearch/core`, the drivers' checkouts at `/omnisearch/plugins` (read-only), the volume `harness` at `/harness` (the generated project, its `vendor/` and Composer's cache) |
| `typesense` | `typesense/typesense:30.0.rca41` | `--data-dir /data` on a tmpfs, the API key `TYPESENSE_KEY` (`xyz` by default); healthy once `/health` answers `"ok":true`, which `omnisearch` waits for |

The `omnisearch` service is given `TYPESENSE_URL=http://typesense:8108` and `TYPESENSE_KEY`.

## Environment

| Variable | Default | |
|---|---|---|
| `OMNISEARCH_PLUGINS` | `./plugins` (empty) | the directory holding the drivers' checkouts, one per slug (`typesense/`); `.env.dist` sets `../..`, the workspace |
| `TYPESENSE_KEY` | `xyz` | the Typesense server's API key |

## What the entrypoint does

`docker/entrypoint.sh` runs `docker/harness/setup.php`, which writes `/harness/composer.json` and
`/harness/phpunit.xml`:

- this core from its checkout (a path repository, `1.x-dev`);
- each driver of `docker/harness/plugins.php` from its checkout under `/omnisearch/plugins/<slug>`
  when its `composer.json` names `omnisearch/<slug>`, otherwise cloned from
  `https://github.com/glitchr-omnisense/omnisearch-<slug>.git` (branch 1.x);
- `doctrine/orm`, `ext-pdo_sqlite`, and for the tests `doctrine/doctrine-bundle`,
  `symfony/framework-bundle`, `symfony/cache`, `config`, `console`, `dependency-injection`,
  `http-kernel`, PHPUnit 11;
- one PHPUnit suite per package (`core`, `typesense`).

The files are rewritten only when they change. Composer installs when there is no `vendor/`, and
updates when `composer.json` is newer than `composer.lock`. Then the command:

| Command | |
|---|---|
| `test [phpunit arguments]` (the default) | `vendor/bin/phpunit` on every suite, e.g. `test --testsuite typesense` |
| `bare [--memory]` | `docker/harness/bin/bare` |
| `composer ...`, `sh`, `php ...` | as they are, in `/harness` |

## Adding a driver

A line in `docker/harness/plugins.php`: the slug, the tests' namespace and the factory class:

```php
return [
    'typesense' => ['Omnisearch\\Typesense\\Tests\\', 'Omnisearch\\Typesense\\TypesenseDriverFactory'],
];
```

`docker compose down -v` removes the Typesense container and the `harness` volume (the next run
installs again).
