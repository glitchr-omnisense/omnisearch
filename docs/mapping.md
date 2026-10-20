---
title: Mapping
order: 2
---

# Mapping: indexes and fields

A class says it is indexed with `#[Index]`, and what of it with `#[Field]`:

```php
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

`#[Index]`'s `options` are the engine's settings of the index; a driver reads its own. The
identifier is the object store's (Doctrine's identifier); an engine's package may add what its
engine needs (omnisearch/typesense: glitchr/typesense-bundle's `id`, `primary`, the discriminator,
one sub-collection per discriminator value).

An object of a sub-class is in its nearest declared parent's index.

The attributes are classes of this package and nothing else: an entity carrying them loads
without omnisearch installed (PHP reads an attribute's class only when asked to), and without a
framework.

`AttributeLoader::load($class)`, `AttributeLoader::scan($directories)` (only the files naming
`Omnisearch\Mapping` are loaded) and `MetadataRegistry` read them; `ClassMetadata::toArray()` /
`fromArray()` keep them in a compiled container or a cache.
