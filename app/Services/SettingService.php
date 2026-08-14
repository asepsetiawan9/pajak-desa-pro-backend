<?php

namespace App\Services;

use App\Repositories\SettingRepository;

class SettingService
{
    /**
     * Data Pengaturan Standar Pabrik (Default Fallbacks)
     */
    public const DEFAULT_SETTINGS = [
        // Identitas Instansi & Pejabat
        'namaDesa' => 'Desa',
        'kecamatan' => 'Malangbong',
        'kabupaten' => 'Kabupaten Garut',
        'kodeDesa' => '32.05.080.001',
        'jabatanKades' => 'Kepala Desa',
        'namaKades' => 'Kepala Desa',
        'nipKades' => '-',
        'jabatanPetugas' => 'Bendahara / Kolektor Utama PBB',
        'namaPetugas' => 'Kolektor PBB Desa',
        'nipPetugas' => '-',
        'teleponDesa' => '(0262) 421001',
        'alamatDesa' => 'Kantor Kepala Desa',

        // Keuangan & Parameter Fee Kolektor PBB-P2
        'tahunAktif' => 2026,
        'jatuhTempoBulan' => 8,
        'jatuhTempoTanggal' => 31,
        'pembulatanRibuan' => true,
        'enableFeeKolektorLuarDesa' => true,
        'feeKolektorLuarDesa' => 5000,

        // Cetak STTS & Resi
        'printerFormat' => 'thermal58',
        'tampilkanLogoKop' => true,
        'headerStruk' => "PEMERINTAH KABUPATEN GARUT\nKECAMATAN MALANGBONG - DESA",
        'footerStruk' => "Terima kasih atas partisipasi Anda dalam pembayaran PBB-P2 untuk pembangunan desa.",
        'cetakOtomatis' => true,
        'jumlahSalinan' => 1,
    ];

    public function __construct(protected SettingRepository $settingRepository) {}

    /**
     * Mengambil seluruh pengaturan sistem terformat & bersinkronisasi dengan tipe data asli
     */
    public function getAllSettings(?int $desaId = null): array
    {
        $rawSettings = $this->settingRepository->getAll($desaId);

        // Cari data desa jika spesifik desa diminta atau user terkait desa
        $targetDesaId = $desaId ?? (auth()->check() ? auth()->user()->desa_id : null);
        $desaDefaults = [];
        if ($targetDesaId) {
            $desa = \App\Models\Desa::find($targetDesaId);
            if ($desa) {
                $desaDefaults = [
                    'namaDesa' => $desa->nama_desa,
                    'nama_desa' => $desa->nama_desa,
                    'kodeDesa' => $desa->kode_desa,
                    'kode_desa' => $desa->kode_desa,
                    'kecamatan' => $desa->nama_kecamatan,
                    'nama_kecamatan' => $desa->nama_kecamatan,
                    'kabupaten' => 'Pemerintah Kabupaten ' . $desa->nama_kabupaten,
                    'nama_instansi' => 'Pemerintah Kabupaten ' . $desa->nama_kabupaten,
                    'namaKades' => $desa->nama_kades ?: 'Kepala Desa',
                    'nama_kades' => $desa->nama_kades ?: 'Kepala Desa',
                    'nipKades' => $desa->nip_kades ?: '-',
                    'nip_kades' => $desa->nip_kades ?: '-',
                ];
            }
        }

        $merged = array_merge(self::DEFAULT_SETTINGS, $desaDefaults, $rawSettings);

        return $this->castSettings($merged);
    }

    /**
     * Memperbarui pengaturan sistem secara masal
     */
    public function updateSettings(array $settings, ?int $desaId = null): array
    {
        $this->settingRepository->setMultiple($settings, $desaId);
        return $this->getAllSettings($desaId);
    }

    /**
     * Mengkonversi nilai string dari DB menjadi tipe data PHP murni (int, bool, string)
     */
    protected function castSettings(array $settings): array
    {
        $casted = [];

        foreach ($settings as $key => $val) {
            $default = self::DEFAULT_SETTINGS[$key] ?? null;

            if (is_bool($default)) {
                $casted[$key] = filter_var($val, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_int($default)) {
                $casted[$key] = is_numeric($val) ? (int) $val : $default;
            } else {
                $casted[$key] = (string) $val;
            }
        }

        return $casted;
    }
}
