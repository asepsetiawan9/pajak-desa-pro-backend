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

            // Ignore tenant scope if user is SUPER_ADMIN_SYSTEM, SUPER_ADMIN, or has no desa_id
            if ($user->role === 'SUPER_ADMIN_SYSTEM' || $user->role === 'SUPER_ADMIN' || is_null($user->desa_id)) {
                return;
            }

            $builder->where($model->getTable() . '.desa_id', $user->desa_id);
        }
    }
}
