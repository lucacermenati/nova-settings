<?php

namespace LucaCermenati\NovaSettings\Console;

use Illuminate\Console\Command;
use LucaCermenati\NovaSettings\SettingsManager;

class SyncCommand extends Command
{
    protected $signature = 'nova-settings:sync {--prune : Delete code-defined rows that are no longer defined in code}';

    protected $description = 'Synchronise the settings defined in code into the database';

    public function handle(SettingsManager $manager): int
    {
        $report = $manager->sync((bool) $this->option('prune'));

        $this->table(['', 'Created', 'Updated'], [
            ['Groups', $report->groupsCreated, $report->groupsUpdated],
            ['Settings', $report->settingsCreated, $report->settingsUpdated],
        ]);

        $orphans = array_merge(
            array_map(fn ($k) => "group {$k}", $report->orphanedGroups),
            array_map(fn ($k) => "setting {$k}", $report->orphanedSettings),
        );

        if ($orphans !== []) {
            $action = $report->pruned ? 'deleted' : 'no longer defined in code (use --prune to delete)';
            $this->warn(count($orphans)." row(s) {$action}: ".implode(', ', $orphans));
        }

        $this->info('Settings synchronised.');

        return self::SUCCESS;
    }
}
