<?php

namespace LucaCermenati\NovaSettings\Casting;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\BooleanGroup;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\Number;
use Throwable;

/**
 * Turns the raw stored value of a setting into the PHP type you would expect
 * for its Nova field class.
 */
class ValueCaster
{
    public static function cast(string $type, mixed $raw, array $options = []): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match (true) {
            is_a($type, Boolean::class, true) => static::toBool($raw),
            is_a($type, Currency::class, true) => is_numeric($raw) ? (float) $raw : $raw,
            is_a($type, Number::class, true) => static::toNumber($raw),
            is_a($type, MultiSelect::class, true),
            is_a($type, BooleanGroup::class, true),
            is_a($type, KeyValue::class, true) => static::toArray($raw),
            is_a($type, Code::class, true) => ($options['json'] ?? false) ? static::toArray($raw) : $raw,
            is_a($type, DateTime::class, true),
            is_a($type, Date::class, true) => static::toDate($raw),
            default => $raw,
        };
    }

    /**
     * A short, printable representation (used on the resource index / detail).
     */
    public static function display(string $type, mixed $raw, array $options = []): ?string
    {
        $value = static::cast($type, $raw, $options);

        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            default => (string) $value,
        };
    }

    protected static function toBool(mixed $raw): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $raw;
    }

    protected static function toNumber(mixed $raw): mixed
    {
        if (! is_string($raw) || ! is_numeric($raw)) {
            return $raw;
        }

        return str_contains($raw, '.') || str_contains(strtolower($raw), 'e')
            ? (float) $raw
            : (int) $raw;
    }

    protected static function toArray(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return (array) $raw;
    }

    protected static function toDate(mixed $raw): mixed
    {
        if ($raw instanceof DateTimeInterface) {
            return $raw;
        }

        try {
            return Carbon::parse($raw);
        } catch (Throwable) {
            return $raw;
        }
    }
}
