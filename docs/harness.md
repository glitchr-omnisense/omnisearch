---
title: The Docker harness
order: 6
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
