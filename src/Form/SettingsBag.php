<?php

namespace LucaCermenati\NovaSettings\Form;

use Illuminate\Database\Eloquent\Model;
use LucaCermenati\NovaSettings\Models\Setting;

/**
 * A throw-away Eloquent model that carries the current setting values
 * (key => raw value). Nova fields resolve against it and fill into it; it is
 * never persisted — the FormBuilder copies its attributes back to the rows.
 */
class SettingsBag extends Model
{
    protected $table = 'settings';

    public $timestamps = false;

    protected $guarded = [];

    public $exists = true;

    /**
     * @param  iterable<Setting>  $settings
     */
    public static function fromSettings(iterable $settings): static
    {
        $bag = new static;

        foreach ($settings as $setting) {
            $bag->setAttribute($setting->key, $setting->value);
        }

        $bag->syncOriginal();

        return $bag;
    }

    public function save(array $options = []): bool
    {
        return true;
    }

    public function getKey(): int
    {
        return 0;
    }
}
