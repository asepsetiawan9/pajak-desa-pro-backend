<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SetoranKecamatan extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'setoran_kecamatans';

    protected $fillable = [
        'desa_id',
        'nomor_bukti',
        'tanggal_setor',
        'tahun',
        'nominal',
        'metode_setoran',
        'bank_tujuan',
        'nomor_referensi',
        'penyetor_nama',
        'penyetor_jabatan',
        'penerima_kecamatan',
        'catatan_desa',
        'bukti_file',
        'status',
        'tanggal_diterima',
        'penerima_user_id',
        'catatan_kecamatan',
        'created_by',
    ];

    protected $casts = [
        'tanggal_setor' => 'date:Y-m-d',
        'tanggal_diterima' => 'datetime',
        'tahun' => 'integer',
        'nominal' => 'float',
        'desa_id' => 'integer',
        'penerima_user_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function desa(): BelongsTo
    {
        return $this->belongsTo(Desa::class, 'desa_id');
    }

    public function penerimaUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penerima_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
