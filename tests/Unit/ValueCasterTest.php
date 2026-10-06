<?php

use Illuminate\Support\Carbon;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use LucaCermenati\NovaSettings\Casting\ValueCaster;

it('casts values according to the field class', function (string $type, mixed $raw, mixed $expected, array $options = []) {
    expect(ValueCaster::cast($type, $raw, $options))->toEqual($expected);
})->with([
    'boolean true' => [Boolean::class, '1', true],
    'boolean false' => [Boolean::class, 0, false],
    'number int' => [Number::class, '42', 42],
    'number float' => [Number::class, '3.5', 3.5],
    'currency' => [Currency::class, '19.99', 19.99],
    'multiselect json string' => [MultiSelect::class, '["a","b"]', ['a', 'b']],
    'keyvalue array' => [KeyValue::class, ['x' => 'y'], ['x' => 'y']],
    'keyvalue null' => [KeyValue::class, null, null],
    'code json' => [Code::class, '{"a":1}', ['a' => 1], ['json' => true]],
    'code raw' => [Code::class, 'plain', 'plain'],
    'text' => [Text::class, 'hello', 'hello'],
    'image path' => [Image::class, 'settings/x.png', 'settings/x.png'],
]);

it('casts dates to Carbon', function () {
    expect(ValueCaster::cast(Date::class, '2026-01-02'))->toBeInstanceOf(Carbon::class)
        ->and(ValueCaster::cast(Date::class, '2026-01-02')->toDateString())->toBe('2026-01-02');
});

it('renders a printable display value', function () {
    expect(ValueCaster::display(Boolean::class, true))->toBe('true')
        ->and(ValueCaster::display(KeyValue::class, ['a' => 'b']))->toBe('{"a":"b"}')
        ->and(ValueCaster::display(Text::class, null))->toBeNull();
});
