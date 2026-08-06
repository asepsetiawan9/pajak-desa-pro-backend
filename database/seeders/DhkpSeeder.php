<?php

namespace Database\Seeders;

use App\Models\DhkpRow;
use Illuminate\Database\Seeder;

class DhkpSeeder extends Seeder
{
    public function run(): void
    {
        $dhkpData = [
            // Dusun Balok (Blok 01)
            [
                'nop' => '32.05.010.001.001-0001.0',
                'nama_wp' => 'H. DEDI SUHARDI',
                'alamat_wp' => 'KP. BALOK RT 01 RW 02 DESA MALANGBONG',
                'alamat_op' => 'KP. BALOK BLOK 01',
                'dusun' => 'Balok',
                'blok' => 'Blok 01',
                'rt_rw' => '001/002',
                'luas_bumi' => 350,
                'luas_bangunan' => 120,
                'njop_bumi' => 175000000,
                'njop_bangunan' => 96000000,
                'ketetapan_pbb' => 245000,
                'denda' => 0,
                'fee_kolektor' => 0,
                'total_bayar' => 245000,
                'status_bayar' => 'LUNAS',
                'domisili' => 'DALAM_DESA',
                'tanggal_bayar' => '2026-03-15 10:30:00',
                'tahun' => 2026,
            ],
            [
                'nop' => '32.05.010.001.001-0002.0',
                'nama_wp' => 'NINA MARLIANA',
                'alamat_wp' => 'KP. BALOK RT 02 RW 02 DESA MALANGBONG',
                'alamat_op' => 'KP. BALOK BLOK 01',
                'dusun' => 'Balok',
                'blok' => 'Blok 01',
                'rt_rw' => '002/002',
                'luas_bumi' => 220,
                'luas_bangunan' => 85,
                'njop_bumi' => 110000000,
                'njop_bangunan' => 68000000,
                'ketetapan_pbb' => 165000,
                'denda' => 0,
                'fee_kolektor' => 0,
                'total_bayar' => 165000,
                'status_bayar' => 'BELUM_BAYAR',
                'domisili' => 'DALAM_DESA',
                'tahun' => 2026,
            ],
            [
                'nop' => '32.05.010.001.001-0003.0',
                'nama_wp' => 'DRS. BAMBANG SUTRISNO',
                'alamat_wp' => 'JL. SOEKARNO HATTA NO 120 BANDUNG',
                'alamat_op' => 'KP. BALOK BLOK 01 (TANAH KEBUN)',
                'dusun' => 'Balok',
                'blok' => 'Blok 01',
                'rt_rw' => '001/002',
                'luas_bumi' => 1200,
                'luas_bangunan' => 0,
                'njop_bumi' => 480000000,
                'njop_bangunan' => 0,
                'ketetapan_pbb' => 435000,
                'denda' => 0,
                'fee_kolektor' => 5000,
                'total_bayar' => 440000,
                'status_bayar' => 'BELUM_BAYAR',
                'domisili' => 'LUAR_DESA',
                'tahun' => 2026,
            ],

            // Dusun Cideres (Blok 02)
            [
                'nop' => '32.05.010.002.002-0010.0',
                'nama_wp' => 'MAMAN ABDURRAHMAN',
                'alamat_wp' => 'KP. CIDERES RT 03 RW 01',
                'alamat_op' => 'KP. CIDERES BLOK 02',
                'dusun' => 'Cideres',
                'blok' => 'Blok 02',
                'rt_rw' => '003/001',
                'luas_bumi' => 180,
                'luas_bangunan' => 60,
                'njop_bumi' => 90000000,
                'njop_bangunan' => 48000000,
                'ketetapan_pbb' => 125000,
                'denda' => 0,
                'fee_kolektor' => 0,
                'total_bayar' => 125000,
                'status_bayar' => 'LUNAS',
                'domisili' => 'DALAM_DESA',
                'tanggal_bayar' => '2026-04-02 14:15:00',
                'tahun' => 2026,
            ],
            [
                'nop' => '32.05.010.002.002-0011.0',
                'nama_wp' => 'CECEP KURNIAWAN',
                'alamat_wp' => 'KP. CIDERES RT 01 RW 01',
                'alamat_op' => 'KP. CIDERES BLOK 02',
                'dusun' => 'Cideres',
                'blok' => 'Blok 02',
                'rt_rw' => '001/001',
                'luas_bumi' => 210,
                'luas_bangunan' => 90,
                'njop_bumi' => 105000000,
                'njop_bangunan' => 72000000,
                'ketetapan_pbb' => 155000,
                'denda' => 0,
                'fee_kolektor' => 0,
                'total_bayar' => 155000,
                'status_bayar' => 'BELUM_BAYAR',
                'domisili' => 'DALAM_DESA',
                'tahun' => 2026,
            ],

            // Dusun Puncak Sari (Blok 03)
            [
                'nop' => '32.05.010.003.003-0020.0',
                'nama_wp' => 'EUIS RATNASARI',
                'alamat_wp' => 'KP. PUNCAK SARI RT 02 RW 03',
                'alamat_op' => 'KP. PUNCAK SARI BLOK 03',
                'dusun' => 'Puncak Sari',
                'blok' => 'Blok 03',
                'rt_rw' => '002/003',
                'luas_bumi' => 150,
                'luas_bangunan' => 45,
                'njop_bumi' => 75000000,
                'njop_bangunan' => 36000000,
                'ketetapan_pbb' => 98000,
                'denda' => 0,
                'fee_kolektor' => 0,
                'total_bayar' => 98000,
                'status_bayar' => 'BELUM_BAYAR',
                'domisili' => 'DALAM_DESA',
                'tahun' => 2026,
            ],

            // Dusun Cipedes (Blok 04)
            [
                'nop' => '32.05.010.004.004-0030.0',
                'nama_wp' => 'IR. TOTONG HERMANTO',
                'alamat_wp' => 'JL. ASIAN AFRIKA NO 45 JAKARTA',
                'alamat_op' => 'KP. CIPEDES BLOK 04',
                'dusun' => 'Cipedes',
                'blok' => 'Blok 04',
                'rt_rw' => '001/004',
                'luas_bumi' => 850,
                'luas_bangunan' => 250,
                'njop_bumi' => 425000000,
                'njop_bangunan' => 200000000,
                'ketetapan_pbb' => 580000,
                'denda' => 0,
                'fee_kolektor' => 5000,
                'total_bayar' => 585000,
                'status_bayar' => 'LUNAS',
                'domisili' => 'LUAR_DESA',
                'tanggal_bayar' => '2026-05-10 11:20:00',
                'tahun' => 2026,
            ],
        ];

        foreach ($dhkpData as $row) {
            DhkpRow::updateOrCreate(
                ['nop' => $row['nop'], 'tahun' => $row['tahun']],
                $row
            );
        }
    }
}
