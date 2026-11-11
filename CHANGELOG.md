# Changelog

## Unreleased (1.x)

### Changed
- omnisense/omnisearch requires omnisense/contracts, the kernel of the omni families:
  `OmnisearchException` implements its `ExceptionInterface` (one catch holds every family's
  failures); nothing else of the public API changes. The `Registry` of connections, the
  `DriverFactory` and the exceptions' classes stay omnisearch's own: a connection is not the
  kernel's provider, and the drivers have no usage limit to guard (omnisearch/typesense's policy
  declares none). The harness installs omnisense/contracts from its checkout
  (`OMNISENSE_CONTRACTS`) or GitHub, runs PHPStan (`phpstan`) and names its entrypoint.
- PHPStan max green again over the 2026-10-21 baseline, types only: `OmnisearchBundle` drops its
  `is_subclass_of()` checks on the drivers and hosts it lists (each implements `DriverFactory` /
  `Host`) and its `?? 'log'` on `on_index_error` (the configuration always sets it); its tests
  narrow what they read. Two baseline entries fixed and removed.

### Fixed
- Under the Symfony bundle, a write an engine refuses or cannot reach during Doctrine's flush no
  longer throws out of `EntityManager::flush()` after the database committed: the bundle gives
  its `Indexer` an `$onError`, by the new `on_index_error` option - `log` by default
  (`Bridge\Symfony\IndexErrorLogger`, the `logger` service at error level), `throw` for the former
  behaviour, or a service id.
- `DoctrineObjectStore::load()` loads back an entity with a composite identifier: each id is split
  back into its values at its `-` (every split a value holding `-` allows), where the joined
  string was compared with the first identifier field only and the entity never came back.
- On a `Manager` without an `ObjectStore`, the `Indexer` takes a removed object's identifier as
  the memory engine does (`Memory\Identity`: `getId()`, else a public `$id`), so the document is
  removed from its index, where nothing was queued.
- `OmnisearchException`'s docblock says what it covers: `Filter`'s `\InvalidArgumentException`
  for a filter that can never work is PHP's, a mistake in the code.

### Added
- The bundle's `on_index_error` option and `Bridge\Symfony\IndexErrorLogger`.
