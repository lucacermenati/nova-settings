<?php

namespace LucaCermenati\NovaSettings\Events;

class SettingsSaved
{
    /**
     * @param  array<int, string>  $keys  the setting keys that were submitted
     */
    public function __construct(public array $keys) {}
}
