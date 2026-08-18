<?php

namespace App\Repositories;

use App\Models\Desa;
use App\Models\DhkpRow;
use App\Models\DusunTarget;
use App\Scopes\TenantScope;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DhkpRepository
{
    public function __construct(protected SettingRepository $settingRepository) {}
    public function getFilteredDhkp(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        $user = auth()->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || is_null($user->desa_id));

        $query = $isSuperAdmin
            ? DhkpRow::withoutGlobalScope(TenantScope::class)->with(['desa:id,nama_desa,kode_desa', 'kolektor:id,name', 'transaksi:id,nomor_stts'])
            : DhkpRow::query()->with(['desa:id,nama_desa,kode_desa', 'kolektor:id,name', 'transaksi:id,nomor_stts']);

        $effectivePerPage = !empty($filters['limit']) ? (int) $filters['limit'] : (!empty($filters['per_page']) ? (int) $filters['per_page'] : $perPage);
        if ($effectivePerPage <= 0) {
            $effectivePerPage = 1000;
        }

        if ($isSuperAdmin) {
            if (!empty($filters['desa_id']) && $filters['desa_id'] !== 'ALL' && $filters['desa_id'] !== 'all') {
                $query->where('desa_id', $filters['desa_id']);
            }
        }

        if (!empty($filters['tahun'])) {
            $query->where('tahun', $filters['tahun']);
        }

        if (!empty($filters['dusun']) && $filters['dusun'] !== 'ALL') {
            $dusuns = is_array($filters['dusun'])
                ? $filters['dusun']
                : array_map('trim', explode(',', $filters['dusun']));

            $query->where(function ($q) use ($dusuns) {
                foreach ($dusuns as $index => $d) {
                    if ($index === 0) {
                        $q->where('dusun', 'LIKE', $d);
                    } else {
                        $q->orWhere('dusun', 'LIKE', $d);
                    }
                }
            });
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

    public function syncTransactionForLunas(DhkpRow $dhkpRow): DhkpRow
    {
        if ($dhkpRow->status_bayar === 'LUNAS' && !$dhkpRow->transaksi_id) {
            $datePrefix = date('Ymd');
            $randomSuffix = strtoupper(substr(md5(uniqid()), 0, 4));
            $nomorStts = "STTS-DHKP-{$datePrefix}-{$randomSuffix}";

            $transaction = \App\Models\TransactionRecord::create([
                'desa_id' => $dhkpRow->desa_id ?? auth()->user()?->desa_id ?? 1,
                'nomor_stts' => $nomorStts,
                'tanggal_transaksi' => $dhkpRow->tanggal_bayar ?? now(),
                'operator_id' => auth()->id() ?? 1,
                'total_pokok' => $dhkpRow->ketetapan_pbb,
                'total_denda' => $dhkpRow->denda ?? 0,
                'total_fee' => $dhkpRow->fee_kolektor ?? 0,
                'total_bayar' => $dhkpRow->ketetapan_pbb + ($dhkpRow->denda ?? 0) + ($dhkpRow->fee_kolektor ?? 0),
                'metode_pembayaran' => 'TUNAI',
                'status_void' => false,
                'metadata_kk' => ['petugas' => 'Petugas DHKP'],
            ]);

            $dhkpRow->update([
                'transaksi_id' => $transaction->id,
                'tanggal_bayar' => $dhkpRow->tanggal_bayar ?? now(),
            ]);
        } elseif ($dhkpRow->status_bayar === 'BELUM_BAYAR' && $dhkpRow->transaksi_id) {
            \App\Models\TransactionRecord::where('id', $dhkpRow->transaksi_id)->update([
                'status_void' => true,
                'void_reason' => 'Status DHKP diubah ke BELUM_BAYAR',
                'void_at' => now(),
            ]);
            $dhkpRow->update([
                'transaksi_id' => null,
                'tanggal_bayar' => null,
            ]);
        }

        return $dhkpRow->fresh();
    }

    public function create(array $data): DhkpRow
    {
        $data['total_bayar'] = ($data['ketetapan_pbb'] ?? 0) + ($data['denda'] ?? 0) + ($data['fee_kolektor'] ?? 0);
        $tahun = (int) ($data['tahun'] ?? 2026);
        $desaId = (int) ($data['desa_id'] ?? 1);

        if (!empty($data['nop'])) {
            $existing = DhkpRow::withoutGlobalScope(TenantScope::class)
                ->where('nop', $data['nop'])
                ->where('tahun', $tahun)
                ->where('desa_id', $desaId)
                ->first();

            if ($existing) {
                $existing->update($data);
                return $this->syncTransactionForLunas($existing);
            }
        }

        $row = DhkpRow::create($data);
        return $this->syncTransactionForLunas($row);
    }

    public function update(DhkpRow $dhkpRow, array $data): DhkpRow
    {
        $dhkpRow->update($data);
        return $this->syncTransactionForLunas($dhkpRow);
    }

    public function delete(DhkpRow $dhkpRow): bool
    {
        if ($dhkpRow->transaksi_id) {
            \App\Models\TransactionRecord::where('id', $dhkpRow->transaksi_id)->delete();
        }
        return $dhkpRow->delete();
    }

    public function getSummaryKPI(int $tahun = 2026, ?string $dusunFilter = null, ?int $desaId = null): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || is_null($user->desa_id));

        if (!$isSuperAdmin && $user && $user->desa_id) {
            $desaId = $user->desa_id;
        }

        $baseQuery = $isSuperAdmin
            ? DhkpRow::withoutGlobalScope(TenantScope::class)->where('tahun', $tahun)
            : DhkpRow::where('tahun', $tahun);

        if ($desaId) {
            $baseQuery->where('desa_id', $desaId);
        }

        $query = clone $baseQuery;

        if (!empty($dusunFilter) && $dusunFilter !== 'ALL') {
            $filterDusuns = array_map('trim', explode(',', $dusunFilter));
            $query->where(function ($q) use ($filterDusuns) {
                foreach ($filterDusuns as $index => $d) {
                    if ($index === 0) {
                        $q->where('dusun', 'LIKE', $d);
                    } else {
                        $q->orWhere('dusun', 'LIKE', $d);
                    }
                }
            });
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
            $effectiveDesaId = $desaId ?? (!$isSuperAdmin && auth()->check() ? auth()->user()->desa_id : null);
            if ($effectiveDesaId) {
                $dusunsFromTarget = DusunTarget::withoutGlobalScope(TenantScope::class)
                    ->where('desa_id', $effectiveDesaId)
                    ->whereNotNull('nama_dusun')
                    ->distinct()
                    ->pluck('nama_dusun')
                    ->filter()
                    ->values()
                    ->toArray();
                if (!empty($dusunsFromTarget)) {
                    $dusuns = $dusunsFromTarget;
                }
            }
            if (empty($dusuns)) {
                $dusuns = ['Balok', 'Cideres', 'Puncak Sari', 'Cipedes'];
            }
            if (!empty($dusunFilter) && $dusunFilter !== 'ALL') {
                $filterDusuns = array_map('trim', explode(',', $dusunFilter));
                $dusuns = array_intersect($dusuns, $filterDusuns);
            }
        }

        $byDusun = [];
        foreach ($dusuns as $dusunName) {
            $dusunQuery = (clone $baseQuery)->where('dusun', $dusunName);
            $targetQuery = DusunTarget::where('nama_dusun', $dusunName)->where('tahun', $tahun);
            if ($desaId) {
                $targetQuery->where('desa_id', $desaId);
            } elseif (!$isSuperAdmin && auth()->check() && auth()->user()->desa_id) {
                $targetQuery->where('desa_id', auth()->user()->desa_id);
            }
            $targetRecord = $targetQuery->first();
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

        // Group by Desa for Super Admin Multi-Tenant Rekap
        $byDesa = [];

        if ($isSuperAdmin) {
            $desas = Desa::where('status_aktif', true)->get();
            if ($desas->isNotEmpty()) {
                foreach ($desas as $desaItem) {
                    $desaQuery = DhkpRow::withoutGlobalScope(TenantScope::class)
                        ->where('tahun', $tahun)
                        ->where('desa_id', $desaItem->id);

                    $target = (int) (clone $desaQuery)->sum('ketetapan_pbb');
                    $realisasi = (int) (clone $desaQuery)->where('status_bayar', 'LUNAS')->sum('ketetapan_pbb');
                    $spptCount = (clone $desaQuery)->count();
                    $desaLunas = (clone $desaQuery)->where('status_bayar', 'LUNAS')->count();
                    $desaBelum = $spptCount - $desaLunas;
                    $desaPersen = $target > 0 ? round(($realisasi / $target) * 100, 2) : 0;

                    $byDesa[] = [
                        'desa_id' => $desaItem->id,
                        'nama_desa' => $desaItem->nama_desa,
                        'kode_desa' => $desaItem->kode_desa,
                        'target' => $target,
                        'realisasi' => $realisasi,
                        'sisa_piutang' => $target - $realisasi,
                        'total_sppt' => $spptCount,
                        'sppt_lunas' => $desaLunas,
                        'sppt_belum' => $desaBelum,
                        'persentase' => $desaPersen,
                    ];
                }
            }
        } else {
            $effectiveDesaId = $user->desa_id ?? 1;
            $userDesaObj = $user ? $user->desa : null;
            if (!$userDesaObj) {
                $userDesaObj = Desa::find($effectiveDesaId);
            }
            $byDesa[] = [
                'desa_id' => $userDesaObj->id ?? $effectiveDesaId,
                'nama_desa' => $userDesaObj->nama_desa ?? 'Desa',
                'kode_desa' => $userDesaObj->kode_desa ?? '3205120004',
                'target' => $totalKetetapan,
                'realisasi' => $terbayar,
                'sisa_piutang' => $sisaPiutang,
                'total_sppt' => $totalSppt,
                'sppt_lunas' => $spptLunas,
                'sppt_belum' => $spptBelum,
                'persentase' => $persentase,
            ];
        }

        // Top Unpaid Priority List
        $topUnpaidQuery = (clone $baseQuery)->where('status_bayar', 'BELUM_BAYAR');

        if (!empty($dusunFilter) && $dusunFilter !== 'ALL') {
            $filterDusuns = array_map('trim', explode(',', $dusunFilter));
            $topUnpaidQuery->whereIn('dusun', $filterDusuns);
        }

        $topUnpaid = $topUnpaidQuery
            ->with(['desa:id,nama_desa'])
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
                    'nama_desa' => $row->desa->nama_desa ?? ($row->desa_id ? "Desa #{$row->desa_id}" : "Desa"),
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
            'by_desa' => $byDesa,
            'top_unpaid' => $topUnpaid,
        ];
    }

    public function bulkUpsert(array $rows): array
    {
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '512M');

        $created = 0;
        $updated = 0;

        if (empty($rows)) {
            return [
                'created' => 0,
                'updated' => 0,
                'total' => 0,
            ];
        }

        // Cache settings once before looping to eliminate thousands of redundant queries
        $enableFee = filter_var($this->settingRepository->getByKey('enable_fee_kolektor_luar_desa', true), FILTER_VALIDATE_BOOLEAN);
        $defaultFee = $enableFee ? (int) $this->settingRepository->getByKey('fee_kolektor_luar_desa', 5000) : 0;

        // Process in optimal chunk sizes
        $chunks = array_chunk($rows, 500);

        foreach ($chunks as $chunk) {
            DB::transaction(function () use ($chunk, $defaultFee, &$created, &$updated) {
                $nops = [];
                $cleanRows = [];

                foreach ($chunk as $data) {
                    if (empty($data['nop'])) continue;
                    $cleanNop = trim((string) $data['nop']);
                    $nops[] = $cleanNop;
                    $cleanRows[] = $data;
                }

                if (empty($cleanRows)) return;

                $uniqueNops = array_values(array_unique($nops));

                // Fetch all existing rows for this chunk in ONE single batch query
                $existingCollection = DhkpRow::withoutGlobalScope(TenantScope::class)
                    ->whereIn('nop', $uniqueNops)
                    ->get();

                $existingMap = [];
                foreach ($existingCollection as $item) {
                    $key = $item->nop . '_' . $item->tahun . '_' . $item->desa_id;
                    $existingMap[$key] = $item;
                }

                foreach ($cleanRows as $data) {
                    $nop = trim((string) $data['nop']);
                    $tahun = (int) ($data['tahun'] ?? 2026);
                    $desaId = (int) ($data['desa_id'] ?? 1);
                    $fee = isset($data['fee_kolektor']) ? (int) $data['fee_kolektor'] : ((($data['domisili'] ?? '') === 'LUAR_DESA') ? $defaultFee : 0);

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

                    $payload['nop'] = $nop;
                    $payload['tahun'] = $tahun;
                    $payload['desa_id'] = $desaId;
                    $payload['total_bayar'] = ((int) ($payload['ketetapan_pbb'] ?? 0)) + ((int) ($payload['denda'] ?? 0)) + ((int) ($payload['fee_kolektor'] ?? 0));

                    $key = $nop . '_' . $tahun . '_' . $desaId;
                    $existing = $existingMap[$key] ?? null;

                    if ($existing) {
                        $existing->fill($payload);
                        $existing->save();
                        if ($existing->status_bayar === 'LUNAS') {
                            $this->syncTransactionForLunas($existing);
                        }
                        $updated++;
                    } else {
                        $row = DhkpRow::create($payload);
                        if ($row->status_bayar === 'LUNAS') {
                            $this->syncTransactionForLunas($row);
                        }
                        // Update in-memory map to handle duplicate NOP occurrences within same import file
                        $existingMap[$key] = $row;
                        $created++;
                    }
                }
            });
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => $created + $updated,
        ];
    }
}
