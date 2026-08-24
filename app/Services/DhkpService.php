<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DhkpRow;
use App\Models\User;
use App\Repositories\DhkpRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DhkpService
{
    public function __construct(protected DhkpRepository $dhkpRepository) {}

    public function getPaginated(array $filters)
    {
        return $this->dhkpRepository->getFilteredDhkp($filters);
    }

    public function getDetail(int $id): DhkpRow
    {
        $dhkp = $this->dhkpRepository->findById($id);
        if (!$dhkp) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(DhkpRow::class, [$id]);
        }
        return $dhkp;
    }

    public function getKpiSummary(int $tahun = 2026, ?string $dusunFilter = null, ?int $desaId = null): array
    {
        return $this->dhkpRepository->getSummaryKPI($tahun, $dusunFilter, $desaId);
    }

    public function createSppt(array $data): DhkpRow
    {
        // Hitung total bayar = ketetapan + denda + fee_kolektor
        $data['total_bayar'] = ($data['ketetapan_pbb'] ?? 0) + ($data['denda'] ?? 0) + ($data['fee_kolektor'] ?? 0);
        $dhkp = $this->dhkpRepository->create($data);

        $user = auth()->user();
        AuditLog::create([
            'user_id' => $user?->id,
            'action' => 'CREATE_DHKP',
            'module' => 'DHKP',
            'payload' => [
                'dhkp_id' => $dhkp->id,
                'nop' => $dhkp->nop,
                'nama_wp' => $dhkp->nama_wp,
                'desa_id' => $dhkp->desa_id,
            ],
            'ip_address' => request()->ip(),
        ]);

        return $dhkp;
    }

    public function updateSppt(int $id, array $data): DhkpRow
    {
        $dhkp = $this->getDetail($id);
        if (isset($data['ketetapan_pbb']) || isset($data['denda']) || isset($data['fee_kolektor'])) {
            $ketetapan = $data['ketetapan_pbb'] ?? $dhkp->ketetapan_pbb;
            $denda = $data['denda'] ?? $dhkp->denda;
            $fee = $data['fee_kolektor'] ?? $dhkp->fee_kolektor;
            $data['total_bayar'] = $ketetapan + $denda + $fee;
        }
        $updated = $this->dhkpRepository->update($dhkp, $data);

        $user = auth()->user();
        AuditLog::create([
            'user_id' => $user?->id,
            'action' => 'UPDATE_DHKP',
            'module' => 'DHKP',
            'payload' => [
                'dhkp_id' => $updated->id,
                'nop' => $updated->nop,
                'status_bayar' => $updated->status_bayar,
                'desa_id' => $updated->desa_id,
            ],
            'ip_address' => request()->ip(),
        ]);

        return $updated;
    }

    public function deleteSppt(int $id): bool
    {
        $dhkp = $this->getDetail($id);
        $user = auth()->user();
        
        AuditLog::create([
            'user_id' => $user?->id,
            'action' => 'DELETE_DHKP',
            'module' => 'DHKP',
            'payload' => [
                'dhkp_id' => $dhkp->id,
                'nop' => $dhkp->nop,
                'nama_wp' => $dhkp->nama_wp,
                'desa_id' => $dhkp->desa_id,
            ],
            'ip_address' => request()->ip(),
        ]);

        return $this->dhkpRepository->delete($dhkp);
    }

    public function importSppt(array $rows): array
    {
        $result = $this->dhkpRepository->bulkUpsert($rows);

        $user = auth()->user();
        AuditLog::create([
            'user_id' => $user?->id,
            'action' => 'IMPORT_DHKP',
            'module' => 'DHKP',
            'payload' => [
                'total_imported' => count($rows),
                'desa_id' => $user?->desa_id,
            ],
            'ip_address' => request()->ip(),
        ]);

        return $result;
    }

    /**
     * Preview: Menghitung data yang akan dihapus tanpa melakukan penghapusan.
     */
    public function previewReset(int $tahun, int $desaId): array
    {
        return $this->dhkpRepository->countByTahunDesa($tahun, $desaId);
    }

    /**
     * Reset/Hapus massal data DHKP berdasarkan tahun pajak & desa.
     * Dilindungi oleh: role check + password verification + DB transaction + audit log.
     */
    public function resetDhkpByTahunDesa(int $tahun, int $desaId, string $password, User $executor): array
    {
        // SECURITY: Verifikasi role — hanya SUPER_ADMIN_SYSTEM
        if ($executor->role !== 'SUPER_ADMIN_SYSTEM') {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Hanya Super Admin System yang diizinkan melakukan reset data DHKP.'
            );
        }

        // SECURITY: Verifikasi password
        if (!Hash::check($password, $executor->password)) {
            throw new \Illuminate\Validation\ValidationException(
                \Illuminate\Support\Facades\Validator::make([], []),
                response()->json([
                    'success' => false,
                    'message' => 'Password yang Anda masukkan salah. Operasi dibatalkan.',
                ], 403)
            );
        }

        // Preview dulu untuk audit log
        $preview = $this->dhkpRepository->countByTahunDesa($tahun, $desaId);

        if ($preview['total_dhkp'] === 0) {
            return [
                'deleted_dhkp' => 0,
                'deleted_transactions' => 0,
                'message' => 'Tidak ada data DHKP yang ditemukan untuk tahun dan desa yang dipilih.',
            ];
        }

        // Eksekusi penghapusan dalam DB Transaction
        $result = DB::transaction(function () use ($tahun, $desaId, $executor, $preview) {
            $deleteResult = $this->dhkpRepository->resetByTahunDesa($tahun, $desaId);

            // Catat di Audit Log
            AuditLog::create([
                'user_id' => $executor->id,
                'action' => 'RESET_DHKP_MASSAL',
                'module' => 'DHKP',
                'payload' => [
                    'tahun' => $tahun,
                    'desa_id' => $desaId,
                    'deleted_dhkp' => $deleteResult['deleted_dhkp'],
                    'deleted_transactions' => $deleteResult['deleted_transactions'],
                    'preview_total_ketetapan' => $preview['total_ketetapan'],
                    'preview_sppt_lunas' => $preview['sppt_lunas'],
                    'preview_sppt_belum' => $preview['sppt_belum'],
                    'executor_name' => $executor->name,
                    'executor_username' => $executor->username,
                ],
                'ip_address' => request()->ip(),
            ]);

            return $deleteResult;
        });

        return [
            'deleted_dhkp' => $result['deleted_dhkp'],
            'deleted_transactions' => $result['deleted_transactions'],
            'message' => "Berhasil menghapus {$result['deleted_dhkp']} data DHKP dan {$result['deleted_transactions']} transaksi terkait.",
        ];
    }
}

