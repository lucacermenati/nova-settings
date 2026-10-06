<?php

namespace LucaCermenati\NovaSettings\Registry;

use Closure;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Nova\Fields\Field;

/**
 * Holds the settings defined in code (closures only — never built fields).
 */
class Registry
{
    /** @var array<string, GroupDefinition> */
    protected array $groups = [];

    public function group(string $key, string $label, Closure|array $fields): GroupDefinition
    {
        if (! preg_match('/^[a-z0-9_\-]+$/i', $key)) {
            throw new InvalidArgumentException("Setting group key [{$key}] may only contain letters, numbers, dashes and underscores.");
        }

        $definition = new GroupDefinition($key, $label, $fields);
        $definition->order(count($this->groups) + 1);

        return $this->groups[$key] = $definition;
    }

    /**
     * @return Collection<int, GroupDefinition>
     */
    public function groups(): Collection
    {
        return collect($this->groups)->values()->sortBy([['order', 'asc']])->values();
    }

    public function get(string $key): ?GroupDefinition
    {
        return $this->groups[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->groups[$key]);
    }

    public function isEmpty(): bool
    {
        return $this->groups === [];
    }

    public function flush(): void
    {
        $this->groups = [];
    }

    /**
     * Build a fresh instance of every code-defined field, keyed by setting key.
     *
     * @return Collection<string, Field>
     */
    public function fields(): Collection
    {
        $fields = collect();

        foreach ($this->groups() as $group) {
            foreach ($group->fields() as $field) {
                if ($fields->has($field->attribute)) {
                    throw new InvalidArgumentException("Setting key [{$field->attribute}] is defined more than once.");
                }

                $fields->put($field->attribute, $field);
            }
        }

        return $fields;
    }

    /**
     * Build a fresh instance of a single code-defined field.
     */
    public function field(string $key): ?Field
    {
        foreach ($this->groups() as $group) {
            foreach ($group->fields() as $field) {
                if ($field->attribute === $key) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * Fingerprint of all definitions, used to skip needless syncs.
     */
    public function fingerprint(): string
    {
        return sha1($this->groups()->map->signature()->implode(';'));
    }
}
