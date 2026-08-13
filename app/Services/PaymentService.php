<?php

namespace App\Services;

use App\Models\AuditLog;
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
        // Extract NOPs from various payload formats
        $nops = $payload['nops'] ?? [];
        if (is_string($nops)) {
            $nops = [$nops];
        }
        if (empty($nops) && !empty($payload['items']) && is_array($payload['items'])) {
            $nops = array_filter(array_map(fn($item) => is_array($item) ? ($item['nop'] ?? null) : null, $payload['items']));
        }
        if (empty($nops) && !empty($payload['nop'])) {
            $nops = [$payload['nop']];
        }
        $nops = array_values(array_filter(array_map('trim', (array) $nops)));

        // Extract DHKP IDs from various payload formats
        $dhkpIds = $payload['dhkp_ids'] ?? [];
        if (is_numeric($dhkpIds) || is_string($dhkpIds)) {
            $dhkpIds = [(int) $dhkpIds];
        }
        if (empty($dhkpIds) && !empty($payload['dhkp_id'])) {
            $dhkpIds = [(int) $payload['dhkp_id']];
        }
        if (empty($dhkpIds) && !empty($payload['id'])) {
            $dhkpIds = [(int) $payload['id']];
        }
        if (empty($dhkpIds) && !empty($payload['items']) && is_array($payload['items'])) {
            $dhkpIds = array_values(array_filter(array_map(fn($item) => is_array($item) ? (int) ($item['dhkpId'] ?? $item['dhkp_id'] ?? $item['id'] ?? 0) : 0, $payload['items'])));
        }
        $dhkpIds = array_values(array_filter($dhkpIds, fn($id) => $id > 0));

        if (empty($nops) && empty($dhkpIds)) {
            throw ValidationException::withMessages(['nops' => 'Daftar NOP atau ID DHKP pembayaran tidak boleh kosong.']);
        }

        // Determine explicit tahun if provided (do not force hardcoded fallback that breaks query)
        $tahun = isset($payload['tahun']) ? (int) $payload['tahun'] : null;
        if (!$tahun && !empty($payload['items']) && is_array($payload['items'])) {
            foreach ($payload['items'] as $item) {
                if (is_array($item) && !empty($item['tahun'])) {
                    $tahun = (int) $item['tahun'];
                    break;
                }
            }
        }

        $metode = strtoupper($payload['metode_pembayaran'] ?? $payload['metode'] ?? 'CASH');
        if ($metode === 'TUNAI') {
            $metode = 'CASH';
        }

        $metadataKk = $payload['metadata_kk'] ?? [
            'uang_dibayar' => $payload['uangDibayar'] ?? $payload['uang_dibayar'] ?? null,
            'kembalian' => $payload['kembalian'] ?? null,
            'petugas' => $payload['petugas'] ?? null,
        ];

        $enableFeeLuarDesa = filter_var(
            $this->settingRepository->getByKey('enable_fee_kolektor_luar_desa', true),
            FILTER_VALIDATE_BOOLEAN
        );
        $feePerLuarDesa = $enableFeeLuarDesa
            ? (int) $this->settingRepository->getByKey('fee_kolektor_luar_desa', 5000)
            : 0;

        return DB::transaction(function () use ($nops, $dhkpIds, $tahun, $metode, $operatorId, $metadataKk, $feePerLuarDesa) {
            // Lock rows for update to prevent concurrent double-payments
            $query = DhkpRow::query()->lockForUpdate();

            if (!empty($dhkpIds)) {
                $query->whereIn('id', $dhkpIds);
            } else {
                $query->whereIn('nop', $nops);
            }

            if ($tahun !== null) {
                $query->where('tahun', $tahun);
            }

            $rows = $query->get();
            $expectedCount = !empty($dhkpIds) ? count($dhkpIds) : count($nops);

            // Fallback A: If year filter caused missing rows, query without year constraint
            if ($rows->count() !== $expectedCount && $tahun !== null) {
                $fallbackQuery = DhkpRow::query()->lockForUpdate();
                if (!empty($dhkpIds)) {
                    $fallbackQuery->whereIn('id', $dhkpIds);
                } else {
                    $fallbackQuery->whereIn('nop', $nops);
                }
                $fallbackRows = $fallbackQuery->get();
                if ($fallbackRows->count() === $expectedCount) {
                    $rows = $fallbackRows;
                }
            }

            // Fallback B: If searching by dhkp_id failed, try searching by nops if available
            if ($rows->count() !== $expectedCount && !empty($nops)) {
                $nopQuery = DhkpRow::whereIn('nop', $nops)->lockForUpdate();
                if ($tahun !== null) {
                    $nopQuery->where('tahun', $tahun);
                }
                $nopRows = $nopQuery->get();
                if ($nopRows->count() === count($nops)) {
                    $rows = $nopRows;
                } else {
                    $nopFallbackRows = DhkpRow::whereIn('nop', $nops)->lockForUpdate()->get();
                    if ($nopFallbackRows->count() === count($nops)) {
                        $rows = $nopFallbackRows;
                    }
                }
            }

            if ($rows->count() === 0) {
                throw ValidationException::withMessages(['nops' => 'NOP atau data DHKP yang Anda pilih tidak ditemukan pada database.']);
            }

            if ($rows->count() !== $expectedCount) {
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
            $firstRow = $rows->first();
            $desaId = $firstRow ? $firstRow->desa_id : (auth()->check() ? auth()->user()->desa_id : null);

            // Buat transaksi
            $transaction = $this->transactionRepository->create([
                'desa_id' => $desaId,
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

            AuditLog::create([
                'desa_id' => $desaId,
                'user_id' => $operatorId,
                'action' => 'PAYMENT_CREATED',
                'module' => 'TRANSACTION',
                'payload' => [
                    'nomor_stts' => $nomorStts,
                    'total_bayar' => $totalBayar,
                    'metode' => $metode,
                    'jumlah_nop' => $rows->count(),
                    'nops' => $rows->pluck('nop')->toArray(),
                ],
                'ip_address' => request()->ip(),
            ]);

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

            AuditLog::create([
                'desa_id' => $transaction->desa_id,
                'user_id' => $userId,
                'action' => 'TRANSACTION_VOIDED',
                'module' => 'TRANSACTION',
                'payload' => [
                    'nomor_stts' => $transaction->nomor_stts,
                    'reason' => $reason,
                    'total_bayar' => $transaction->total_bayar,
                ],
                'ip_address' => request()->ip(),
            ]);

            return $transaction->fresh(['operator', 'voidUser', 'dhkpRows']);
        });
    }
}
