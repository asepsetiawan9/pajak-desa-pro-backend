<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DhkpRow;
use App\Repositories\DhkpRepository;

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
}
