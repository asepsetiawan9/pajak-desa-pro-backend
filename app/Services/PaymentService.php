<?php

namespace App\Services;

use App\Models\DhkpRow;
use App\Models\TransactionRecord;
use App\Repositories\DhkpRepository;
use App\Repositories\SettingRepository;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        protected TransactionRepository $transactionRepository,
        protected DhkpRepository $dhkpRepository,
        protected SettingRepository $settingRepository
    ) {}

    /**
     * Memproses Pembayaran Multi-NOP Kasir STTS secara Atomic & Safe Lock
     */
    public function processPayment(array $payload, int $operatorId): TransactionRecord
    {
        $nops = $payload['nops'] ?? [];
        if (empty($nops) && !empty($payload['items']) && is_array($payload['items'])) {
            $nops = array_filter(array_map(fn($item) => is_array($item) ? ($item['nop'] ?? null) : null, $payload['items']));
        }

        $tahun = $payload['tahun'] ?? 2026;
        $metode = strtoupper($payload['metode_pembayaran'] ?? $payload['metode'] ?? 'CASH');
        if ($metode === 'TUNAI') {
            $metode = 'CASH';
        }

        $metadataKk = $payload['metadata_kk'] ?? [
            'uang_dibayar' => $payload['uangDibayar'] ?? null,
            'kembalian' => $payload['kembalian'] ?? null,
            'petugas' => $payload['petugas'] ?? null,
        ];

        if (empty($nops)) {
            throw ValidationException::withMessages(['nops' => 'Daftar NOP pembayaran tidak boleh kosong.']);
        }

        $feePerLuarDesa = (int) $this->settingRepository->getByKey('fee_kolektor_luar_desa', 5000);

        return DB::transaction(function () use ($nops, $tahun, $metode, $operatorId, $metadataKk, $feePerLuarDesa) {
            // Lock rows for update to prevent concurrent double-payments
            $rows = DhkpRow::whereIn('nop', $nops)
                ->where('tahun', $tahun)
                ->lockForUpdate()
                ->get();

            if ($rows->count() !== count($nops)) {
                throw ValidationException::withMessages(['nops' => 'Beberapa NOP yang Anda pilih tidak ditemukan pada tahun ketetapan ini.']);
            }

            // Check if any NOP is already paid
            foreach ($rows as $row) {
                if ($row->status_bayar === 'LUNAS') {
                    throw ValidationException::withMessages([
                        'nops' => "NOP {$row->nop} ({$row->nama_wp}) SUDAH LUNAS sebelumnya. Transaksi dibatalkan."
                    ]);
                }
            }

            $totalPokok = 0;
            $totalDenda = 0;
            $totalFee = 0;

            // Generate nomor STTS unik: STTS-YYYYMMDD-XXXX
            $datePrefix = date('Ymd');
            $randomSuffix = strtoupper(substr(md5(uniqid()), 0, 4));
            $nomorStts = "STTS-{$datePrefix}-{$randomSuffix}";

            // Hitung kalkulasi
            foreach ($rows as $row) {
                $pokok = (int) $row->ketetapan_pbb;
                $denda = (int) $row->denda;
                $fee = $row->domisili === 'LUAR_DESA' ? $feePerLuarDesa : 0;

                $totalPokok += $pokok;
                $totalDenda += $denda;
                $totalFee += $fee;
            }

            $totalBayar = $totalPokok + $totalDenda + $totalFee;

            // Buat transaksi
            $transaction = $this->transactionRepository->create([
                'nomor_stts' => $nomorStts,
                'tanggal_transaksi' => now(),
                'operator_id' => $operatorId,
                'total_pokok' => $totalPokok,
                'total_denda' => $totalDenda,
                'total_fee' => $totalFee,
                'total_bayar' => $totalBayar,
                'metode_pembayaran' => $metode,
                'status_void' => false,
                'metadata_kk' => $metadataKk,
            ]);

            // Update status bayar DHKP Rows
            foreach ($rows as $row) {
                $fee = $row->domisili === 'LUAR_DESA' ? $feePerLuarDesa : 0;
                $row->update([
                    'status_bayar' => 'LUNAS',
                    'tanggal_bayar' => now(),
                    'fee_kolektor' => $fee,
                    'total_bayar' => $row->ketetapan_pbb + $row->denda + $fee,
                    'kolektor_id' => $operatorId,
                    'transaksi_id' => $transaction->id,
                ]);
            }

            return $transaction->fresh(['operator', 'dhkpRows']);
        });
    }

    /**
     * Pembatalan / Void Transaksi STTS dengan Auto-Rollback Status DHKP
     */
    public function voidTransaction(int $transactionId, string $reason, int $userId): TransactionRecord
    {
        return DB::transaction(function () use ($transactionId, $reason, $userId) {
            $transaction = TransactionRecord::where('id', $transactionId)->lockForUpdate()->first();

            if (!$transaction) {
                throw ValidationException::withMessages(['transaction' => 'Transaksi tidak ditemukan.']);
            }

            if ($transaction->status_void) {
                throw ValidationException::withMessages(['transaction' => 'Transaksi ini sudah pernah di-void sebelumnya.']);
            }

            // Rollback all associated DHKP rows
            DhkpRow::where('transaksi_id', $transaction->id)->update([
                'status_bayar' => 'BELUM_BAYAR',
                'tanggal_bayar' => null,
                'fee_kolektor' => 0,
                'total_bayar' => DB::raw('ketetapan_pbb + denda'),
                'kolektor_id' => null,
                'transaksi_id' => null,
            ]);

            // Mark transaction as voided
            $transaction->update([
                'status_void' => true,
                'void_reason' => $reason,
                'void_at' => now(),
                'void_by' => $userId,
            ]);

            return $transaction->fresh(['operator', 'voidUser', 'dhkpRows']);
        });
    }
}
