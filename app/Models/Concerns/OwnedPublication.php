<?php

namespace App\Models\Concerns;

use App\Support\Tenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Platform posts and tenant posts share a table; all unscoped reads must be deliberate. */
trait OwnedPublication
{
    protected static function bootOwnedPublication(): void
    {
        static::addGlobalScope('business', function (Builder $q) {
            $id = app(Tenant::class)->id();
            $id ? $q->where($q->getModel()->qualifyColumn('business_id'), $id) : $q->whereRaw('1 = 0');
        });
        static::creating(function (Model $m) {
            $id = app(Tenant::class)->id();
            if ($id) {
                if ($m->business_id && (int) $m->business_id !== $id) {
                    throw new AuthorizationException;
                }
                $m->business_id = $id;
            } elseif (! auth()->user()?->isAdmin() || $m->business_id !== null) {
                throw new AuthorizationException('A studio or platform publishing context is required.');
            }
        });
        static::updating(function (Model $m) {
            if ($m->isDirty('business_id')) {
                throw new AuthorizationException('The publisher cannot be changed.');
            }
            if (! auth()->user()?->isAdmin() && (int) $m->business_id !== app(Tenant::class)->id()) {
                throw new AuthorizationException;
            }
        });
        static::deleting(function (Model $m) {
            if (! auth()->user()?->isAdmin() && (int) $m->business_id !== app(Tenant::class)->id()) {
                throw new AuthorizationException;
            }
        });
    }
}
