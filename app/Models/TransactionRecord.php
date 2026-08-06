<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionRecord extends Model
{
    use HasFactory;

    protected $table = 'transactions';

    protected $fillable = [
        'nomor_stts',
        'tanggal_transaksi',
        'operator_id',
        'total_pokok',
        'total_denda',
        'total_fee',
        'total_bayar',
        'metode_pembayaran',
        'status_void',
        'void_reason',
        'void_at',
        'void_by',
        'metadata_kk',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_transaksi' => 'datetime',
            'void_at' => 'datetime',
            'status_void' => 'boolean',
            'metadata_kk' => 'array',
            'total_pokok' => 'integer',
            'total_denda' => 'integer',
            'total_fee' => 'integer',
            'total_bayar' => 'integer',
        ];
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function voidUser()
    {
        return $this->belongsTo(User::class, 'void_by');
    }

    public function dhkpRows()
    {
        return $this->hasMany(DhkpRow::class, 'transaksi_id');
    }
}
