<?php

namespace LucaCermenati\NovaSettings\Sync;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use LucaCermenati\NovaSettings\Enums\Source;
use LucaCermenati\NovaSettings\Models\Setting;
use LucaCermenati\NovaSettings\Models\SettingGroup;
use LucaCermenati\NovaSettings\Registry\GroupDefinition;
use LucaCermenati\NovaSettings\Registry\Registry;
use Throwable;

/**
 * Mirrors the groups/settings defined in code into the database tables.
 * Values are never touched.
 */
class Synchronizer
{
    public function __construct(protected Registry $registry) {}

    public function sync(bool $prune = false): SyncReport
    {
        $report = new SyncReport;
        $report->pruned = $prune;

        DB::transaction(function () use ($report, $prune) {
            $request = $this->request();
            $groupKeys = [];
            $settingKeys = [];

            foreach ($this->registry->groups() as $definition) {
                $group = $this->syncGroup($definition, $report);
                $groupKeys[] = $group->key;

                foreach ($definition->fields() as $index => $field) {
                    $this->syncSetting($group, $field, $index + 1, $request, $report);
                    $settingKeys[] = $field->attribute;
                }
            }

            $this->handleOrphans($groupKeys, $settingKeys, $prune, $report);
        });

        return $report;
    }

    protected function syncGroup(GroupDefinition $definition, SyncReport $report): SettingGroup
    {
        $group = SettingGroup::query()->firstOrNew(['key' => $definition->key]);

        $group->fill([
            'label' => $definition->label,
            'icon' => $definition->icon,
            'sort_order' => $definition->order,
            'source' => Source::Code,
        ]);

        if (! $group->exists) {
            $report->groupsCreated++;
        } elseif ($group->isDirty()) {
            $report->groupsUpdated++;
        }

        $group->save();

        return $group;
    }

    protected function syncSetting(SettingGroup $group, Field $field, int $position, NovaRequest $request, SyncReport $report): void
    {
        $setting = Setting::query()->firstOrNew(['key' => $field->attribute]);

        $options = $setting->fieldOptions();
        $default = $this->defaultOf($field, $request);

        if ($default !== null) {
            $options['default'] = $default;
        } else {
            unset($options['default']);
        }

        $setting->fill([
            'setting_group_id' => $group->id,
            'label' => (string) $field->name,
            'description' => $field->helpText ? (string) $field->helpText : null,
            'type' => $field::class,
            'sort_order' => $position,
            'source' => Source::Code,
            'options' => $options === [] ? null : $options,
        ]);

        if (! $setting->exists) {
            $report->settingsCreated++;
        } elseif ($setting->isDirty()) {
            $report->settingsUpdated++;
        }

        $setting->save();
    }

    /**
     * Code rows whose key disappeared from the registry are reported (and shown
     * as editable/deletable in the Nova resources); they are only deleted when
     * pruning.
     */
    protected function handleOrphans(array $groupKeys, array $settingKeys, bool $prune, SyncReport $report): void
    {
        $orphanSettings = $this->orphans(Setting::class, $settingKeys);
        $report->orphanedSettings = $orphanSettings->pluck('key')->all();

        if ($prune) {
            $orphanSettings->each->delete();
        }

        $orphanGroups = $this->orphans(SettingGroup::class, $groupKeys);
        $report->orphanedGroups = $orphanGroups->pluck('key')->all();

        if ($prune) {
            $orphanGroups->filter(fn (SettingGroup $group) => $group->settings()->doesntExist())->each->delete();
        }
    }

    /**
     * Code-sourced rows of the given model whose key is not in the given list.
     *
     * @template TModel of Setting|SettingGroup
     *
     * @param  class-string<TModel>  $model
     * @param  array<int, string>  $keys
     * @return Collection<int, TModel>
     */
    protected function orphans(string $model, array $keys): Collection
    {
        return $model::query()
            ->where('source', Source::Code->value)
            ->whereNotIn('key', $keys)
            ->get();
    }

    protected function defaultOf(Field $field, NovaRequest $request): mixed
    {
        try {
            $default = $field->resolveDefaultCallback($request);
        } catch (Throwable) {
            return null;
        }

        return is_scalar($default) || is_array($default) ? $default : null;
    }

    protected function request(): NovaRequest
    {
        $current = app('request');

        return $current instanceof NovaRequest ? $current : NovaRequest::createFrom($current);
    }
}
