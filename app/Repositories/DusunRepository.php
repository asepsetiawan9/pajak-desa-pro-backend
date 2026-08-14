<?php

namespace App\Repositories;

use App\Models\DhkpRow;
use App\Models\Dusun;
use App\Models\DusunTarget;
use App\Scopes\TenantScope;

class DusunRepository
{
    /**
     * Mengambil daftar master dusun lengkap dengan filter dan pagination/list
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll(array $filters = [])
    {
        $user = auth()->user();
        $isSuperAdmin = $user && (
            $user->role === 'SUPER_ADMIN_SYSTEM' ||
            is_null($user->desa_id)
        );

        $query = $isSuperAdmin
            ? Dusun::withoutGlobalScope(TenantScope::class)->with('desa:id,nama_desa,kode_desa')
            : Dusun::with('desa:id,nama_desa,kode_desa');

        if (!empty($filters['desa_id']) && strtoupper((string)$filters['desa_id']) !== 'ALL' && strtoupper((string)$filters['desa_id']) !== 'SEMUA') {
            $query->where('desa_id', $filters['desa_id']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nama_dusun', 'like', $search)
                  ->orWhere('kode_dusun', 'like', $search)
                  ->orWhere('rt_rw', 'like', $search);
            });
        }

        if (isset($filters['status_aktif']) && $filters['status_aktif'] !== '') {
            $query->where('status_aktif', filter_var($filters['status_aktif'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('desa_id')->orderBy('nama_dusun')->get();
    }

    /**
     * Cari dusun berdasarkan ID
     */
    public function findById(int $id): ?Dusun
    {
        $user = auth()->user();
        $isSuperAdmin = $user && (
            $user->role === 'SUPER_ADMIN_SYSTEM' ||
            is_null($user->desa_id)
        );

        $query = $isSuperAdmin
            ? Dusun::withoutGlobalScope(TenantScope::class)->with('desa:id,nama_desa,kode_desa')
            : Dusun::with('desa:id,nama_desa,kode_desa');

        return $query->find($id);
    }

    /**
     * Cari dusun berdasarkan nama dan desa_id
     */
    public function findByNameAndDesa(string $name, int $desaId): ?Dusun
    {
        return Dusun::withoutGlobalScope(TenantScope::class)
            ->where('desa_id', $desaId)
            ->whereRaw('LOWER(TRIM(nama_dusun)) = ?', [strtolower(trim($name))])
            ->first();
    }

    /**
     * Buat data master dusun baru
     */
    public function create(array $data): Dusun
    {
        return Dusun::create($data);
    }

    /**
     * Update data master dusun
     */
    public function update(Dusun $dusun, array $data): Dusun
    {
        $dusun->update($data);
        return $dusun->fresh(['desa:id,nama_desa,kode_desa']);
    }

    /**
     * Hapus data dusun
     */
    public function delete(Dusun $dusun): bool
    {
        return $dusun->delete();
    }

    /**
     * Mengambil daftar nama dusun unik untuk desa tertentu atau seluruh desa jika Super Admin tanpa filter.
     * Menggabungkan data dari tabel Master Dusun (prioritas) + fallback dari DHKP Rows & Dusun Target.
     *
     * @param int|string|null $desaId
     * @return array<string>
     */
    public function getUniqueDusunsByDesa($desaId = null): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user && (
            $user->role === 'SUPER_ADMIN_SYSTEM' ||
            is_null($user->desa_id)
        );

        // 1. Query dari Tabel Master Dusuns (Prioritas)
        $masterQuery = $isSuperAdmin
            ? Dusun::withoutGlobalScope(TenantScope::class)->where('status_aktif', true)
            : Dusun::where('status_aktif', true);

        if (!empty($desaId) && strtoupper((string)$desaId) !== 'ALL' && strtoupper((string)$desaId) !== 'SEMUA') {
            $masterQuery->where('desa_id', $desaId);
        }

        $masterDusuns = $masterQuery->pluck('nama_dusun')->toArray();

        // 2. Query dari DHKP Rows (Fallback/Kompatibilitas)
        $dhkpQuery = $isSuperAdmin
            ? DhkpRow::withoutGlobalScope(TenantScope::class)->whereNotNull('dusun')->where('dusun', '!=', '')
            : DhkpRow::whereNotNull('dusun')->where('dusun', '!=', '');

        if (!empty($desaId) && strtoupper((string)$desaId) !== 'ALL' && strtoupper((string)$desaId) !== 'SEMUA') {
            $dhkpQuery->where('desa_id', $desaId);
        }

        $dhkpDusuns = $dhkpQuery->distinct()->pluck('dusun')->toArray();

        // 3. Query dari Dusun Target
        $targetQuery = $isSuperAdmin
            ? DusunTarget::withoutGlobalScope(TenantScope::class)->whereNotNull('nama_dusun')->where('nama_dusun', '!=', '')
            : DusunTarget::whereNotNull('nama_dusun')->where('nama_dusun', '!=', '');

        if (!empty($desaId) && strtoupper((string)$desaId) !== 'ALL' && strtoupper((string)$desaId) !== 'SEMUA') {
            $targetQuery->where('desa_id', $desaId);
        }

        $targetDusuns = $targetQuery->distinct()->pluck('nama_dusun')->toArray();

        // 4. Gabungkan dan bersihkan duplikasi nama dusun
        $merged = array_merge($masterDusuns, $dhkpDusuns, $targetDusuns);
        $normalized = [];

        foreach ($merged as $d) {
            $clean = trim((string)$d);
            if ($clean !== '') {
                $normalized[strtoupper($clean)] = $clean;
            }
        }

        $result = array_values($normalized);
        sort($result, SORT_NATURAL | SORT_FLAG_CASE);

        return $result;
    }
}
