<?php

use Illuminate\Support\Facades\Route;
use Laravel\Nova\Http\Requests\NovaRequest;

Route::get('/', function (NovaRequest $request) {
    return inertia('NovaSettings', [
        'label' => config('nova-settings.tool.label', 'Settings'),
        'basePath' => config('nova-settings.tool.path', 'nova-settings'),
    ]);
});
