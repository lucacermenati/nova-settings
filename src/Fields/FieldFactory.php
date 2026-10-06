<?php

namespace LucaCermenati\NovaSettings\Fields;

use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\BooleanGroup;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use LucaCermenati\NovaSettings\Models\Setting;

/**
 * Builds a Nova field from a setting row (field class + options JSON).
 *
 * Options contract (all keys optional):
 *
 *  help, placeholder, default, rules (string|array), nullable, required, readonly,
 *  stacked, fullWidth, textAlign,
 *  options (Select / MultiSelect / BooleanGroup), searchable (Select),
 *  min, max, step (Number / Currency), currency, locale (Currency),
 *  rows, maxlength, enforceMaxlength (Text / Textarea),
 *  language, json (Code),
 *  disk, path, acceptedTypes, prunable, deletable, maxWidth, squared, rounded (File / Image),
 *  trueValue, falseValue (Boolean),
 *  keyLabel, valueLabel, actionText (KeyValue)
 *
 * Settings created from the Nova UI only ever store `options` (the choices of a
 * Select / MultiSelect / BooleanGroup); the other keys are for rows created
 * programmatically or synced from code.
 */
class FieldFactory
{
    public function make(Setting $setting): Field
    {
        return $this->build(
            $setting->type,
            $setting->label,
            $setting->key,
            $setting->fieldOptions(),
            $setting->description,
            $setting->isLocked(),
        );
    }

    /**
     * @param  class-string<Field>  $type
     */
    public function build(string $type, string $label, string $key, array $options = [], ?string $description = null, bool $locked = false): Field
    {
        if (! is_subclass_of($type, Field::class) || (! $locked && ! $this->isAllowedType($type))) {
            throw UnsupportedFieldTypeException::forType($type);
        }

        $field = $type::make($label, $key);

        if ($description !== null && $description !== '') {
            $field->help($description);
        }

        $this->applyGeneric($field, $options);
        $this->applyTypeSpecific($field, $options);

        return $field;
    }

    /**
     * Whether a field class cannot work without a map of choices (value => label).
     *
     * @param  class-string<Field>|string  $type
     */
    public static function requiresChoices(string $type): bool
    {
        return is_a($type, Select::class, true)
            || is_a($type, MultiSelect::class, true)
            || is_a($type, BooleanGroup::class, true);
    }

    public function isAllowedType(string $type): bool
    {
        return in_array($type, $this->allowedTypes(), true);
    }

    /**
     * @return array<string, class-string<Field>> label => class
     */
    public function allowedTypes(): array
    {
        return (array) config('nova-settings.field_types', []);
    }

    protected function applyGeneric(Field $field, array $options): void
    {
        if (isset($options['help'])) {
            $field->help((string) $options['help']);
        }

        if (isset($options['placeholder'])) {
            $field->placeholder((string) $options['placeholder']);
        }

        if (isset($options['default'])) {
            $field->default($options['default']);
        }

        $rules = $this->normalizeRules($options['rules'] ?? []);

        if (! empty($options['required'])) {
            array_unshift($rules, 'required');
        }

        if ($rules !== []) {
            $field->rules(...array_values(array_unique($rules)));
        }

        if (! empty($options['nullable'])) {
            $field->nullable();
        }

        if (! empty($options['readonly'])) {
            $field->readonly();
        }

        if (! empty($options['stacked'])) {
            $field->stacked();
        }

        if (! empty($options['fullWidth'])) {
            $field->fullWidth();
        }

        if (isset($options['textAlign'])) {
            $field->textAlign((string) $options['textAlign']);
        }
    }

    /**
     * Not mutually exclusive: Nova's Number (and Currency) extends Text, so a
     * number field receives both the number and the text options.
     */
    protected function applyTypeSpecific(Field $field, array $options): void
    {
        if ($field instanceof Select || $field instanceof MultiSelect || $field instanceof BooleanGroup) {
            $this->applyChoiceOptions($field, $options);
        }

        if ($field instanceof Number) {
            $this->applyNumberOptions($field, $options);
        }

        if ($field instanceof Text || $field instanceof Textarea) {
            $this->applyTextOptions($field, $options);
        }

        if ($field instanceof Code) {
            $this->applyCodeOptions($field, $options);
        }

        if ($field instanceof File) {
            $this->applyFileOptions($field, $options);
        }

        if ($field instanceof Boolean) {
            $this->applyBooleanOptions($field, $options);
        }

        if ($field instanceof KeyValue) {
            $this->applyKeyValueOptions($field, $options);
        }
    }

    protected function applyChoiceOptions(Select|MultiSelect|BooleanGroup $field, array $options): void
    {
        if (isset($options['options']) && is_array($options['options'])) {
            $field->options($options['options']);
        }

        // Not every choice field supports these (BooleanGroup has neither,
        // MultiSelect is not searchable).
        if (! empty($options['displayUsingLabels']) && method_exists($field, 'displayUsingLabels')) {
            $field->displayUsingLabels();
        }

        if (! empty($options['searchable']) && method_exists($field, 'searchable')) {
            $field->searchable();
        }
    }

    protected function applyNumberOptions(Number $field, array $options): void
    {
        foreach (['min', 'max', 'step'] as $method) {
            if (isset($options[$method])) {
                $field->{$method}($options[$method]);
            }
        }

        if ($field instanceof Currency) {
            if (isset($options['currency'])) {
                $field->currency((string) $options['currency']);
            }

            if (isset($options['locale'])) {
                $field->locale((string) $options['locale']);
            }
        }
    }

    protected function applyTextOptions(Text|Textarea $field, array $options): void
    {
        if ($field instanceof Textarea && isset($options['rows'])) {
            $field->rows((int) $options['rows']);
        }

        if (isset($options['maxlength'])) {
            $field->maxlength((int) $options['maxlength'], ! empty($options['enforceMaxlength']));
        }
    }

    protected function applyCodeOptions(Code $field, array $options): void
    {
        if (isset($options['language'])) {
            $field->language((string) $options['language']);
        }

        if (! empty($options['json'])) {
            $field->json();
        }
    }

    protected function applyFileOptions(File $field, array $options): void
    {
        $field->disk($options['disk'] ?? config('nova-settings.files.disk', 'public'));
        $field->path($options['path'] ?? config('nova-settings.files.path', 'settings'));

        if (isset($options['acceptedTypes'])) {
            $field->acceptedTypes((string) $options['acceptedTypes']);
        }

        if ($options['prunable'] ?? true) {
            $field->prunable();
        }

        if (array_key_exists('deletable', $options)) {
            $field->deletable((bool) $options['deletable']);
        }

        if ($field instanceof Image) {
            if (isset($options['maxWidth'])) {
                $field->maxWidth((int) $options['maxWidth']);
            }

            if (! empty($options['squared'])) {
                $field->squared();
            }

            if (! empty($options['rounded'])) {
                $field->rounded();
            }
        }
    }

    protected function applyBooleanOptions(Boolean $field, array $options): void
    {
        if (array_key_exists('trueValue', $options)) {
            $field->trueValue($options['trueValue']);
        }

        if (array_key_exists('falseValue', $options)) {
            $field->falseValue($options['falseValue']);
        }
    }

    protected function applyKeyValueOptions(KeyValue $field, array $options): void
    {
        foreach (['keyLabel', 'valueLabel', 'actionText'] as $method) {
            if (isset($options[$method])) {
                $field->{$method}((string) $options[$method]);
            }
        }
    }

    protected function normalizeRules(mixed $rules): array
    {
        if (is_string($rules)) {
            return array_values(array_filter(explode('|', $rules)));
        }

        return is_array($rules) ? array_values(array_filter($rules, fn ($rule) => is_string($rule) && $rule !== '')) : [];
    }
}
