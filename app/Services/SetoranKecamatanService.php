<?php

namespace App\Services;

use App\Models\Desa;
use App\Models\SetoranKecamatan;
use App\Repositories\SetoranKecamatanRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class SetoranKecamatanService
{
    public function __construct(
        protected SetoranKecamatanRepository $repository
    ) {}

    public function getFilteredSetoran(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getFilteredData($filters, $perPage);
    }

    public function getSummary(array $filters): array
    {
        return $this->repository->getKpiSummary($filters);
    }

    public function getById(int $id): ?SetoranKecamatan
    {
        return $this->repository->findById($id);
    }

    public function createSetoran(array $data): SetoranKecamatan
    {
        $user = auth()->user();
        $desaId = $data['desa_id'] ?? ($user ? $user->desa_id : null);

        // Fetch desa code for custom nomor_bukti
        $kodeDesa = 'DESA';
        if ($desaId) {
            $desa = Desa::find($desaId);
            if ($desa) {
                $kodeDesa = $desa->kode_desa;
            }
        }

        // Auto generate nomor_bukti if missing
        if (empty($data['nomor_bukti'])) {
            $datePrefix = date('Ymd', strtotime($data['tanggal_setor'] ?? now()));
            $randomSuffix = strtoupper(Str::random(4));
            $data['nomor_bukti'] = "STR/{$kodeDesa}/{$datePrefix}/{$randomSuffix}";
        }

        if (empty($data['desa_id']) && $desaId) {
            $data['desa_id'] = $desaId;
        }

        $data['status'] = $data['status'] ?? 'PENDING';

        return $this->repository->create($data);
    }

    public function updateSetoran(int $id, array $data): SetoranKecamatan
    {
        $setoran = $this->repository->findById($id);
        if (!$setoran) {
            throw new \Exception("Data setoran tidak ditemukan.");
        }

        // Only allow update if pending or user is super admin
        $user = auth()->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || is_null($user->desa_id));

        if ($setoran->status !== 'PENDING' && !$isSuperAdmin) {
            throw new \Exception("Setoran yang sudah diproses oleh Kecamatan tidak dapat diubah.");
        }

        $this->repository->update($setoran, $data);
        return $setoran->fresh(['desa', 'penerimaUser', 'creator']);
    }

    public function verifySetoran(int $id, string $status, ?string $catatanKecamatan, ?string $tanggalDiterima): SetoranKecamatan
    {
        $setoran = $this->repository->findById($id);
        if (!$setoran) {
            throw new \Exception("Data setoran tidak ditemukan.");
        }

        if (!in_array($status, ['DITERIMA', 'DITOLAK', 'PENDING'])) {
            throw new \Exception("Status verifikasi tidak valid.");
        }

        $this->repository->verify($setoran, $status, $catatanKecamatan, $tanggalDiterima);
        return $setoran->fresh(['desa', 'penerimaUser', 'creator']);
    }

    public function deleteSetoran(int $id): bool
    {
        $setoran = $this->repository->findById($id);
        if (!$setoran) {
            throw new \Exception("Data setoran tidak ditemukan.");
        }

        $user = auth()->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || is_null($user->desa_id));

        if ($setoran->status !== 'PENDING' && !$isSuperAdmin) {
            throw new \Exception("Setoran yang sudah disetujui/ditolak tidak dapat dihapus.");
        }

        return $this->repository->delete($setoran);
    }
}
