<?php

namespace Core\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Sorgu sonuçlarını saran dizi benzeri nesne.
 * foreach, count(), $koleksiyon[0] ve json_encode ile doğrudan kullanılabilir.
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    protected array $items;

    public function __construct(iterable $items = [])
    {
        $this->items = $items instanceof Traversable ? iterator_to_array($items) : (array) $items;
    }

    public static function make(iterable $items = []): static
    {
        return new static($items);
    }

    public function all(): array
    {
        return $this->items;
    }

    public function toArray(): array
    {
        return array_map(function ($item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                return $item->toArray();
            }
            if (is_object($item)) {
                return (array) $item;
            }
            return $item;
        }, $this->items);
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    public function first(?callable $callback = null, $default = null)
    {
        foreach ($this->items as $key => $item) {
            if ($callback === null || $callback($item, $key)) {
                return $item;
            }
        }

        return $default;
    }

    public function last($default = null)
    {
        return $this->items === [] ? $default : end($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function map(callable $callback): static
    {
        $keys = array_keys($this->items);

        return new static(array_combine($keys, array_map($callback, $this->items, $keys)));
    }

    public function filter(?callable $callback = null): static
    {
        return new static($callback === null
            ? array_filter($this->items)
            : array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    public function values(): static
    {
        return new static(array_values($this->items));
    }

    public function keys(): static
    {
        return new static(array_keys($this->items));
    }

    public function pluck(string $value, ?string $key = null): static
    {
        $result = [];

        foreach ($this->items as $item) {
            $v = static::dataGet($item, $value);

            if ($key === null) {
                $result[] = $v;
            } else {
                $result[static::dataGet($item, $key)] = $v;
            }
        }

        return new static($result);
    }

    public function contains($value): bool
    {
        if (is_callable($value) && !is_string($value)) {
            foreach ($this->items as $key => $item) {
                if ($value($item, $key)) {
                    return true;
                }
            }
            return false;
        }

        return in_array($value, $this->items, true);
    }

    public function sortBy($by, bool $descending = false): static
    {
        $items = $this->items;
        $resolve = is_callable($by) ? $by : fn($item) => static::dataGet($item, $by);

        uasort($items, function ($a, $b) use ($resolve, $descending) {
            $result = $resolve($a) <=> $resolve($b);
            return $descending ? -$result : $result;
        });

        return new static($items);
    }

    public function implode(string $glue, ?string $key = null): string
    {
        $items = $key === null ? $this->items : $this->pluck($key)->all();

        return implode($glue, $items);
    }

    public function push($item): static
    {
        $this->items[] = $item;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return array_map(function ($item) {
            if ($item instanceof JsonSerializable) {
                return $item->jsonSerialize();
            }
            return $item;
        }, $this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /** Dizi, stdClass ya da Model üzerinden değer okur. */
    protected static function dataGet($item, string $key)
    {
        if (is_array($item)) {
            return $item[$key] ?? null;
        }

        if (is_object($item)) {
            return $item->{$key} ?? null;
        }

        return null;
    }
}
