<?php

namespace LucaCermenati\NovaSettings\Tests;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Inertia\ServiceProvider;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaCoreServiceProvider;
use LucaCermenati\NovaSettings\NovaSettings;
use LucaCermenati\NovaSettings\NovaSettingsServiceProvider;
use LucaCermenati\NovaSettings\SettingsManager;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Gate::define('viewNova', fn ($user = null) => true);
        Nova::auth(fn () => true);
        Nova::tools([new NovaSettings]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
            NovaCoreServiceProvider::class,
            NovaSettingsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/public'),
            'url' => 'http://localhost/storage',
            'visibility' => 'public',
        ]);
        $app['config']->set('nova-settings.auto_sync', false);
    }

    protected function manager(): SettingsManager
    {
        return $this->app->make(SettingsManager::class);
    }

    protected function novaRequest(string $method = 'GET', array $parameters = [], array $files = []): NovaRequest
    {
        $request = NovaRequest::create('/nova-vendor/nova-settings/fields', $method, $parameters, [], $files);
        $request->headers->set('Accept', 'application/json');

        $this->app->instance('request', $request);

        return $request;
    }

    protected function user(): User
    {
        return User::query()->forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
    }
}
