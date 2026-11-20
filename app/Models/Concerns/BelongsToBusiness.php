<?php

namespace App\Models\Concerns;

use App\Support\Tenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToBusiness
{
    protected static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $query) {
            $id = app(Tenant::class)->id();
            $id ? $query->where($query->getModel()->qualifyColumn('business_id'), $id) : $query->whereRaw('1 = 0');
        });
        static::creating(function (Model $model) {
            $id = app(Tenant::class)->id();
            if (! $id || ($model->business_id && (int) $model->business_id !== $id)) {
                throw new AuthorizationException('A valid studio context is required.');
            }
            $model->business_id = $id;
        });
        static::updating(function (Model $model) {
            if ($model->isDirty('business_id') || (int) $model->business_id !== app(Tenant::class)->id()) {
                throw new AuthorizationException('Studio ownership cannot be changed.');
            }
        });
        static::deleting(function (Model $model) {
            if ((int) $model->business_id !== app(Tenant::class)->id()) {
                throw new AuthorizationException;
            }
        });
    }
}
