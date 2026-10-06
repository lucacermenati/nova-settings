<?php

use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\BooleanGroup;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Color;
use Laravel\Nova\Fields\Country;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Email;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\Markdown;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Timezone;
use Laravel\Nova\Fields\Trix;
use Laravel\Nova\Fields\URL;

return [

    /*
    |--------------------------------------------------------------------------
    | Tool
    |--------------------------------------------------------------------------
    |
    | The sidebar label, Heroicon name and URI (under /nova) of the settings
    | tool page where all values are edited.
    |
    */

    'tool' => [
        'label' => 'Settings',
        'icon' => 'cog-6-tooth',
        'path' => 'nova-settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Manage definitions from the Nova UI
    |--------------------------------------------------------------------------
    |
    | When enabled the package registers two Nova resources (Setting Groups and
    | Settings) so admins can create groups (tabs) and settings, choosing the
    | Nova field type from a dropdown. Disable to only allow code definitions.
    |
    */

    'manage_definitions' => true,

    /*
    |--------------------------------------------------------------------------
    | Auto sync
    |--------------------------------------------------------------------------
    |
    | Synchronise the settings defined in code into the database whenever Nova
    | is served and the definitions changed (guarded by a fingerprint stored in
    | cache). You can always run `php artisan nova-settings:sync` manually.
    |
    */

    'auto_sync' => env('NOVA_SETTINGS_AUTO_SYNC', true),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => true,
        'store' => null,
        'key' => 'nova-settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Files
    |--------------------------------------------------------------------------
    |
    | Default disk and directory for File / Image settings created from the UI
    | (code-defined fields use whatever you configured on the field itself).
    |
    */

    'files' => [
        'disk' => env('NOVA_SETTINGS_DISK', 'public'),
        'path' => 'settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Field types
    |--------------------------------------------------------------------------
    |
    | The Nova field classes that can be picked when creating a setting from
    | the Nova UI (label => class). Add your own field classes here. Fields
    | that need a real resource context (relations, Repeater, Tag, dependsOn)
    | are not supported on the settings page.
    |
    */

    'field_types' => [
        'Text' => Text::class,
        'Textarea' => Textarea::class,
        'Number' => Number::class,
        'Currency' => Currency::class,
        'Boolean' => Boolean::class,
        'Boolean group' => BooleanGroup::class,
        'Select' => Select::class,
        'Multi select' => MultiSelect::class,
        'Date' => Date::class,
        'Date & time' => DateTime::class,
        'Email' => Email::class,
        'URL' => URL::class,
        'Password' => Password::class,
        'Color' => Color::class,
        'Code' => Code::class,
        'Markdown' => Markdown::class,
        'Trix' => Trix::class,
        'Key / value' => KeyValue::class,
        'File' => File::class,
        'Image' => Image::class,
        'Timezone' => Timezone::class,
        'Country' => Country::class,
    ],

];
