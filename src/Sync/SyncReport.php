<?php

namespace LucaCermenati\NovaSettings\Sync;

class SyncReport
{
    public int $groupsCreated = 0;

    public int $groupsUpdated = 0;

    public int $settingsCreated = 0;

    public int $settingsUpdated = 0;

    /** @var array<int, string> keys of code rows no longer defined in code */
    public array $orphanedSettings = [];

    /** @var array<int, string> */
    public array $orphanedGroups = [];

    public bool $pruned = false;
}
