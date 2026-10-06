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
        if (! class_exists($type) || ! is_subclass_of($type, Field::class)) {
            throw UnsupportedFieldTypeException::forType($type);
        }

        if (! $locked && ! $this->isAllowedType($type)) {
            throw UnsupportedFieldTypeException::forType($type);
        }

        /** @var Field $field */
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

    protected function applyGeneric(Field $field, array $o): void
    {
        if (isset($o['help'])) {
            $field->help((string) $o['help']);
        }

        if (isset($o['placeholder']) && method_exists($field, 'placeholder')) {
            $field->placeholder((string) $o['placeholder']);
        }

        if (array_key_exists('default', $o) && $o['default'] !== null) {
            $field->default($o['default']);
        }

        $rules = $this->normalizeRules($o['rules'] ?? []);

        if (! empty($o['required'])) {
            array_unshift($rules, 'required');
        }

        if ($rules !== []) {
            $field->rules(...array_values(array_unique($rules)));
        }

        if (! empty($o['nullable'])) {
            $field->nullable();
        }

        if (! empty($o['readonly'])) {
            $field->readonly();
        }

        if (! empty($o['stacked'])) {
            $field->stacked();
        }

        if (! empty($o['fullWidth'])) {
            $field->fullWidth();
        }

        if (isset($o['textAlign'])) {
            $field->textAlign((string) $o['textAlign']);
        }
    }

    protected function applyTypeSpecific(Field $field, array $o): void
    {
        if ($field instanceof Select || $field instanceof MultiSelect || $field instanceof BooleanGroup) {
            if (isset($o['options']) && is_array($o['options'])) {
                $field->options($o['options']);
            }

            if (! empty($o['displayUsingLabels']) && method_exists($field, 'displayUsingLabels')) {
                $field->displayUsingLabels();
            }

            if (! empty($o['searchable']) && method_exists($field, 'searchable')) {
                $field->searchable();
            }
        }

        if ($field instanceof Number || $field instanceof Currency) {
            foreach (['min', 'max', 'step'] as $method) {
                if (isset($o[$method])) {
                    $field->{$method}($o[$method]);
                }
            }
        }

        if ($field instanceof Currency) {
            if (isset($o['currency'])) {
                $field->currency((string) $o['currency']);
            }

            if (isset($o['locale'])) {
                $field->locale((string) $o['locale']);
            }
        }

        if ($field instanceof Textarea && isset($o['rows'])) {
            $field->rows((int) $o['rows']);
        }

        if ($field instanceof Text || $field instanceof Textarea) {
            if (isset($o['maxlength'])) {
                $field->maxlength((int) $o['maxlength'], ! empty($o['enforceMaxlength']));
            }
        }

        if ($field instanceof Code) {
            if (isset($o['language'])) {
                $field->language((string) $o['language']);
            }

            if (! empty($o['json'])) {
                $field->json();
            }
        }

        if ($field instanceof File) {
            $field->disk($o['disk'] ?? config('nova-settings.files.disk', 'public'));
            $field->path($o['path'] ?? config('nova-settings.files.path', 'settings'));

            if (isset($o['acceptedTypes'])) {
                $field->acceptedTypes((string) $o['acceptedTypes']);
            }

            if ($o['prunable'] ?? true) {
                $field->prunable();
            }

            if (array_key_exists('deletable', $o)) {
                $field->deletable((bool) $o['deletable']);
            }

            if ($field instanceof Image) {
                if (isset($o['maxWidth'])) {
                    $field->maxWidth((int) $o['maxWidth']);
                }

                if (! empty($o['squared'])) {
                    $field->squared();
                }

                if (! empty($o['rounded'])) {
                    $field->rounded();
                }
            }
        }

        if ($field instanceof Boolean) {
            if (array_key_exists('trueValue', $o)) {
                $field->trueValue($o['trueValue']);
            }

            if (array_key_exists('falseValue', $o)) {
                $field->falseValue($o['falseValue']);
            }
        }

        if ($field instanceof KeyValue) {
            foreach (['keyLabel', 'valueLabel', 'actionText'] as $method) {
                if (isset($o[$method])) {
                    $field->{$method}((string) $o[$method]);
                }
            }
        }
    }

    protected function normalizeRules(mixed $rules): array
    {
        if (is_string($rules)) {
            return array_values(array_filter(explode('|', $rules)));
        }

        return is_array($rules) ? array_values(array_filter($rules, fn ($r) => is_string($r) && $r !== '')) : [];
    }
}
