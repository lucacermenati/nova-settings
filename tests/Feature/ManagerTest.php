<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use LucaCermenati\NovaSettings\Facades\NovaSettings;
use LucaCermenati\NovaSettings\Models\Setting;
use LucaCermenati\NovaSettings\SettingsManager;

beforeEach(function () {
    NovaSettings::group('general', 'General', fn () => [
        Text::make('Site name', 'site_name'),
        Number::make('Items per page', 'items_per_page')->default(25),
        Boolean::make('Maintenance', 'maintenance'),
        Image::make('Logo', 'logo')->disk('public')->path('settings'),
    ]);

    NovaSettings::sync();
});

it('returns cast values, defaults and fallbacks', function () {
    Setting::where('key', 'maintenance')->update(['value' => json_encode(true)]);
    NovaSettings::flushCache();

    expect(setting('maintenance'))->toBeTrue()
        ->and(setting('items_per_page'))->toBe(25)
        ->and(setting('site_name'))->toBeNull()
        ->and(setting('site_name', 'fallback'))->toBe('fallback')
        ->and(setting('unknown', 'x'))->toBe('x')
        ->and(NovaSettings::has('site_name'))->toBeTrue()
        ->and(NovaSettings::has('unknown'))->toBeFalse()
        ->and(NovaSettings::all())->toHaveKeys(['site_name', 'items_per_page', 'maintenance', 'logo']);
});

it('writes values with set() and invalidates the cache', function () {
    NovaSettings::set('site_name', 'Acme');

    expect(setting('site_name'))->toBe('Acme')
        ->and(Cache::get('nova-settings.values'))->toMatchArray(['site_name' => 'Acme']);

    NovaSettings::forget('site_name');

    expect(setting('site_name'))->toBeNull();
});

it('serves values from cache without querying the database', function () {
    setting('site_name');

    DB::enableQueryLog();
    app('LucaCermenati\NovaSettings\SettingsManager')->flushCache();
    setting('site_name');
    $first = count(DB::getQueryLog());

    // A fresh manager instance must hit the cache, not the database.
    $this->app->forgetInstance(SettingsManager::class);
    DB::flushQueryLog();
    setting('site_name');

    expect($first)->toBeGreaterThan(0)
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('builds the public url of a file setting', function () {
    expect(setting_url('logo'))->toBeNull();

    NovaSettings::set('logo', 'settings/logo.png');

    expect(setting_url('logo'))->toBe('http://localhost/storage/settings/logo.png');
});
