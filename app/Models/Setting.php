<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'settings';

    protected $fillable = [
        'desa_id',
        'key',
        'value',
        'description',
    ];
}
