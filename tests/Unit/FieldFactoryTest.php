<?php

use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\BooleanGroup;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use LucaCermenati\NovaSettings\Enums\Source;
use LucaCermenati\NovaSettings\Fields\FieldFactory;
use LucaCermenati\NovaSettings\Fields\UnsupportedFieldTypeException;
use LucaCermenati\NovaSettings\Models\Setting;

function makeSetting(string $type, array $options = [], string $source = 'database'): Setting
{
    return new Setting([
        'key' => 'my_setting',
        'label' => 'My setting',
        'description' => 'Some help',
        'type' => $type,
        'options' => $options,
        'source' => Source::from($source),
    ]);
}

it('builds a select with options and rules', function () {
    $field = (new FieldFactory)->make(makeSetting(Select::class, [
        'options' => ['a' => 'A', 'b' => 'B'],
        'required' => true,
        'rules' => ['in:a,b'],
        'placeholder' => 'Pick one',
    ]));

    expect($field)->toBeInstanceOf(Select::class)
        ->and($field->attribute)->toBe('my_setting')
        ->and((string) $field->name)->toBe('My setting')
        ->and($field->helpText)->toBe('Some help')
        ->and($field->rules)->toBe(['required', 'in:a,b'])
        ->and($field->jsonSerialize()['options'])->toHaveCount(2);
});

it('applies number and image specific options', function () {
    $number = (new FieldFactory)->make(makeSetting(Number::class, ['min' => 1, 'max' => 10, 'step' => 0.5]));
    $image = (new FieldFactory)->make(makeSetting(Image::class, ['disk' => 'public', 'path' => 'logos']));

    expect($number->jsonSerialize())->toMatchArray(['min' => 1, 'max' => 10, 'step' => 0.5])
        ->and($image->getStorageDisk())->toBe('public')
        ->and($image->getStorageDir())->toBe('logos');
});

it('falls back to the configured disk for files', function () {
    config()->set('nova-settings.files.disk', 's3');

    $image = (new FieldFactory)->make(makeSetting(Image::class));

    expect($image->getStorageDisk())->toBe('s3');
});

it('rejects field classes that are not allowed for database settings', function () {
    config()->set('nova-settings.field_types', ['Text' => Text::class]);

    (new FieldFactory)->make(makeSetting(Boolean::class));
})->throws(UnsupportedFieldTypeException::class);

it('allows any field class for code-defined settings', function () {
    config()->set('nova-settings.field_types', ['Text' => Text::class]);

    expect((new FieldFactory)->make(makeSetting(Boolean::class, [], 'code')))->toBeInstanceOf(Boolean::class);
});

it('knows which field types cannot work without choices', function () {
    expect(FieldFactory::requiresChoices(Select::class))->toBeTrue()
        ->and(FieldFactory::requiresChoices(MultiSelect::class))->toBeTrue()
        ->and(FieldFactory::requiresChoices(BooleanGroup::class))->toBeTrue()
        ->and(FieldFactory::requiresChoices(Text::class))->toBeFalse()
        ->and(FieldFactory::requiresChoices(Number::class))->toBeFalse()
        ->and(FieldFactory::requiresChoices(KeyValue::class))->toBeFalse()
        ->and(FieldFactory::requiresChoices('not-a-class'))->toBeFalse();
});

it('tolerates a setting whose stored options are not an array', function () {
    $setting = makeSetting(Text::class);
    $setting->setRawAttributes(['type' => Text::class, 'key' => 'my_setting', 'label' => 'My setting', 'options' => '"null"']);

    expect($setting->options)->toBe('null')
        ->and($setting->fieldOptions())->toBe([])
        ->and((new FieldFactory)->make($setting))->toBeInstanceOf(Text::class);
});
