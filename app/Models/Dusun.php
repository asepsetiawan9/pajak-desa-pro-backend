<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dusun extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'dusuns';

    protected $fillable = [
        'desa_id',
        'nama_dusun',
        'kode_dusun',
        'rt_rw',
        'status_aktif',
    ];

    protected function casts(): array
    {
        return [
            'status_aktif' => 'boolean',
        ];
    }

    public function desa()
    {
        return $this->belongsTo(Desa::class, 'desa_id');
    }
}
