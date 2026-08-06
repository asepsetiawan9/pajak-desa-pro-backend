<?php

namespace App\Repositories;

use App\Models\DhkpRow;
use App\Models\DusunTarget;
use Illuminate\Pagination\LengthAwarePaginator;

class DhkpRepository
{
    public function getFilteredDhkp(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        $query = DhkpRow::query()->with(['kolektor:id,name', 'transaksi:id,nomor_stts']);

        $effectivePerPage = !empty($filters['limit']) ? (int) $filters['limit'] : (!empty($filters['per_page']) ? (int) $filters['per_page'] : $perPage);
        if ($effectivePerPage <= 0) {
            $effectivePerPage = 1000;
        }

        if (!empty($filters['tahun'])) {
            $query->where('tahun', $filters['tahun']);
        }

        if (!empty($filters['dusun']) && $filters['dusun'] !== 'ALL') {
            if (is_array($filters['dusun'])) {
                $query->whereIn('dusun', $filters['dusun']);
            } elseif (str_contains($filters['dusun'], ',')) {
                $dusuns = array_map('trim', explode(',', $filters['dusun']));
                $query->whereIn('dusun', $dusuns);
            } else {
                $query->where('dusun', $filters['dusun']);
            }
        }

        if (!empty($filters['blok']) && $filters['blok'] !== 'ALL') {
            $query->where('blok', $filters['blok']);
        }

        if (!empty($filters['status_bayar']) && $filters['status_bayar'] !== 'ALL') {
            $query->where('status_bayar', $filters['status_bayar']);
        }

        if (!empty($filters['domisili']) && $filters['domisili'] !== 'ALL') {
            $query->where('domisili', $filters['domisili']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nop', 'like', "%{$search}%")
                  ->orWhere('nama_wp', 'like', "%{$search}%")
                  ->orWhere('alamat_wp', 'like', "%{$search}%")
                  ->orWhere('alamat_op', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('id', 'asc')->paginate($effectivePerPage);
    }

    public function findById(int $id): ?DhkpRow
    {
        return DhkpRow::with(['kolektor', 'transaksi'])->find($id);
    }

    public function findByNopAndTahun(string $nop, int $tahun): ?DhkpRow
    {
        return DhkpRow::where('nop', $nop)->where('tahun', $tahun)->first();
    }

    public function getByNopsAndTahun(array $nops, int $tahun)
    {
        return DhkpRow::whereIn('nop', $nops)->where('tahun', $tahun)->get();
    }

    public function create(array $data): DhkpRow
    {
        return DhkpRow::create($data);
    }

    public function update(DhkpRow $dhkpRow, array $data): DhkpRow
    {
        $dhkpRow->update($data);
        return $dhkpRow->fresh();
    }

    public function delete(DhkpRow $dhkpRow): bool
    {
        return $dhkpRow->delete();
    }

    public function getSummaryKPI(int $tahun = 2026, ?string $dusunFilter = null): array
    {
        $query = DhkpRow::where('tahun', $tahun);

        if (!empty($dusunFilter) && $dusunFilter !== 'ALL') {
            $filterDusuns = array_map('trim', explode(',', $dusunFilter));
            $query->whereIn('dusun', $filterDusuns);
        }

        $totalKetetapan = (int) $query->sum('ketetapan_pbb');
        $terbayar = (int) (clone $query)->where('status_bayar', 'LUNAS')->sum('ketetapan_pbb');
        $sisaPiutang = $totalKetetapan - $terbayar;
        $persentase = $totalKetetapan > 0 ? round(($terbayar / $totalKetetapan) * 100, 2) : 0;

        $totalSppt = (clone $query)->count();
        $spptLunas = (clone $query)->where('status_bayar', 'LUNAS')->count();
        $spptBelum = $totalSppt - $spptLunas;

        // Group by Dusun
        $dusuns = (clone $query)->whereNotNull('dusun')->distinct()->pluck('dusun')->filter()->values()->toArray();
        if (empty($dusuns)) {
            $dusuns = ['Balok', 'Cideres', 'Puncak Sari', 'Cipedes'];
            if (!empty($dusunFilter) && $dusunFilter !== 'ALL') {
                $filterDusuns = array_map('trim', explode(',', $dusunFilter));
                $dusuns = array_intersect($dusuns, $filterDusuns);
            }
        }

        $byDusun = [];
        foreach ($dusuns as $dusunName) {
            $dusunQuery = DhkpRow::where('tahun', $tahun)->where('dusun', $dusunName);
            $targetRecord = DusunTarget::where('nama_dusun', $dusunName)->where('tahun', $tahun)->first();
            $ketetapanSum = (int) (clone $dusunQuery)->sum('ketetapan_pbb');
            $target = $targetRecord ? (int) $targetRecord->target_pbb : $ketetapanSum;
            if ($target === 0) {
                $target = $ketetapanSum;
            }

            $realisasi = (int) (clone $dusunQuery)->where('status_bayar', 'LUNAS')->sum('ketetapan_pbb');
            $spptCount = (clone $dusunQuery)->count();
            $dusunLunas = (clone $dusunQuery)->where('status_bayar', 'LUNAS')->count();
            $dusunBelum = $spptCount - $dusunLunas;
            $dusunPersen = $target > 0 ? round(($realisasi / $target) * 100, 2) : 0;

            $byDusun[] = [
                'dusun' => $dusunName,
                'target' => $target,
                'realisasi' => $realisasi,
                'sisa_piutang' => $target - $realisasi,
                'total_sppt' => $spptCount,
                'sppt_lunas' => $dusunLunas,
                'sppt_belum' => $dusunBelum,
                'persentase' => $dusunPersen,
            ];
        }

        // Top Unpaid Priority List
        $topUnpaidQuery = DhkpRow::where('tahun', $tahun)
            ->where('status_bayar', 'BELUM_BAYAR');

        if (!empty($dusunFilter) && $dusunFilter !== 'ALL') {
            $filterDusuns = array_map('trim', explode(',', $dusunFilter));
            $topUnpaidQuery->whereIn('dusun', $filterDusuns);
        }

        $topUnpaid = $topUnpaidQuery
            ->orderBy('ketetapan_pbb', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'nop' => $row->nop,
                    'nama_wp' => $row->nama_wp,
                    'dusun' => $row->dusun,
                    'blok' => $row->blok,
                    'ketetapan_pbb' => $row->ketetapan_pbb,
                    'domisili' => $row->domisili,
                    'status_bayar' => $row->status_bayar,
                ];
            })
            ->values();

        return [
            'tahun' => $tahun,
            'total_ketetapan' => $totalKetetapan,
            'terbayar' => $terbayar,
            'sisa_piutang' => $sisaPiutang,
            'persentase_realisasi' => $persentase,
            'total_sppt' => $totalSppt,
            'sppt_lunas' => $spptLunas,
            'sppt_belum' => $spptBelum,
            'by_dusun' => $byDusun,
            'top_unpaid' => $topUnpaid,
        ];
    }

    public function bulkUpsert(array $rows): array
    {
        $created = 0;
        $updated = 0;

        foreach ($rows as $data) {
            if (empty($data['nop'])) continue;

            $tahun = (int) ($data['tahun'] ?? 2026);
            $ketetapan = (int) ($data['ketetapan_pbb'] ?? 0);
            $denda = (int) ($data['denda'] ?? 0);
            $fee = (int) ($data['fee_kolektor'] ?? (($data['domisili'] ?? '') === 'LUAR_DESA' ? 5000 : 0));
            
            $payload = array_merge([
                'nama_wp' => 'Tanpa Nama',
                'alamat_wp' => '-',
                'alamat_op' => '-',
                'dusun' => 'Balok',
                'blok' => 'Blok 01',
                'rt_rw' => '001/001',
                'luas_bumi' => 100,
                'luas_bangunan' => 0,
                'njop_bumi' => 100000,
                'njop_bangunan' => 0,
                'denda' => 0,
                'fee_kolektor' => $fee,
                'status_bayar' => 'BELUM_BAYAR',
                'domisili' => 'DALAM_DESA',
            ], $data);

            $payload['total_bayar'] = ((int) ($payload['ketetapan_pbb'] ?? 0)) + ((int) ($payload['denda'] ?? 0)) + ((int) ($payload['fee_kolektor'] ?? 0));

            $existing = DhkpRow::where('nop', $payload['nop'])->where('tahun', $tahun)->first();
            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                DhkpRow::create($payload);
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => $created + $updated,
        ];
    }
}
