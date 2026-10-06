<?php

namespace LucaCermenati\NovaSettings;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\File;
use LucaCermenati\NovaSettings\Casting\ValueCaster;
use LucaCermenati\NovaSettings\Fields\FieldFactory;
use LucaCermenati\NovaSettings\Models\Setting;
use LucaCermenati\NovaSettings\Registry\GroupDefinition;
use LucaCermenati\NovaSettings\Registry\Registry;
use LucaCermenati\NovaSettings\Sync\Synchronizer;
use LucaCermenati\NovaSettings\Sync\SyncReport;
use Throwable;

class SettingsManager
{
    protected ?array $loaded = null;

    public function __construct(
        protected Registry $registry,
        protected FieldFactory $factory,
        protected CacheFactory $cache,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Definitions
    |--------------------------------------------------------------------------
    */

    /**
     * Define a group of settings (one tab) in code.
     *
     * @param  Closure(): array<int, Field>|array<int, Field>  $fields
     */
    public function group(string $key, string $label, Closure|array $fields): GroupDefinition
    {
        return $this->registry->group($key, $label, $fields);
    }

    public function registry(): Registry
    {
        return $this->registry;
    }

    /**
     * The Nova field for a setting row: the live code-defined field when the
     * setting is managed in code, otherwise one built from type + options.
     */
    public function fieldFor(Setting $setting): ?Field
    {
        if ($setting->isLocked() && ($field = $this->registry->field($setting->key))) {
            return $field;
        }

        try {
            return $this->factory->make($setting);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function sync(bool $prune = false): SyncReport
    {
        $report = (new Synchronizer($this->registry))->sync($prune);

        $this->store()->forever($this->cacheKey('fingerprint'), $this->registry->fingerprint());
        $this->flushCache();

        return $report;
    }

    /**
     * Sync code definitions when they changed since the last sync. Cheap: one
     * cache read on the hot path.
     */
    public function autoSync(): void
    {
        if (! config('nova-settings.auto_sync', true) || $this->registry->isEmpty()) {
            return;
        }

        try {
            $upToDate = $this->store()->get($this->cacheKey('fingerprint')) === $this->registry->fingerprint();

            if (! $upToDate && Schema::hasTable('settings')) {
                $this->sync();
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Values
    |--------------------------------------------------------------------------
    */

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values()[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values());
    }

    /**
     * @return array<string, mixed> key => cast value
     */
    public function all(): array
    {
        return $this->values();
    }

    /**
     * Store a value programmatically (no Nova field processing).
     */
    public function set(string $key, mixed $value): void
    {
        Setting::query()->where('key', $key)->firstOrFail()->update(['value' => $value]);

        $this->flushCache();
    }

    public function forget(string $key): void
    {
        $this->set($key, null);
    }

    /**
     * Public URL of a File / Image setting.
     */
    public function url(string $key): ?string
    {
        $setting = Setting::query()->where('key', $key)->first();

        if (! $setting?->hasStoredFile()) {
            return null;
        }

        $field = $this->fieldFor($setting);
        $disk = $field instanceof File ? $field->getStorageDisk() : null;

        return Storage::disk($disk ?? $setting->storageDisk())->url($setting->value);
    }

    public function flushCache(): void
    {
        $this->loaded = null;

        try {
            $this->store()->forget($this->cacheKey('values'));
        } catch (Throwable) {
            //
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function values(): array
    {
        return $this->loaded ??= $this->cachedValues();
    }

    /**
     * @return array<string, mixed>
     */
    protected function cachedValues(): array
    {
        if (! config('nova-settings.cache.enabled', true)) {
            return $this->load();
        }

        try {
            return $this->store()->rememberForever($this->cacheKey('values'), $this->load(...));
        } catch (Throwable) {
            return $this->load();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function load(): array
    {
        try {
            $settings = Setting::query()->get();
        } catch (QueryException) {
            return [];
        }

        return $settings->mapWithKeys(function (Setting $setting) {
            $options = $setting->fieldOptions();
            $raw = $setting->value ?? $options['default'] ?? null;

            return [$setting->key => ValueCaster::cast($setting->type, $raw, $options)];
        })->all();
    }

    protected function store(): Repository
    {
        return $this->cache->store(config('nova-settings.cache.store'));
    }

    protected function cacheKey(string $suffix): string
    {
        return config('nova-settings.cache.key', 'nova-settings').'.'.$suffix;
    }
}
