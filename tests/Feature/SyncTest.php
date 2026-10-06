<?php

use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use LucaCermenati\NovaSettings\Enums\Source;
use LucaCermenati\NovaSettings\Models\Setting;
use LucaCermenati\NovaSettings\Models\SettingGroup;

beforeEach(function () {
    $this->manager()->group('general', 'General', fn () => [
        Text::make('Site name', 'site_name')->help('Shown in the header'),
        Number::make('Items per page', 'items_per_page')->default(25),
    ])->icon('cog')->order(1);

    $this->manager()->group('flags', 'Flags', fn () => [
        Boolean::make('Maintenance', 'maintenance'),
    ])->order(2);
});

it('creates groups and settings from code definitions', function () {
    $report = $this->manager()->sync();

    expect($report->groupsCreated)->toBe(2)
        ->and($report->settingsCreated)->toBe(3)
        ->and(SettingGroup::count())->toBe(2)
        ->and(Setting::count())->toBe(3);

    $setting = Setting::where('key', 'site_name')->first();

    expect($setting->source)->toBe(Source::Code)
        ->and($setting->type)->toBe(Text::class)
        ->and($setting->label)->toBe('Site name')
        ->and($setting->description)->toBe('Shown in the header')
        ->and($setting->group->key)->toBe('general')
        ->and($setting->group->icon)->toBe('cog');

    expect(Setting::where('key', 'items_per_page')->first()->options)->toBe(['default' => 25]);
});

it('is idempotent and never touches stored values', function () {
    $this->manager()->sync();

    Setting::where('key', 'site_name')->update(['value' => json_encode('Acme')]);

    $report = $this->manager()->sync();

    expect($report->settingsCreated)->toBe(0)
        ->and($report->settingsUpdated)->toBe(0)
        ->and(Setting::where('key', 'site_name')->first()->value)->toBe('Acme');
});

it('reports orphaned code rows and only deletes them when pruning', function () {
    $this->manager()->sync();

    $this->manager()->registry()->flush();
    $this->manager()->group('general', 'General', fn () => [
        Text::make('Site name', 'site_name'),
    ]);

    $report = $this->manager()->sync();

    expect($report->orphanedSettings)->toBe(['items_per_page', 'maintenance'])
        ->and($report->orphanedGroups)->toBe(['flags'])
        ->and(Setting::where('key', 'maintenance')->first()->source)->toBe(Source::Code)
        ->and(Setting::where('key', 'maintenance')->first()->isManagedByCode())->toBeFalse()
        ->and(Setting::where('key', 'site_name')->first()->isManagedByCode())->toBeTrue()
        ->and(SettingGroup::where('key', 'flags')->first()->isManagedByCode())->toBeFalse();

    $this->manager()->sync(prune: true);

    expect(Setting::where('key', 'maintenance')->exists())->toBeFalse()
        ->and(SettingGroup::where('key', 'flags')->exists())->toBeFalse()
        ->and(Setting::where('key', 'site_name')->exists())->toBeTrue();
});

it('adopts a group created in the UI with the same key', function () {
    SettingGroup::create(['key' => 'general', 'label' => 'Custom', 'source' => Source::Database]);

    $this->manager()->sync();

    $group = SettingGroup::where('key', 'general')->first();

    expect(SettingGroup::count())->toBe(2)
        ->and($group->label)->toBe('General')
        ->and($group->source)->toBe(Source::Code);
});

it('auto syncs only when the definitions changed', function () {
    config()->set('nova-settings.auto_sync', true);

    $this->manager()->autoSync();
    expect(Setting::count())->toBe(3);

    $this->manager()->group('flags', 'Flags', fn () => [
        Boolean::make('Maintenance', 'maintenance'),
        Boolean::make('Beta', 'beta'),
    ]);

    $this->manager()->autoSync();
    expect(Setting::count())->toBe(4);
});
