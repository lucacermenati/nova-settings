<?php

namespace LucaCermenati\NovaSettings\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\File;
use LucaCermenati\NovaSettings\Casting\ValueCaster;
use LucaCermenati\NovaSettings\Enums\Source;
use LucaCermenati\NovaSettings\Registry\Registry;
use LucaCermenati\NovaSettings\SettingsManager;

/**
 * @property int $id
 * @property int $setting_group_id
 * @property string $key
 * @property string $label
 * @property string|null $description
 * @property class-string<Field> $type
 * @property array|null $options
 * @property mixed $value
 * @property int $sort_order
 * @property Source $source
 * @property-read SettingGroup $group
 */
class Setting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'value' => 'json',
        'sort_order' => 'integer',
        'source' => Source::class,
    ];

    protected static function booted(): void
    {
        static::saved(fn () => app(SettingsManager::class)->flushCache());

        static::deleted(function (Setting $setting) {
            $setting->deleteStoredFile();

            app(SettingsManager::class)->flushCache();
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SettingGroup::class, 'setting_group_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function isLocked(): bool
    {
        return $this->source === Source::Code;
    }

    /**
     * Code-sourced and still defined in code: read-only in the Nova resources.
     * A code row whose key disappeared from the code stays editable/deletable.
     */
    public function isManagedByCode(): bool
    {
        return $this->isLocked() && app(Registry::class)->field($this->key) !== null;
    }

    public function isFile(): bool
    {
        return is_a($this->type, File::class, true);
    }

    /**
     * The options array passed to the field factory, always an array even
     * when the stored JSON is null or malformed.
     */
    public function fieldOptions(): array
    {
        return is_array($this->options) ? $this->options : [];
    }

    /**
     * The stored value cast to its PHP type (bool, int, array, Carbon, ...).
     */
    public function castValue(): mixed
    {
        return ValueCaster::cast($this->type, $this->value, $this->fieldOptions());
    }

    /**
     * The disk a File / Image setting stores its file on.
     */
    public function storageDisk(): string
    {
        return $this->fieldOptions()['disk'] ?? config('nova-settings.files.disk', 'public');
    }

    public function deleteStoredFile(): void
    {
        if ($this->isFile() && is_string($this->value) && $this->value !== '') {
            Storage::disk($this->storageDisk())->delete($this->value);
        }
    }
}
