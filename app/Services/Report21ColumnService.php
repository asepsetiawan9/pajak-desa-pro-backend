<?php

namespace App\Services;

use App\Models\DhkpRow;
use App\Models\DusunTarget;
use Illuminate\Support\Facades\DB;

class Report21ColumnService
{
    /**
     * Membangun Agregasi Laporan 21-Kolom Resmi Per Dusun/Blok
     */
    public function generate21ColumnReport(int $tahun = 2026, ?string $dusunFilter = null, ?string $bukuFilter = null): array
    {
        $queryBase = DhkpRow::where('tahun', $tahun);

        if (!empty($bukuFilter) && strtoupper($bukuFilter) !== 'SEMUA' && strtoupper($bukuFilter) !== 'ALL') {
            $bukuUpper = strtoupper(trim($bukuFilter));
            if ($bukuUpper === 'BUKU_1' || $bukuUpper === '1') {
                $queryBase->where('ketetapan_pbb', '<=', 100000);
            } elseif ($bukuUpper === 'BUKU_2' || $bukuUpper === '2') {
                $queryBase->where('ketetapan_pbb', '>', 100000)->where('ketetapan_pbb', '<=', 500000);
            } elseif ($bukuUpper === 'BUKU_3' || $bukuUpper === '3') {
                $queryBase->where('ketetapan_pbb', '>', 500000)->where('ketetapan_pbb', '<=', 2000000);
            } elseif ($bukuUpper === 'BUKU_4' || $bukuUpper === '4') {
                $queryBase->where('ketetapan_pbb', '>', 2000000)->where('ketetapan_pbb', '<=', 5000000);
            } elseif ($bukuUpper === 'BUKU_5' || $bukuUpper === '5') {
                $queryBase->where('ketetapan_pbb', '>', 5000000);
            }
        }

        $dusuns = (clone $queryBase)
            ->whereNotNull('dusun')
            ->distinct()
            ->pluck('dusun')
            ->filter()
            ->values()
            ->toArray();

        if (empty($dusuns)) {
            $dusuns = DhkpRow::whereNotNull('dusun')
                ->distinct()
                ->pluck('dusun')
                ->filter()
                ->values()
                ->toArray();
        }

        if (!empty($dusunFilter) && strtoupper($dusunFilter) !== 'ALL') {
            $filterDusuns = array_map('strtoupper', array_map('trim', explode(',', $dusunFilter)));
            $dusuns = array_values(array_filter($dusuns, function ($d) use ($filterDusuns) {
                return in_array(strtoupper(trim($d)), $filterDusuns);
            }));
        }

        $result = [];

        $totalPokokDesa = 0;
        $totalSpptDesa = 0;
        $totalRealisasiPokokDesa = 0;
        $totalRealisasiSpptDesa = 0;

        foreach ($dusuns as $index => $dusun) {
            $rows = (clone $queryBase)->where('dusun', $dusun)->get();

            $spptKetetapan = $rows->count();
            $pokokKetetapan = (int) $rows->sum('ketetapan_pbb');

            $lunasRows = $rows->where('status_bayar', 'LUNAS');
            $spptLunas = $lunasRows->count();
            $pokokLunas = (int) $lunasRows->sum('ketetapan_pbb');

            $spptSisa = $spptKetetapan - $spptLunas;
            $pokokSisa = $pokokKetetapan - $pokokLunas;

            $persenSppt = $spptKetetapan > 0 ? round(($spptLunas / $spptKetetapan) * 100, 2) : 0;
            $persenPokok = $pokokKetetapan > 0 ? round(($pokokLunas / $pokokKetetapan) * 100, 2) : 0;

            $totalPokokDesa += $pokokKetetapan;
            $totalSpptDesa += $spptKetetapan;
            $totalRealisasiPokokDesa += $pokokLunas;
            $totalRealisasiSpptDesa += $spptLunas;

            $result[] = [
                'no' => $index + 1,
                'dusun' => $dusun,
                'sppt_ketetapan' => $spptKetetapan,
                'pokok_ketetapan' => $pokokKetetapan,
                'sppt_realisasi' => $spptLunas,
                'pokok_realisasi' => $pokokLunas,
                'sppt_sisa' => $spptSisa,
                'pokok_sisa' => $pokokSisa,
                'persen_sppt' => $persenSppt,
                'persen_pokok' => $persenPokok,
            ];
        }

        $persenSpptTotal = $totalSpptDesa > 0 ? round(($totalRealisasiSpptDesa / $totalSpptDesa) * 100, 2) : 0;
        $persenPokokTotal = $totalPokokDesa > 0 ? round(($totalRealisasiPokokDesa / $totalPokokDesa) * 100, 2) : 0;

        return [
            'tahun' => $tahun,
            'buku' => $bukuFilter ?? 'SEMUA',
            'details' => $result,
            'summary' => [
                'total_sppt_ketetapan' => $totalSpptDesa,
                'total_pokok_ketetapan' => $totalPokokDesa,
                'total_sppt_realisasi' => $totalRealisasiSpptDesa,
                'total_pokok_realisasi' => $totalRealisasiPokokDesa,
                'total_sppt_sisa' => $totalSpptDesa - $totalRealisasiSpptDesa,
                'total_pokok_sisa' => $totalPokokDesa - $totalRealisasiPokokDesa,
                'persen_sppt_total' => $persenSpptTotal,
                'persen_pokok_total' => $persenPokokTotal,
            ],
        ];
    }
}
