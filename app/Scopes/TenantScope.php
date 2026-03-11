<?php

namespace App\Scopes;

use App\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->getTable() . '.tenant_id', app(TenantContext::class)->getId());
    }

    public static function addTenantIdOnCreating(Model $model): void
    {
        if (!$model->getAttribute('tenant_id')) {
            $model->setAttribute('tenant_id', app(TenantContext::class)->getId());
        }
    }
}
