---
title: Mapping
order: 30
---

# Mapping: indexes and fields

A class says it is indexed with `#[Index]`, and what of it with `#[Field]`:

```php
use Omnisearch\Mapping\{Field, Index, Type};

#[Index('blogArticle', connection: 'default', options: ['typesense' => ['default_sorting_field' => 'publishedAt']])]
#[Field('state', property: 'state', facet: true)]
#[Field('title', type: Type::STRINGS, property: 'translations.title', infix: true)]
#[Field('tags', type: Type::STRINGS, property: 'tags.slug', facet: true)]
#[Field('audio', type: Type::BOOL, property: 'isAudible', facet: true)]
class Article
{
    #[Field(type: Type::DATETIME, sort: true)]     // on a property: its name, its path
    private ?\DateTimeInterface $publishedAt;

    #[Field('readingTime', type: Type::INT)]        // on a method: its name without get/is/has
    public function getReadingTime(): int { ... }
}
```

| `#[Field]` | |
|---|---|
| `name` | the field in the document (required on the class) |
| `type` | `Type::STRING`, `STRINGS` (a list), `INT`, `INTS`, `BIGINT`, `FLOAT`, `FLOATS`, `BOOL`, `BOOLS`, `DATETIME` (a timestamp), `GEOPOINT` |
| `property` | the path its value is read from: `translations.title` goes through a list (every item), `tags` gives each tag's `__toString()`, `isAudible` calls that method; the name when none |
| `facet` | its values counted, and filtered on |
| `sort` | results sortable by it |
| `infix` | matched by the middle of a word, where the engine can |
| `optional` | a document may lack it |
| `locale` | the language its text is tokenized in |
| `options` | the engine's own settings, under its name (`['typesense' => ['stem' => true]]`) |

Every argument, its type and default: [Concepts](concepts.md#field).

## Names and paths

- On the class, `name` is required and `property` defaults to the name.
- On a property, both default to the property's name (`publishedAt`). The value is read from
  outside the object: a private property needs a public getter (`getPublishedAt()`,
  `isPublished()`, `hasTags()`), and without one it reads null.
- On a method, the name is the method's without a leading `get`, `is` or `has` followed by a
  capital (`getReadingTime` gives `readingTime`, `isAudible` gives `audible`), and the path is the
  method's own name, so the method itself is called.
- The fields keep their declared order: the class's attributes first, then the properties', then
  the methods' - the order `omnisearch:indexes` and `schema()` show them in.
- A name declared twice on a class, a field on the class without a name, a type not in `Type::ALL`
  are `MappingException`s when the class is loaded.

On the memory engine (and any driver that uses `ORM\Transformer`), a path is read by
`PropertyReader` ([Concepts](concepts.md#propertyreader)): a getter, a method of that name, a
public property, an `ArrayAccess` key or `__get()`; through a list on each item; what cannot be read
is null. A list field gets a list whatever the path gives (a single value wrapped); a `DATETIME`
gets a timestamp. omnisearch/typesense hands the same path to glitchr/typesense-bundle's
transformer, which reads it its own way.

## The identifier

The document's identifier is not a field: it is the object store's `identify()` - Doctrine's
identifier with `DoctrineObjectStore`, `getId()` or `$id` on the memory engine without a store. An
engine's package may add what its engine needs: omnisearch/typesense lets glitchr/typesense-bundle
add its `id` and `primary` fields from Doctrine's identifier, and a discriminator field and one
sub-collection per discriminator value for an entity with inheritance.

## Sub-classes

An object of a sub-class is in its nearest declared parent's index: `MetadataRegistry::find()`
walks `get_parent_class()` until a class with an `#[Index]`. Attributes are not inherited: a
sub-class declaring its own `#[Index]` has an index of its own, with only the fields it declares.

## Index options

`#[Index]`'s `options` are the engine's settings of the index; a driver reads its own, under its
engine's name, and ignores the rest. omnisearch/typesense reads `default_sorting_field`,
`token_separators` and `symbols_to_index` (from `options['typesense']`, or the options themselves
when there is no `typesense` key). The memory engine reads none.

## Reading the metadata

The attributes are classes of this package and nothing else: an entity carrying them loads
without omnisearch installed (PHP reads an attribute's class only when asked to), and without a
framework.

```php
use Omnisearch\Mapping\{AttributeLoader, ClassMetadata, MetadataRegistry};

$metadata = AttributeLoader::load(Article::class);              // ?ClassMetadata
$all = AttributeLoader::scan([__DIR__.'/src/Entity']);          // by index name
$registry = MetadataRegistry::fromDirectories([__DIR__.'/src/Entity']);

// Kept in a cache file, read back without reflection
file_put_contents('var/search.php', '<?php return '.var_export(array_map(fn (ClassMetadata $m) => $m->toArray(), array_values($all)), true).';');
$registry = MetadataRegistry::fromArray(require 'var/search.php');
```

`scan()` loads only the files whose text names `Omnisearch\Mapping`: the rest of an application's
code is never autoloaded. The Symfony bundle does this at compile time and keeps the arrays in the
container ([Symfony](symfony.md#the-metadata-at-compile-time)).
