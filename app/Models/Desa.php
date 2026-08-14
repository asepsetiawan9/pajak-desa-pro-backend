<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Desa extends Model
{
    use HasFactory;

    protected $table = 'desas';

    protected $fillable = [
        'kode_desa',
        'nama_desa',
        'nama_kecamatan',
        'nama_kabupaten',
        'nama_provinsi',
        'nama_kades',
        'nip_kades',
        'subdomain',
        'logo_path',
        'status_aktif',
    ];

    protected function casts(): array
    {
        return [
            'status_aktif' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class, 'desa_id');
    }

    public function dhkpRows()
    {
        return $this->hasMany(DhkpRow::class, 'desa_id');
    }

    public function transactions()
    {
        return $this->hasMany(TransactionRecord::class, 'desa_id');
    }

    public function settings()
    {
        return $this->hasMany(Setting::class, 'desa_id');
    }

    public function dusuns()
    {
        return $this->hasMany(Dusun::class, 'desa_id');
    }
}

