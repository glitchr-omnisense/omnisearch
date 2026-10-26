---
title: Laravel
order: 16
---

# Laravel

There is no Laravel bridge for omnisearch: omnibridge/laravel has none, and this package ships no
service provider. Nothing is missing for it to run, since the core needs no framework: a service
provider of the application builds the `Manager` once, as in plain PHP.

## A service provider

```php
namespace App\Providers;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\ServiceProvider;
use Omnisearch\Bridge\Doctrine\DoctrineObjectStore;
use Omnisearch\Mapping\MetadataRegistry;
use Omnisearch\ORM\Finder;
use Omnisearch\ORM\Manager;
use Omnisearch\Registry;
use Omnisearch\Typesense\TypesenseDriverFactory;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Manager::class, function ($app) {
            $entities = $app->make(EntityManagerInterface::class);

            return new Manager(
                new Registry([new TypesenseDriverFactory(null, $entities)], [
                    'default' => ['driver' => 'typesense', 'options' => [
                        'url' => config('services.typesense.url'),
                        'secret' => (string) config('services.typesense.key'),
                    ]],
                ]),
                MetadataRegistry::fromDirectories([app_path('Entity')]),
                new DoctrineObjectStore($entities),
            );
        });

        // One index's finder where a class asks for a Finder
        $this->app->when(\App\Http\Controllers\SearchController::class)
            ->needs(Finder::class)
            ->give(fn ($app) => $app->make(Manager::class)->finder(\App\Entity\Article::class));
    }
}
```

This is the shape lesringostars' Laravel twin uses (its `app/Omnibridge/ScreenServiceProvider.php`):
the Symfony site's Typesense, the indexes its entities declare, Doctrine's entity manager. It needs
an `EntityManagerInterface` bound in the container by the application's Doctrine integration; with the memory engine and an `ObjectStore` of the application's, no Doctrine
is needed at all.

## What a provider does not get

- **The metadata at boot.** `MetadataRegistry::fromDirectories()` scans the directory on the first
  use of the `Manager` in each process. To skip it, keep `ClassMetadata::toArray()` of each index
  in a cache file and build with `MetadataRegistry::fromArray()`.
- **Indexing on writes.** Nothing listens to Doctrine's events: register
  `Omnisearch\Bridge\Doctrine\IndexListener` on the entity manager's event manager
  (`IndexListener::EVENTS`), with an `Indexer` and an `$onError` ([Indexing](indexing.md)). An
  Eloquent model is not an entity: call `Manager::index()`, or an `Indexer` from the model's
  events, with an `ObjectStore` of the application's.
- **Commands.** `omnisearch:indexes` and `omnisearch:schema` are Symfony Console commands. An
  Artisan command of the application prints the same from `Manager::metadata()->all()` and
  `Manager::connection($index)->schema($metadata)`, and creates or drops the indexes with
  `Manager::create()` and `Manager::drop()`.
