<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use LucaCermenati\NovaSettings\Enums\Source;
use LucaCermenati\NovaSettings\Models\Setting;
use LucaCermenati\NovaSettings\Models\SettingGroup;
use LucaCermenati\NovaSettings\Nova\SettingResource;

function resourceRequest(array $parameters, string $method = 'POST', ?int $resourceId = null): NovaRequest
{
    $uri = '/nova-api/settings'.($resourceId ? '/'.$resourceId : '');
    $request = NovaRequest::create($uri, $method, $parameters);
    $request->headers->set('Accept', 'application/json');

    $route = new Route($method, '/nova-api/{resource}/{resourceId?}', fn () => null);
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);

    return $request;
}

function choicesRules(array $rules): ?array
{
    foreach ($rules as $key => $value) {
        if (str_contains($key, 'options')) {
            return $value;
        }
    }

    return null;
}

beforeEach(function () {
    $this->group = SettingGroup::create(['key' => 'seo', 'label' => 'SEO', 'sort_order' => 1]);
});

it('requires choices only for choice-based field types', function () {
    $base = ['group' => $this->group->id, 'key' => 'k', 'label' => 'K'];

    $select = choicesRules(SettingResource::rulesForCreation(resourceRequest($base + ['type' => Select::class])));
    $text = choicesRules(SettingResource::rulesForCreation(resourceRequest($base + ['type' => Text::class])));

    expect($select)->not->toBeNull()
        ->and($select)->toContain('required')
        ->and($text ?? [])->not->toContain('required');
});

it('stores the choices under options.options', function () {
    $request = resourceRequest([
        'group' => $this->group->id,
        'key' => 'colors',
        'label' => 'Colors',
        'type' => MultiSelect::class,
        'options->options' => '{"red":"Red","blue":"Blue"}',
    ]);

    [$model] = SettingResource::fill($request, new Setting);

    expect($model->type)->toBe(MultiSelect::class)
        ->and($model->options)->toBe(['options' => ['red' => 'Red', 'blue' => 'Blue']]);
});

it('drops stale choices when the type no longer needs them', function () {
    $setting = Setting::create([
        'setting_group_id' => $this->group->id,
        'key' => 'colors',
        'label' => 'Colors',
        'type' => Select::class,
        'options' => ['options' => ['red' => 'Red']],
        'source' => Source::Database,
    ]);

    $request = resourceRequest([
        'group' => $this->group->id,
        'key' => 'colors',
        'label' => 'Colors',
        'type' => Text::class,
    ], 'PUT', $setting->id);

    [$model] = SettingResource::fillForUpdate($request, $setting);

    expect($model->type)->toBe(Text::class)
        ->and($model->options)->toBeNull();
});

it('renders the detail view of a row with malformed options', function () {
    DB::table('settings')->insert([
        'setting_group_id' => $this->group->id,
        'key' => 'broken',
        'label' => 'Broken',
        'type' => MultiSelect::class,
        'options' => '"null"',
        'value' => null,
        'sort_order' => 0,
        'source' => Source::Database->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $setting = Setting::where('key', 'broken')->firstOrFail();
    $fields = (new SettingResource($setting))->detailFields(resourceRequest([], 'GET'));
    $current = $fields->first(fn ($field) => (string) $field->name === 'Current value');

    expect($current)->not->toBeNull()
        ->and($current->value)->toBeNull();
});
