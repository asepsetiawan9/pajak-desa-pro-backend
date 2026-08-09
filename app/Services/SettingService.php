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
        'namaDesa' => 'Desa Barudua',
        'kecamatan' => 'Malangbong',
        'kabupaten' => 'Kabupaten Garut',
        'kodeDesa' => '32.05.080.001',
        'namaKades' => 'Endang Yana',
        'nipKades' => '19780512 200501 1 004',
        'namaPetugas' => 'Kolektor PBB Desa',
        'nipPetugas' => '19850315 201002 1 002',
        'teleponDesa' => '(0262) 421001',
        'alamatDesa' => 'Jl. Raya Barudua No. 12, Kec. Malangbong, Garut',

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
        'headerStruk' => "PEMERINTAH KABUPATEN GARUT\nKECAMATAN MALANGBONG - DESA BARUDUA",
        'footerStruk' => "Terima kasih atas partisipasi Anda dalam pembayaran PBB-P2 untuk pembangunan Desa Barudua.",
        'cetakOtomatis' => true,
        'jumlahSalinan' => 1,
    ];

    public function __construct(protected SettingRepository $settingRepository) {}

    /**
     * Mengambil seluruh pengaturan sistem terformat & bersinkronisasi dengan tipe data asli
     */
    public function getAllSettings(): array
    {
        $rawSettings = $this->settingRepository->getAll();
        $merged = array_merge(self::DEFAULT_SETTINGS, $rawSettings);

        return $this->castSettings($merged);
    }

    /**
     * Memperbarui pengaturan sistem secara masal
     */
    public function updateSettings(array $settings): array
    {
        $this->settingRepository->setMultiple($settings);
        return $this->getAllSettings();
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
