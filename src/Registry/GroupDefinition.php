<?php

namespace LucaCermenati\NovaSettings\Registry;

use Closure;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\FieldCollection;

/**
 * A group of settings (one tab) defined in code.
 */
class GroupDefinition
{
    public ?string $icon = null;

    public int $order = 0;

    protected Closure $fields;

    public function __construct(
        public readonly string $key,
        public string $label,
        Closure|array $fields,
    ) {
        $this->fields = $fields instanceof Closure ? $fields : fn () => $fields;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function order(int $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Build fresh field instances. Called on every request on purpose: Nova
     * fields are stateful (they get resolved against a model), so they must
     * never be shared between requests.
     *
     * @return FieldCollection<int, Field>
     */
    public function fields(): FieldCollection
    {
        $fields = collect(($this->fields)())->flatten()->filter(fn ($field) => $field instanceof Field);

        return FieldCollection::make($fields->values()->all());
    }

    /**
     * A stable signature of this definition, used to detect changes.
     */
    public function signature(): string
    {
        return implode('|', [
            $this->key,
            $this->label,
            (string) $this->icon,
            $this->order,
            $this->fields()->map(fn (Field $field) => implode(':', [
                $field->attribute,
                (string) $field->name,
                $field::class,
                (string) ($field->helpText ?? ''),
            ]))->implode(','),
        ]);
    }
}
