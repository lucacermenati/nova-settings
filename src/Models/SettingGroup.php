<?php

namespace LucaCermenati\NovaSettings\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LucaCermenati\NovaSettings\Enums\Source;
use LucaCermenati\NovaSettings\Registry\Registry;

/**
 * @property int $id
 * @property string $key
 * @property string $label
 * @property string|null $icon
 * @property int $sort_order
 * @property Source $source
 * @property-read Collection<int, Setting> $settings
 */
class SettingGroup extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sort_order' => 'integer',
        'source' => Source::class,
    ];

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class)->ordered();
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
     */
    public function isManagedByCode(): bool
    {
        return $this->isLocked() && app(Registry::class)->has($this->key);
    }
}
