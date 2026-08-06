<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DusunTarget extends Model
{
    use HasFactory;

    protected $table = 'dusun_targets';

    protected $fillable = [
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
