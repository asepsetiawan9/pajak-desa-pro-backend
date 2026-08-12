<?php

namespace App\Repositories;

use App\Models\Setting;

class SettingRepository
{
    /**
     * Mapping alias lengkap antara key frontend (camelCase) dan backend (snake_case)
     */
    protected array $aliases = [
        'namaDesa' => 'nama_desa',
        'kecamatan' => 'nama_kecamatan',
        'kabupaten' => 'nama_instansi',
        'kodeDesa' => 'kode_desa',
        'tahunAktif' => 'tahun_pajak',
        'jabatanKades' => 'jabatan_kades',
        'namaKades' => 'nama_kades',
        'nipKades' => 'nip_kades',
        'jabatanPetugas' => 'jabatan_bendahara',
        'namaPetugas' => 'nama_bendahara',
        'nipPetugas' => 'nip_bendahara',
        'teleponDesa' => 'telepon_desa',
        'alamatDesa' => 'alamat_desa',
        'jatuhTempoBulan' => 'jatuh_tempo_bulan',
        'jatuhTempoTanggal' => 'jatuh_tempo_tanggal',
        'pembulatanRibuan' => 'pembulatan_ribuan',
        'enableFeeKolektorLuarDesa' => 'enable_fee_kolektor_luar_desa',
        'feeKolektorLuarDesa' => 'fee_kolektor_luar_desa',
        'printerFormat' => 'printer_format',
        'tampilkanLogoKop' => 'tampilkan_logo_kop',
        'headerStruk' => 'header_resi_thermal',
        'footerStruk' => 'footer_resi_thermal',
        'cetakOtomatis' => 'cetak_otomatis',
        'jumlahSalinan' => 'jumlah_salinan',
    ];

    protected function getScopedQuery(?int $desaId = null)
    {
        if ($desaId !== null) {
            return Setting::withoutGlobalScope(\App\Scopes\TenantScope::class)->where('desa_id', $desaId);
        }
        return Setting::query();
    }

    public function getAll(?int $desaId = null): array
    {
        $query = $this->getScopedQuery($desaId);
        $settings = $query->pluck('value', 'key')->toArray();
        $result = [];

        foreach ($settings as $key => $value) {
            $result[$key] = $value;
        }

        foreach ($settings as $key => $value) {
            if (isset($this->aliases[$key])) {
                $result[$this->aliases[$key]] = $value;
            } else {
                $camelKey = array_search($key, $this->aliases);
                if ($camelKey !== false) {
                    $result[$camelKey] = $value;
                }
            }
        }

        return $result;
    }

    public function getByKey(string $key, $default = null, ?int $desaId = null)
    {
        $setting = $this->getScopedQuery($desaId)->where('key', $key)->first();
        if ($setting !== null) {
            return $setting->value;
        }

        // Coba cari melalui alias jika key utama tidak ditemukan
        $aliasKey = $this->aliases[$key] ?? array_search($key, $this->aliases);
        if ($aliasKey) {
            $aliasSetting = $this->getScopedQuery($desaId)->where('key', $aliasKey)->first();
            if ($aliasSetting !== null) {
                return $aliasSetting->value;
            }
        }

        return $default;
    }

    public function setKey(string $key, $value, ?string $description = null, ?int $desaId = null): Setting
    {
        $stringValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

        $matchCriteria = ['key' => $key];
        if ($desaId !== null) {
            $matchCriteria['desa_id'] = $desaId;
        }

        $setting = Setting::withoutGlobalScope(\App\Scopes\TenantScope::class)->updateOrCreate(
            $matchCriteria,
            ['value' => $stringValue, 'description' => $description]
        );

        // Jika memiliki alias, sinkronkan juga nilai aliasnya
        $aliasKey = $this->aliases[$key] ?? array_search($key, $this->aliases);
        if ($aliasKey) {
            $aliasMatchCriteria = ['key' => $aliasKey];
            if ($desaId !== null) {
                $aliasMatchCriteria['desa_id'] = $desaId;
            }

            Setting::withoutGlobalScope(\App\Scopes\TenantScope::class)->updateOrCreate(
                $aliasMatchCriteria,
                ['value' => $stringValue, 'description' => $description ? "Alias for {$key}" : null]
            );
        }

        return $setting;
    }

    public function setMultiple(array $settings, ?int $desaId = null): void
    {
        foreach ($settings as $key => $value) {
            $this->setKey($key, $value, null, $desaId);
        }
    }
}

