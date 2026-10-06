<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use LucaCermenati\NovaSettings\Enums\Source;
use LucaCermenati\NovaSettings\Facades\NovaSettings;
use LucaCermenati\NovaSettings\Form\FormBuilder;
use LucaCermenati\NovaSettings\Models\Setting;
use LucaCermenati\NovaSettings\Models\SettingGroup;

beforeEach(function () {
    Storage::fake('public');

    NovaSettings::group('general', 'General', fn () => [
        Text::make('Site name', 'site_name')->rules('required'),
        Number::make('Items per page', 'items_per_page')->default(25),
        Boolean::make('Maintenance', 'maintenance'),
        Image::make('Logo', 'logo')->disk('public')->path('settings')->prunable(),
        KeyValue::make('Links', 'links'),
    ])->order(1);

    NovaSettings::sync();

    // A group + setting created from the Nova UI (database source).
    $seo = SettingGroup::create(['key' => 'seo', 'label' => 'SEO', 'sort_order' => 2]);
    Setting::create([
        'setting_group_id' => $seo->id,
        'key' => 'meta_title',
        'label' => 'Meta title',
        'type' => Select::class,
        'options' => ['options' => ['a' => 'A', 'b' => 'B'], 'rules' => ['in:a,b']],
        'source' => Source::Database,
    ]);
});

it('serializes one tab per group with Nova field payloads', function () {
    $data = json_decode(json_encode(app(FormBuilder::class)->serialize($this->novaRequest())), true);

    $tabs = collect($data['panels'])->firstWhere('component', 'tabs-panel');

    expect($tabs)->not->toBeNull()
        ->and($tabs['fields'])->toHaveCount(6)
        ->and(collect($data['fields'])->pluck('tab.attribute')->unique()->values()->all())->toBe(['general', 'seo'])
        ->and(collect($data['fields'])->firstWhere('attribute', 'items_per_page')['value'])->toBe(25)
        ->and(collect($data['fields'])->firstWhere('attribute', 'meta_title')['component'])->toBe('select-field')
        ->and(collect($data['fields'])->firstWhere('attribute', 'logo')['component'])->toBe('file-field');
});

it('validates using the field rules', function () {
    try {
        app(FormBuilder::class)->save($this->novaRequest('POST', ['site_name' => '', 'meta_title' => 'zzz']));
        $this->fail('Expected a validation exception');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKeys(['site_name', 'meta_title']);
    }
});

it('persists submitted values with proper casts', function () {
    app(FormBuilder::class)->save($this->novaRequest('POST', [
        'site_name' => 'Acme',
        'items_per_page' => '10',
        'maintenance' => '1',
        'links' => json_encode(['twitter' => 'https://x.com/acme']),
        'meta_title' => 'b',
    ]));

    expect(setting('site_name'))->toBe('Acme')
        ->and(setting('items_per_page'))->toBe(10)
        ->and(setting('maintenance'))->toBeTrue()
        ->and(setting('links'))->toBe(['twitter' => 'https://x.com/acme'])
        ->and(setting('meta_title'))->toBe('b');
});

it('stores, replaces and deletes uploaded files', function () {
    $request = $this->novaRequest('POST', ['site_name' => 'Acme'], ['logo' => UploadedFile::fake()->image('logo.png')]);
    app(FormBuilder::class)->save($request);

    $first = setting('logo');
    expect($first)->toStartWith('settings/');
    Storage::disk('public')->assertExists($first);

    // Replacing prunes the previous file.
    $request = $this->novaRequest('POST', ['site_name' => 'Acme'], ['logo' => UploadedFile::fake()->image('new.png')]);
    app(FormBuilder::class)->save($request);

    $second = setting('logo');
    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);

    app(FormBuilder::class)->deleteFile($this->novaRequest('DELETE'), 'logo');

    expect(setting('logo'))->toBeNull();
    Storage::disk('public')->assertMissing($second);
});

it('exposes the form through the tool endpoints', function () {
    $this->actingAs($this->user());

    $this->getJson('/nova-vendor/nova-settings/fields')
        ->assertOk()
        ->assertJsonPath('panels.1.component', 'tabs-panel');

    $this->postJson('/nova-vendor/nova-settings/fields', ['site_name' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['site_name']);

    $this->postJson('/nova-vendor/nova-settings/fields', ['site_name' => 'Acme', 'maintenance' => '0'])
        ->assertNoContent();

    expect(setting('site_name'))->toBe('Acme')
        ->and(setting('maintenance'))->toBeFalse();
});
