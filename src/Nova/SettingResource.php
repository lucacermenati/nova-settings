<?php

namespace LucaCermenati\NovaSettings\Nova;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;
use LucaCermenati\NovaSettings\Casting\ValueCaster;
use LucaCermenati\NovaSettings\Enums\Source;
use LucaCermenati\NovaSettings\Fields\FieldFactory;
use LucaCermenati\NovaSettings\Models\Setting;
use LucaCermenati\NovaSettings\Nova\Filters\SettingGroupFilter;
use LucaCermenati\NovaSettings\Rules\ChoicesNotEmpty;

/**
 * @extends resource<Setting>
 */
class SettingResource extends Resource
{
    public static $model = Setting::class;

    public static $title = 'label';

    public static $search = ['key', 'label'];

    public static $displayInNavigation = false;

    public static $globallySearchable = false;

    public static function label(): string
    {
        return __('Settings');
    }

    public static function singularLabel(): string
    {
        return __('Setting');
    }

    public static function uriKey(): string
    {
        return 'settings';
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->orderBy('setting_group_id')->orderBy('sort_order')->orderBy('id');
    }

    public function fields(NovaRequest $request): array
    {
        $locked = fn () => $this->isLocked();
        $types = (array) config('nova-settings.field_types', []);

        return [
            ID::make()->sortable(),

            BelongsTo::make(__('Group'), 'group', SettingGroupResource::class)
                ->rules('required')
                ->readonly($locked)
                ->sortable(),

            Text::make(__('Key'), 'key')
                ->rules('required', 'alpha_dash', 'max:100')
                ->creationRules('unique:settings,key')
                ->updateRules('unique:settings,key,{{resourceId}}')
                ->readonly($locked)
                ->help(__('Use it in your code: setting(\'your_key\').')),

            Text::make(__('Label'), 'label')
                ->rules('required', 'max:255')
                ->readonly($locked),

            Textarea::make(__('Description'), 'description')
                ->nullable()
                ->alwaysShow()
                ->readonly($locked)
                ->help(__('Shown as help text under the field.')),

            Select::make(__('Field type'), 'type')
                ->options(array_flip($types))
                ->displayUsingLabels()
                ->rules('required', Rule::in(array_values($types)))
                ->readonly($locked)
                ->searchable(),

            KeyValue::make(__('Choices'), 'options->options')
                ->keyLabel(__('Value'))
                ->valueLabel(__('Label'))
                ->actionText(__('Add choice'))
                ->help(__('The value is what gets stored, the label is what the user sees.'))
                ->readonly($locked)
                ->hide()
                ->dependsOn('type', function (KeyValue $field, NovaRequest $request, FormData $formData) {
                    if (FieldFactory::requiresChoices((string) $formData->string('type'))) {
                        $field->show()->rules('required', new ChoicesNotEmpty);
                    }
                })
                ->showOnDetail(fn ($request, $resource) => $resource instanceof Setting && FieldFactory::requiresChoices((string) $resource->type)),

            Number::make(__('Order'), 'sort_order')
                ->rules('nullable', 'integer', 'min:0')
                ->default(0)
                ->readonly($locked)
                ->sortable(),

            Badge::make(__('Source'), 'source')
                ->resolveUsing(fn ($value) => $value instanceof Source ? $value->value : $value)
                ->map(['code' => 'info', 'database' => 'success'])
                ->labels(['code' => __('Defined in code'), 'database' => __('Created in Nova')]),

            Text::make(__('Current value'), fn () => $this->displayValue())
                ->exceptOnForms(),
        ];
    }

    public function filters(NovaRequest $request): array
    {
        return [
            new SettingGroupFilter,
        ];
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return ! $this->isLocked() && parent::authorizedToUpdate($request);
    }

    public function authorizedToDelete(Request $request): bool
    {
        return ! $this->isLocked() && parent::authorizedToDelete($request);
    }

    public function authorizedToReplicate(Request $request): bool
    {
        return false;
    }

    /**
     * Hidden dependent fields are not submitted, so switching a Select to another
     * type would leave its choices behind: drop them whenever the type has no use for them.
     */
    protected static function fillFields(NovaRequest $request, $model, Collection $fields): array
    {
        [$model, $callbacks] = parent::fillFields($request, $model, $fields);

        if ($model instanceof Setting) {
            $options = $model->fieldOptions();

            if (! FieldFactory::requiresChoices((string) $model->type)) {
                unset($options['options']);
            }

            $model->options = $options === [] ? null : $options;
        }

        return [$model, $callbacks];
    }

    protected function isLocked(): bool
    {
        return $this->resource instanceof Setting && $this->resource->exists && $this->resource->isManagedByCode();
    }

    protected function displayValue(): ?string
    {
        if (! $this->resource instanceof Setting || ! $this->resource->exists) {
            return null;
        }

        if (is_a($this->resource->type, Password::class, true)) {
            return $this->resource->value ? '••••••' : null;
        }

        $display = ValueCaster::display($this->resource->type, $this->resource->value, $this->resource->fieldOptions());

        return $display === null ? null : Str::limit($display, 80);
    }
}
