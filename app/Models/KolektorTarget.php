<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KolektorTarget extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'kolektor_targets';

    protected $fillable = [
        'desa_id',
        'kolektor_id',
        'tahun',
        'target_nominal',
        'target_sppt',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'target_nominal' => 'integer',
            'target_sppt' => 'integer',
        ];
    }

    public function kolektor()
    {
        return $this->belongsTo(User::class, 'kolektor_id');
    }

    public function desa()
    {
        return $this->belongsTo(Desa::class, 'desa_id');
    }
}
