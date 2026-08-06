<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DhkpRow extends Model
{
    use HasFactory;

    protected $table = 'dhkp_rows';

    protected $fillable = [
        'nop',
        'nama_wp',
        'alamat_wp',
        'alamat_op',
        'dusun',
        'blok',
        'rt_rw',
        'luas_bumi',
        'luas_bangunan',
        'njop_bumi',
        'njop_bangunan',
        'ketetapan_pbb',
        'denda',
        'fee_kolektor',
        'total_bayar',
        'status_bayar',
        'domisili',
        'tanggal_bayar',
        'kolektor_id',
        'transaksi_id',
        'tahun',
    ];

    protected function casts(): array
    {
        return [
            'luas_bumi' => 'integer',
            'luas_bangunan' => 'integer',
            'njop_bumi' => 'integer',
            'njop_bangunan' => 'integer',
            'ketetapan_pbb' => 'integer',
            'denda' => 'integer',
            'fee_kolektor' => 'integer',
            'total_bayar' => 'integer',
            'tahun' => 'integer',
            'tanggal_bayar' => 'datetime',
        ];
    }

    public function kolektor()
    {
        return $this->belongsTo(User::class, 'kolektor_id');
    }

    public function transaksi()
    {
        return $this->belongsTo(TransactionRecord::class, 'transaksi_id');
    }
}
