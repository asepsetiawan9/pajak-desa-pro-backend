<?php

namespace App\Services;

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
        return $this->dhkpRepository->create($data);
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
        return $this->dhkpRepository->update($dhkp, $data);
    }

    public function deleteSppt(int $id): bool
    {
        $dhkp = $this->getDetail($id);
        return $this->dhkpRepository->delete($dhkp);
    }

    public function importSppt(array $rows): array
    {
        return $this->dhkpRepository->bulkUpsert($rows);
    }
}
