<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check()) {
            $user = auth()->user();

            // Ignore tenant scope ONLY if user is SUPER_ADMIN_SYSTEM or has no desa_id (Admin Kecamatan / System)
            if ($user->role === 'SUPER_ADMIN_SYSTEM' || is_null($user->desa_id)) {
                return;
            }

            $builder->where($model->getTable() . '.desa_id', $user->desa_id);
        }
    }
}
