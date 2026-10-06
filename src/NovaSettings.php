<?php

namespace LucaCermenati\NovaSettings;

use Illuminate\Http\Request;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;
use LucaCermenati\NovaSettings\Nova\SettingGroupResource;
use LucaCermenati\NovaSettings\Nova\SettingResource;

class NovaSettings extends Tool
{
    public function boot(): void
    {
        Nova::mix('nova-settings', __DIR__.'/../dist/mix-manifest.json');

        app(SettingsManager::class)->autoSync();
    }

    public function menu(Request $request): MenuSection
    {
        $items = [
            MenuItem::link(__('Values'), '/'.trim(config('nova-settings.tool.path', 'nova-settings'), '/')),
        ];

        if (config('nova-settings.manage_definitions', true)) {
            foreach ([SettingGroupResource::class, SettingResource::class] as $resource) {
                // Not MenuItem::resource(): that requires $displayInNavigation,
                // which would also list the resources under "Resources".
                $items[] = MenuItem::make($resource::label())
                    ->path('/resources/'.$resource::uriKey())
                    ->canSee(fn ($request) => $resource::authorizedToViewAny($request));
            }
        }

        return MenuSection::make(config('nova-settings.tool.label', 'Settings'), $items)
            ->icon(config('nova-settings.tool.icon', 'cog-6-tooth'))
            ->collapsable();
    }
}
