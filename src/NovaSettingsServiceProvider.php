<?php

namespace LucaCermenati\NovaSettings;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Http\Middleware\Authenticate;
use Laravel\Nova\Nova;
use LucaCermenati\NovaSettings\Console\SyncCommand;
use LucaCermenati\NovaSettings\Fields\FieldFactory;
use LucaCermenati\NovaSettings\Http\Middleware\Authorize;
use LucaCermenati\NovaSettings\Nova\SettingGroupResource;
use LucaCermenati\NovaSettings\Nova\SettingResource;
use LucaCermenati\NovaSettings\Registry\Registry;

class NovaSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nova-settings.php', 'nova-settings');

        $this->app->singleton(Registry::class);
        $this->app->singleton(FieldFactory::class);
        $this->app->singleton(SettingsManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/nova-settings.php' => config_path('nova-settings.php'),
        ], 'nova-settings-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'nova-settings-migrations');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([SyncCommand::class]);
        }

        $this->app->booted(fn () => $this->routes());

        if (config('nova-settings.manage_definitions', true)) {
            Nova::resources([SettingGroupResource::class, SettingResource::class]);
        }
    }

    protected function routes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $path = trim(config('nova-settings.tool.path', 'nova-settings'), '/');
        $middleware = ['nova', Authenticate::class, Authorize::class];

        Nova::router($middleware, $path)
            ->group(__DIR__.'/../routes/inertia.php');

        Route::middleware($middleware)
            ->prefix('nova-vendor/nova-settings')
            ->group(__DIR__.'/../routes/api.php');
    }
}
