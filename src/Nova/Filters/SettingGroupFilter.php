<?php

namespace LucaCermenati\NovaSettings\Nova\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use LucaCermenati\NovaSettings\Models\SettingGroup;

class SettingGroupFilter extends Filter
{
    public $component = 'select-filter';

    public function name(): string
    {
        return __('Group');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('setting_group_id', $value);
    }

    public function options(NovaRequest $request): array
    {
        return SettingGroup::query()->ordered()->pluck('id', 'label')->all();
    }
}
