<?php

namespace LucaCermenati\NovaSettings\Nova;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;
use LucaCermenati\NovaSettings\Models\SettingGroup;
use LucaCermenati\NovaSettings\Nova\Concerns\LocksCodeManagedRows;

/**
 * @extends resource<SettingGroup>
 */
class SettingGroupResource extends Resource
{
    use LocksCodeManagedRows;

    public static $model = SettingGroup::class;

    public static $title = 'label';

    public static $search = ['key', 'label'];

    public static $displayInNavigation = false;

    public static $globallySearchable = false;

    public static function label(): string
    {
        return __('Setting groups');
    }

    public static function singularLabel(): string
    {
        return __('Setting group');
    }

    public static function uriKey(): string
    {
        return 'setting-groups';
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function fields(NovaRequest $request): array
    {
        $locked = fn () => $this->isLocked();

        return [
            ID::make()->sortable(),

            Text::make(__('Key'), 'key')
                ->rules('required', 'alpha_dash', 'max:100')
                ->creationRules('unique:setting_groups,key')
                ->updateRules('unique:setting_groups,key,{{resourceId}}')
                ->readonly($locked)
                ->help(__('Used as the tab identifier. Letters, numbers, dashes and underscores only.')),

            Text::make(__('Label'), 'label')
                ->rules('required', 'max:255')
                ->readonly($locked),

            Text::make(__('Icon'), 'icon')
                ->nullable()
                ->hideFromIndex()
                ->readonly($locked)
                ->help(__('Optional Heroicon name, e.g. "cog-6-tooth".')),

            Number::make(__('Order'), 'sort_order')
                ->rules('nullable', 'integer', 'min:0')
                ->default(0)
                ->readonly($locked)
                ->sortable(),

            $this->sourceBadge(),

            HasMany::make(__('Settings'), 'settings', SettingResource::class),
        ];
    }
}
