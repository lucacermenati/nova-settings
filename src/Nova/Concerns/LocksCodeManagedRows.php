<?php

namespace LucaCermenati\NovaSettings\Nova\Concerns;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use LucaCermenati\NovaSettings\Enums\Source;

/**
 * Rows still defined in code are read-only in the Nova resources: they can be
 * viewed but not updated, deleted or replicated.
 */
trait LocksCodeManagedRows
{
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

    protected function isLocked(): bool
    {
        return $this->resource instanceof static::$model
            && $this->resource->exists
            && $this->resource->isManagedByCode();
    }

    protected function sourceBadge(): Badge
    {
        return Badge::make(__('Source'), 'source')
            ->resolveUsing(fn ($value) => $value instanceof Source ? $value->value : $value)
            ->map(['code' => 'info', 'database' => 'success'])
            ->labels(['code' => __('Defined in code'), 'database' => __('Created in Nova')]);
    }
}
