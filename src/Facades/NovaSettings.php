<?php

namespace LucaCermenati\NovaSettings\Facades;

use Illuminate\Support\Facades\Facade;
use LucaCermenati\NovaSettings\SettingsManager;

/**
 * @method static \LucaCermenati\NovaSettings\Registry\GroupDefinition group(string $key, string $label, \Closure|array $fields)
 * @method static \LucaCermenati\NovaSettings\Registry\Registry registry()
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(string $key, mixed $value)
 * @method static bool has(string $key)
 * @method static array all()
 * @method static string|null url(string $key)
 * @method static void forget(string $key)
 * @method static void flushCache()
 * @method static \LucaCermenati\NovaSettings\Sync\SyncReport sync(bool $prune = false)
 *
 * @see SettingsManager
 */
class NovaSettings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingsManager::class;
    }
}
