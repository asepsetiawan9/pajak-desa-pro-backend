<?php

namespace App\Traits;

use App\Models\Desa;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            if (!$model->desa_id && auth()->check() && auth()->user()->desa_id) {
                $model->desa_id = auth()->user()->desa_id;
            }
        });
    }

    public function desa()
    {
        return $this->belongsTo(Desa::class, 'desa_id');
    }
}
