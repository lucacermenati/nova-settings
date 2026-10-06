<?php

namespace LucaCermenati\NovaSettings\Fields;

use RuntimeException;

class UnsupportedFieldTypeException extends RuntimeException
{
    public static function forType(string $type): static
    {
        return new static("[{$type}] is not an allowed Nova field class for settings. Add it to the nova-settings.field_types config.");
    }
}
