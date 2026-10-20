<?php

namespace Omnisearch\DBAL;

/**
 * A condition on a field's value, the same for every engine; the driver
 * writes it in its own syntax. On a list field, "=" means "holds".
 */
final class Filter
{
    public const EQUALS = '=';
    public const NOT_EQUALS = '!=';
    public const IN = 'in';
    public const NOT_IN = 'not in';
    public const LOWER = '<';
    public const LOWER_OR_EQUAL = '<=';
    public const GREATER = '>';
    public const GREATER_OR_EQUAL = '>=';

    public function __construct(
        public readonly string $field,
        public readonly string $operator,
        public readonly mixed $value,
    ) {
        if (!\in_array($operator, [self::EQUALS, self::NOT_EQUALS, self::IN, self::NOT_IN, self::LOWER, self::LOWER_OR_EQUAL, self::GREATER, self::GREATER_OR_EQUAL], true)) {
            throw new \InvalidArgumentException(\sprintf('Unknown operator "%s".', $operator));
        }
        if (\in_array($operator, [self::IN, self::NOT_IN], true) && !\is_array($value)) {
            throw new \InvalidArgumentException(\sprintf('"%s" takes a list of values.', $operator));
        }
    }

    public static function equals(string $field, string|int|float|bool $value): self
    {
        return new self($field, self::EQUALS, $value);
    }

    public static function notEquals(string $field, string|int|float|bool $value): self
    {
        return new self($field, self::NOT_EQUALS, $value);
    }

    /** @param list<string|int|float|bool> $values */
    public static function in(string $field, array $values): self
    {
        return new self($field, self::IN, array_values($values));
    }

    /** @param list<string|int|float|bool> $values */
    public static function notIn(string $field, array $values): self
    {
        return new self($field, self::NOT_IN, array_values($values));
    }

    public function matches(mixed $actual): bool
    {
        $values = \is_array($actual) ? $actual : [$actual];
        $compare = fn ($a, $b) => \is_string($a) || \is_string($b) ? strcmp((string) $a, (string) $b) : $a <=> $b;

        return match ($this->operator) {
            self::EQUALS => \in_array($this->value, $values, false),
            self::NOT_EQUALS => !\in_array($this->value, $values, false),
            self::IN => [] !== array_intersect(array_map('strval', $values), array_map('strval', $this->value)),
            self::NOT_IN => [] === array_intersect(array_map('strval', $values), array_map('strval', $this->value)),
            self::LOWER => (bool) array_filter($values, fn ($v) => $compare($v, $this->value) < 0),
            self::LOWER_OR_EQUAL => (bool) array_filter($values, fn ($v) => $compare($v, $this->value) <= 0),
            self::GREATER => (bool) array_filter($values, fn ($v) => $compare($v, $this->value) > 0),
            self::GREATER_OR_EQUAL => (bool) array_filter($values, fn ($v) => $compare($v, $this->value) >= 0),
        };
    }
}
