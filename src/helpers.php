<?php

use LucaCermenati\NovaSettings\SettingsManager;

if (! function_exists('setting')) {
    /**
     * Get a setting value (cast to its PHP type), or the manager when no key is given.
     */
    function setting(?string $key = null, mixed $default = null): mixed
    {
        $manager = app(SettingsManager::class);

        if ($key === null) {
            return $manager;
        }

        return $manager->get($key, $default);
    }
}

if (! function_exists('setting_url')) {
    /**
     * Public URL of a File / Image setting, or null when nothing is stored.
     */
    function setting_url(string $key): ?string
    {
        return app(SettingsManager::class)->url($key);
    }
}
