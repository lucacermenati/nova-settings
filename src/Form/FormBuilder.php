<?php

namespace LucaCermenati\NovaSettings\Form;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Nova\Contracts\Deletable;
use Laravel\Nova\DeleteField;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\FieldCollection;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Tabs\Tab;
use Laravel\Nova\Tabs\TabsGroup;
use LucaCermenati\NovaSettings\Events\SettingsSaved;
use LucaCermenati\NovaSettings\Models\Setting;
use LucaCermenati\NovaSettings\Models\SettingGroup;
use LucaCermenati\NovaSettings\SettingsManager;

/**
 * Builds the settings form (one tab per group) out of Nova's own field and
 * panel machinery, and writes submitted values back to the settings rows.
 */
class FormBuilder
{
    public function __construct(protected SettingsManager $manager) {}

    /**
     * The payload the tool page renders: Nova panels (a tabs panel) + fields.
     */
    public function serialize(NovaRequest $request): array
    {
        $bag = $this->bag();
        $resource = $this->resource($request, $bag);

        $fields = $resource->updateFieldsWithinPanels($request, $resource)
            ->applyDependsOnWithDefaultValues($request);

        $fields->each(function (Field $field) use ($request) {
            if ($field instanceof Code && $field->value === 'null') {
                $field->value = null;
            }

            if (is_null($field->value) && method_exists($field, 'resolveDefaultCallback')) {
                $field->value = $field->resolveDefaultCallback($request);
            }
        });

        $panels = $resource->availablePanelsForUpdate($request, $resource, $fields);

        return [
            'panels' => array_values($panels),
            'fields' => $fields->values()->all(),
        ];
    }

    /**
     * Validate the submitted values and persist them.
     */
    public function save(NovaRequest $request): void
    {
        $bag = $this->bag();
        $resource = $this->resource($request, $bag);
        $fields = $resource->updateFieldsWithoutReadonly($request);

        $this->validate($request, $fields);

        DB::transaction(function () use ($request, $bag, $fields) {
            $callbacks = $fields->map->fill($request, $bag)
                ->filter(fn ($callback) => is_callable($callback))
                ->values();

            $this->persist($bag, $fields->map->attribute->all());

            // Deferred file callbacks (pruning the previous file) run after the
            // values are stored, exactly like Nova's resource controllers do.
            $callbacks->each->__invoke();
        });

        $this->manager->flushCache();

        event(new SettingsSaved($fields->map->attribute->all()));
    }

    /**
     * Delete the stored file of a File / Image setting.
     */
    public function deleteFile(NovaRequest $request, string $key): void
    {
        $bag = $this->bag();
        $resource = $this->resource($request, $bag);

        /** @var Field&Deletable $field */
        $field = $resource->updateFields($request)
            ->whereInstanceOf(Deletable::class)
            ->findFieldByAttributeOrFail($key);

        DeleteField::forRequest($request, $field, $bag);

        $this->persist($bag, array_unique([$key, ...array_keys($bag->getDirty())]));

        $this->manager->flushCache();
    }

    public function bag(): SettingsBag
    {
        return SettingsBag::fromSettings(Setting::query()->get());
    }

    public function resource(NovaRequest $request, ?SettingsBag $bag = null): SettingsFormResource
    {
        $bag ??= $this->bag();

        return new SettingsFormResource($bag, fn () => $this->panels());
    }

    /**
     * @return Collection<int, SettingGroup>
     */
    public function groups(): Collection
    {
        return SettingGroup::query()->ordered()->with('settings')->get()
            ->filter(fn (SettingGroup $group) => $group->settings->isNotEmpty())
            ->values();
    }

    /**
     * One Tab::group() with a tab per setting group.
     *
     * @return array<int, TabsGroup>
     */
    protected function panels(): array
    {
        $tabs = $this->groups()->map(function (SettingGroup $group, int $index) {
            $fields = $group->settings
                ->map(fn (Setting $setting) => $this->manager->fieldFor($setting))
                ->filter()
                ->values()
                ->all();

            return Tab::make($group->label, $fields)
                ->withAttribute($group->key)
                ->withPosition($index + 1);
        })->all();

        $group = Tab::group(config('nova-settings.tool.label', 'Settings'), $tabs, 'nova-settings');
        $group->showTitle = false;

        return [$group];
    }

    protected function validate(NovaRequest $request, FieldCollection $fields): void
    {
        $rules = [];
        $attributes = [];

        foreach ($fields as $field) {
            $rules = array_merge($rules, $field->getUpdateRules($request));
            $attributes[$field->validationKey()] = (string) $field->name;
        }

        Validator::make($request->all(), $rules, [], $attributes)->validate();
    }

    /**
     * Copy the given bag attributes into the settings rows.
     *
     * @param  array<int, string>  $keys
     */
    protected function persist(SettingsBag $bag, array $keys): void
    {
        $settings = Setting::query()->whereIn('key', $keys)->get()->keyBy('key');

        foreach ($keys as $key) {
            $setting = $settings->get($key);

            if (! $setting) {
                continue;
            }

            $setting->value = $bag->getAttribute($key);

            if ($setting->isDirty('value')) {
                $setting->save();
            }
        }
    }
}
