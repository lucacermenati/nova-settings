<?php

namespace LucaCermenati\NovaSettings\Nova;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;
use LucaCermenati\NovaSettings\Enums\Source;
use LucaCermenati\NovaSettings\Models\SettingGroup;

/**
 * @extends resource<SettingGroup>
 */
class SettingGroupResource extends Resource
{
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

            Badge::make(__('Source'), 'source')
                ->resolveUsing(fn ($value) => $value instanceof Source ? $value->value : $value)
                ->map(['code' => 'info', 'database' => 'success'])
                ->labels(['code' => __('Defined in code'), 'database' => __('Created in Nova')]),

            HasMany::make(__('Settings'), 'settings', SettingResource::class),
        ];
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return ! $this->isLocked() && parent::authorizedToUpdate($request);
    }

    public function authorizedToDelete(Request $request): bool
    {
        return ! $this->isLocked() && parent::authorizedToDelete($request);
    }

    public function authorizedToReplicate(Request $request): bool
    {
        return false;
    }

    protected function isLocked(): bool
    {
        return $this->resource instanceof SettingGroup && $this->resource->exists && $this->resource->isManagedByCode();
    }
}
