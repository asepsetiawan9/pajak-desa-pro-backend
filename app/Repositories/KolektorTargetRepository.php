<?php

namespace App\Repositories;

use App\Models\DhkpRow;
use App\Models\KolektorTarget;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\DB;

class KolektorTargetRepository
{
    /**
     * List semua target kolektor di suatu desa & tahun.
     */
    public function getByDesaAndTahun(int $desaId, int $tahun): array
    {
        $targets = KolektorTarget::withoutGlobalScope(TenantScope::class)
            ->where('desa_id', $desaId)
            ->where('tahun', $tahun)
            ->with('kolektor:id,name,username,dusun_akses')
            ->get();

        return $targets->map(function (KolektorTarget $target) use ($tahun) {
            $realisasi = $this->calculateRealisasi($target->kolektor_id, $tahun, $target->desa_id);
            return $this->buildPerformanceData($target, $realisasi);
        })->toArray();
    }

    /**
     * Target & performa spesifik 1 kolektor pada tahun tertentu.
     */
    public function getByKolektorAndTahun(int $kolektorId, int $tahun, ?int $desaId = null): ?array
    {
        $query = KolektorTarget::withoutGlobalScope(TenantScope::class)
            ->where('kolektor_id', $kolektorId)
            ->where('tahun', $tahun)
            ->with('kolektor:id,name,username,dusun_akses');

        if ($desaId) {
            $query->where('desa_id', $desaId);
        }

        $target = $query->first();

        if (!$target) {
            return null;
        }

        $realisasi = $this->calculateRealisasi($kolektorId, $tahun, $target->desa_id);
        return $this->buildPerformanceData($target, $realisasi);
    }

    /**
     * Leaderboard: ranking kolektor berdasarkan persentase capaian.
     */
    public function getLeaderboard(int $tahun, ?int $desaId = null): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || is_null($user->desa_id));

        $query = KolektorTarget::withoutGlobalScope(TenantScope::class)
            ->where('tahun', $tahun)
            ->with(['kolektor:id,name,username,dusun_akses', 'desa:id,nama_desa,kode_desa']);

        if ($desaId && $desaId !== 0) {
            $query->where('desa_id', $desaId);
        } elseif (!$isSuperAdmin && $user && $user->desa_id) {
            $query->where('desa_id', $user->desa_id);
        }

        $targets = $query->get();

        $leaderboard = $targets->map(function (KolektorTarget $target) use ($tahun) {
            $realisasi = $this->calculateRealisasi($target->kolektor_id, $tahun, $target->desa_id);
            return $this->buildPerformanceData($target, $realisasi);
        });

        // Sort by persentase_nominal descending
        return $leaderboard->sortByDesc('persentase_nominal')->values()->toArray();
    }

    /**
     * Trend setoran harian kolektor (30 hari terakhir).
     */
    public function getTrendHarian(int $kolektorId, int $tahun, ?int $desaId = null): array
    {
        $query = DhkpRow::withoutGlobalScope(TenantScope::class)
            ->where('kolektor_id', $kolektorId)
            ->where('tahun', $tahun)
            ->where('status_bayar', 'LUNAS')
            ->whereNotNull('tanggal_bayar')
            ->where('tanggal_bayar', '>=', now()->subDays(30));

        if ($desaId) {
            $query->where('desa_id', $desaId);
        }

        return $query
            ->select(
                DB::raw('DATE(tanggal_bayar) as tanggal'),
                DB::raw('SUM(ketetapan_pbb) as nominal'),
                DB::raw('COUNT(*) as jumlah_sppt')
            )
            ->groupBy(DB::raw('DATE(tanggal_bayar)'))
            ->orderBy('tanggal', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Trend mingguan kolektor (12 minggu terakhir).
     */
    public function getTrendMingguan(int $kolektorId, int $tahun, ?int $desaId = null): array
    {
        $query = DhkpRow::withoutGlobalScope(TenantScope::class)
            ->where('kolektor_id', $kolektorId)
            ->where('tahun', $tahun)
            ->where('status_bayar', 'LUNAS')
            ->whereNotNull('tanggal_bayar')
            ->where('tanggal_bayar', '>=', now()->subWeeks(12));

        if ($desaId) {
            $query->where('desa_id', $desaId);
        }

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $weekExpression = $isSqlite ? "strftime('%Y%W', tanggal_bayar)" : 'YEARWEEK(tanggal_bayar, 1)';

        return $query
            ->select(
                DB::raw("{$weekExpression} as minggu"),
                DB::raw('MIN(DATE(tanggal_bayar)) as mulai'),
                DB::raw('MAX(DATE(tanggal_bayar)) as selesai'),
                DB::raw('SUM(ketetapan_pbb) as nominal'),
                DB::raw('COUNT(*) as jumlah_sppt')
            )
            ->groupBy(DB::raw($weekExpression))
            ->orderBy('minggu', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Breakdown capaian kolektor per dusun yang diampu beserta perbandingan target dusun.
     */
    public function getCapaianPerDusun(int $kolektorId, int $tahun, ?int $desaId = null): array
    {
        $kolektor = User::withoutGlobalScope(TenantScope::class)->find($kolektorId);
        if (!$kolektor) {
            return [];
        }

        $effectiveDesaId = $desaId ?? $kolektor->desa_id;

        // Ambil daftar dusun yang diampu
        $dusunList = [];
        if (!empty($kolektor->dusun_akses) && strtoupper(trim($kolektor->dusun_akses)) !== 'ALL') {
            $dusunList = array_values(array_filter(array_map('trim', explode(',', $kolektor->dusun_akses))));
        } else {
            // Ambil seluruh dusun dari master dusun_targets atau dhkp_rows desa tersebut
            $targetDusuns = DB::table('dusun_targets')
                ->where('desa_id', $effectiveDesaId)
                ->where('tahun', $tahun)
                ->pluck('nama_dusun')
                ->toArray();

            $dhkpDusuns = DhkpRow::withoutGlobalScope(TenantScope::class)
                ->where('desa_id', $effectiveDesaId)
                ->where('tahun', $tahun)
                ->whereNotNull('dusun')
                ->distinct()
                ->pluck('dusun')
                ->toArray();

            $dusunList = array_values(array_unique(array_filter(array_merge($targetDusuns, $dhkpDusuns))));
        }

        if (empty($dusunList)) {
            return [];
        }

        $breakdown = [];
        foreach ($dusunList as $dusunName) {
            $cleanedDusun = trim($dusunName);
            if (empty($cleanedDusun)) continue;

            // Target dusun
            $targetDusun = DB::table('dusun_targets')
                ->where('desa_id', $effectiveDesaId)
                ->where('tahun', $tahun)
                ->whereRaw('UPPER(nama_dusun) = ?', [strtoupper($cleanedDusun)])
                ->first();

            $targetDusunNominal = $targetDusun ? (int) $targetDusun->target_pbb : 0;

            // Realisasi kolektor di dusun ini
            $dhkpQuery = DhkpRow::withoutGlobalScope(TenantScope::class)
                ->where('kolektor_id', $kolektorId)
                ->where('tahun', $tahun)
                ->where('status_bayar', 'LUNAS')
                ->whereRaw('UPPER(dusun) = ?', [strtoupper($cleanedDusun)]);

            if ($effectiveDesaId) {
                $dhkpQuery->where('desa_id', $effectiveDesaId);
            }

            $realisasiNominal = (int) (clone $dhkpQuery)->sum('ketetapan_pbb');
            $realisasiSppt = (clone $dhkpQuery)->count();
            $totalFee = (int) (clone $dhkpQuery)->sum('fee_kolektor');
            $persenDusun = $targetDusunNominal > 0
                ? round(($realisasiNominal / $targetDusunNominal) * 100, 2)
                : 0;

            $breakdown[] = [
                'nama_dusun' => $cleanedDusun,
                'target_dusun_nominal' => $targetDusunNominal,
                'realisasi_nominal' => $realisasiNominal,
                'realisasi_sppt' => $realisasiSppt,
                'persentase_dusun' => $persenDusun,
                'total_fee' => $totalFee,
            ];
        }

        return $breakdown;
    }

    /**
     * Buat atau update target kolektor (upsert by desa_id + kolektor_id + tahun).
     */
    public function upsertTarget(array $data): KolektorTarget
    {
        return KolektorTarget::withoutGlobalScope(TenantScope::class)->updateOrCreate(
            [
                'desa_id' => $data['desa_id'],
                'kolektor_id' => $data['kolektor_id'],
                'tahun' => $data['tahun'],
            ],
            [
                'target_nominal' => $data['target_nominal'] ?? 0,
                'target_sppt' => $data['target_sppt'] ?? 0,
                'catatan' => $data['catatan'] ?? null,
            ]
        );
    }

    /**
     * Hapus target kolektor.
     */
    public function deleteTarget(int $id): bool
    {
        $target = KolektorTarget::withoutGlobalScope(TenantScope::class)->find($id);
        if (!$target) {
            return false;
        }
        return $target->delete();
    }

    /**
     * Hitung realisasi real-time dari dhkp_rows.
     */
    private function calculateRealisasi(int $kolektorId, int $tahun, ?int $desaId = null): array
    {
        $query = DhkpRow::withoutGlobalScope(TenantScope::class)
            ->where('kolektor_id', $kolektorId)
            ->where('tahun', $tahun)
            ->where('status_bayar', 'LUNAS');

        if ($desaId) {
            $query->where('desa_id', $desaId);
        }

        $nominal = (int) (clone $query)->sum('ketetapan_pbb');
        $sppt = (clone $query)->count();
        $totalFee = (int) (clone $query)->sum('fee_kolektor');

        return [
            'nominal' => $nominal,
            'sppt' => $sppt,
            'total_fee' => $totalFee,
        ];
    }

    /**
     * Build structured performance data dari target + realisasi.
     */
    private function buildPerformanceData(KolektorTarget $target, array $realisasi): array
    {
        $persenNominal = $target->target_nominal > 0
            ? round(($realisasi['nominal'] / $target->target_nominal) * 100, 2)
            : 0;
        $persenSppt = $target->target_sppt > 0
            ? round(($realisasi['sppt'] / $target->target_sppt) * 100, 2)
            : 0;

        return [
            'id' => $target->id,
            'kolektor_id' => $target->kolektor_id,
            'kolektor_name' => $target->kolektor->name ?? 'Unknown',
            'kolektor_username' => $target->kolektor->username ?? '',
            'dusun_akses' => $target->kolektor->dusun_akses ?? null,
            'desa_id' => $target->desa_id,
            'nama_desa' => $target->desa->nama_desa ?? null,
            'tahun' => $target->tahun,
            'target_nominal' => $target->target_nominal,
            'target_sppt' => $target->target_sppt,
            'realisasi_nominal' => $realisasi['nominal'],
            'realisasi_sppt' => $realisasi['sppt'],
            'sisa_nominal' => max(0, $target->target_nominal - $realisasi['nominal']),
            'sisa_sppt' => max(0, $target->target_sppt - $realisasi['sppt']),
            'persentase_nominal' => $persenNominal,
            'persentase_sppt' => $persenSppt,
            'total_fee' => $realisasi['total_fee'],
            'badge' => $this->determineBadge($persenNominal),
            'status' => $this->determineStatus($persenNominal),
            'catatan' => $target->catatan,
            'created_at' => $target->created_at?->toIso8601String(),
            'updated_at' => $target->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Tentukan badge berdasarkan persentase capaian.
     */
    private function determineBadge(float $persentase): string
    {
        if ($persentase >= 100) return 'LEGEND';
        if ($persentase >= 75) return 'GOLD';
        if ($persentase >= 50) return 'SILVER';
        if ($persentase >= 25) return 'BRONZE';
        return 'NONE';
    }

    /**
     * Tentukan status berdasarkan persentase.
     */
    private function determineStatus(float $persentase): string
    {
        if ($persentase >= 100) return 'EXCEEDED';
        if ($persentase >= 75) return 'ON_TRACK';
        if ($persentase >= 50) return 'MODERATE';
        if ($persentase >= 25) return 'BEHIND';
        return 'CRITICAL';
    }
}
