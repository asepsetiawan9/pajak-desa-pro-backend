<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DusunTarget extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'dusun_targets';

    protected $fillable = [
        'desa_id',
        'nama_dusun',
        'tahun',
        'target_pbb',
        'realisasi_pbb',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'target_pbb' => 'integer',
            'realisasi_pbb' => 'integer',
        ];
    }
}
