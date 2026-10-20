<?php

namespace Omnisearch\Mapping;

use Omnisearch\Exception\MappingException;

/**
 * Reads #[Index] and #[Field] into ClassMetadata - with reflection only,
 * no framework, no engine.
 */
final class AttributeLoader
{
    /**
     * @param class-string $class
     *
     * @return ?ClassMetadata null when the class declares no Index
     */
    public static function load(string $class): ?ClassMetadata
    {
        $reflection = new \ReflectionClass($class);
        $index = ($reflection->getAttributes(Index::class)[0] ?? null)?->newInstance();
        if (!$index instanceof Index) {
            return null;
        }

        $fields = [];
        $add = function (Field $field, ?string $name, ?string $path) use (&$fields, $class): void {
            $name = $field->name ?? $name ?? throw new MappingException(\sprintf('A field declared on the class %s needs a name.', $class));
            if (isset($fields[$name])) {
                throw new MappingException(\sprintf('The field "%s" is declared twice on %s.', $name, $class));
            }
            if (!\in_array($field->type, Type::ALL, true)) {
                throw new MappingException(\sprintf('The field "%s" of %s has the type "%s"; one of %s.', $name, $class, $field->type, implode(', ', Type::ALL)));
            }
            $fields[$name] = new FieldMetadata($name, $field->type, $field->property ?? $path, $field->facet, $field->sort, $field->infix, $field->optional, $field->locale, $field->options);
        };

        foreach ($reflection->getAttributes(Field::class) as $attribute) {
            $add($attribute->newInstance(), null, null);
        }
        foreach ($reflection->getProperties() as $property) {
            foreach ($property->getAttributes(Field::class) as $attribute) {
                $add($attribute->newInstance(), $property->getName(), $property->getName());
            }
        }
        foreach ($reflection->getMethods() as $method) {
            foreach ($method->getAttributes(Field::class) as $attribute) {
                $add($attribute->newInstance(), lcfirst((string) preg_replace('/^(get|is|has)(?=[A-Z])/', '', $method->getName())), $method->getName());
            }
        }

        return new ClassMetadata($class, $index->name, $index->connection, $fields, $index->options);
    }

    /**
     * The metadata of every class declared under these directories with an
     * Index. A file is read for its tokens, and only loaded when it names
     * this namespace: the rest of an application's code is never autoloaded.
     *
     * @param list<string> $directories
     *
     * @return array<string, ClassMetadata> by index name, sorted
     */
    public static function scan(array $directories): array
    {
        $metadata = [];
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ('php' !== $file->getExtension()) {
                    continue;
                }
                $source = (string) file_get_contents($file->getPathname());
                if (!str_contains($source, 'Omnisearch\\Mapping')) {
                    continue;
                }
                foreach (self::declared($source) as $class) {
                    if (!class_exists($class) || null === ($classMetadata = self::load($class))) {
                        continue;
                    }
                    if (isset($metadata[$classMetadata->index]) && $metadata[$classMetadata->index]->class !== $class) {
                        throw new MappingException(\sprintf('The index "%s" is declared twice (%s and %s).', $classMetadata->index, $metadata[$classMetadata->index]->class, $class));
                    }
                    $metadata[$classMetadata->index] = $classMetadata;
                }
            }
        }
        ksort($metadata);

        return $metadata;
    }

    /** @return list<string> the classes a PHP source declares */
    private static function declared(string $source): array
    {
        $tokens = \PhpToken::tokenize($source);
        $namespace = '';
        $classes = [];
        foreach ($tokens as $i => $token) {
            if ($token->is(T_NAMESPACE)) {
                $namespace = '';
                for ($j = $i + 1; isset($tokens[$j]) && !$tokens[$j]->is([';', '{']); ++$j) {
                    if ($tokens[$j]->is([T_NAME_QUALIFIED, T_STRING])) {
                        $namespace .= $tokens[$j]->text;
                    }
                }
            } elseif ($token->is(T_CLASS)) {
                for ($k = $i - 1; $k >= 0 && $tokens[$k]->is(T_WHITESPACE); --$k);
                if ($k >= 0 && $tokens[$k]->is([T_DOUBLE_COLON, T_NEW])) {
                    continue;
                }
                for ($j = $i + 1; isset($tokens[$j]) && $tokens[$j]->is(T_WHITESPACE); ++$j);
                if (isset($tokens[$j]) && $tokens[$j]->is(T_STRING)) {
                    $classes[] = ltrim($namespace.'\\'.$tokens[$j]->text, '\\');
                }
            }
        }

        return $classes;
    }
}
