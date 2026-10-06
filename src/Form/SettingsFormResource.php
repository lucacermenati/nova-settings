<?php

namespace LucaCermenati\NovaSettings\Form;

use Closure;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

/**
 * An internal, unregistered Nova resource. It only exists so the package can
 * reuse Nova's own field/panel resolution (tabs, validation rules, fill,
 * serialization) for the settings form.
 */
class SettingsFormResource extends Resource
{
    public static $model = SettingsBag::class;

    public static $title = 'id';

    public static $displayInNavigation = false;

    public static $globallySearchable = false;

    protected Closure $fieldsResolver;

    public function __construct($resource = null, ?Closure $fieldsResolver = null)
    {
        parent::__construct($resource);

        $this->fieldsResolver = $fieldsResolver ?? fn () => [];
    }

    public function fields(NovaRequest $request): array
    {
        return ($this->fieldsResolver)($request);
    }

    public static function label(): string
    {
        return (string) config('nova-settings.tool.label', 'Settings');
    }

    public static function singularLabel(): string
    {
        return static::label();
    }

    public static function uriKey(): string
    {
        return 'nova-settings-form';
    }

    public function title(): string
    {
        return '';
    }
}
