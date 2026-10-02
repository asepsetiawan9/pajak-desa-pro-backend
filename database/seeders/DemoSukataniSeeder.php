<?php

namespace Database\Seeders;

use App\Models\Desa;
use App\Models\DhkpRow;
use App\Models\Dusun;
use App\Models\DusunTarget;
use App\Models\KolektorTarget;
use App\Models\Setting;
use App\Models\TransactionRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoSukataniSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info("🚀 Memulai seeding data DEMO Desa Sukatani, Kec. Cisurupan...");

        // 1. Master Desa Sukatani
        $desa = Desa::updateOrCreate(
            ['id' => 1],
            [
                'kode_desa' => '3205042001',
                'nama_desa' => 'Desa Sukatani',
                'nama_kecamatan' => 'Cisurupan',
                'nama_kabupaten' => 'Garut',
                'nama_provinsi' => 'Jawa Barat',
                'nama_kades' => 'H. Asep Rohmat, S.IP',
                'nip_kades' => '19760812 200604 1 008',
                'subdomain' => 'pbbdemo',
                'logo_path' => '/logo2.png',
                'status_aktif' => true,
            ]
        );

        // 2. Master Dusuns
        $dusuns = [
            ['nama_dusun' => 'DUSUN SUKATANI', 'kode_dusun' => 'DSN-01', 'rt_rw' => 'RT 01-03 / RW 01'],
            ['nama_dusun' => 'DUSUN CIKUPA', 'kode_dusun' => 'DSN-02', 'rt_rw' => 'RT 01-04 / RW 02'],
            ['nama_dusun' => 'DUSUN PASIRANGIN', 'kode_dusun' => 'DSN-03', 'rt_rw' => 'RT 01-03 / RW 03'],
            ['nama_dusun' => 'DUSUN BABAKAN', 'kode_dusun' => 'DSN-04', 'rt_rw' => 'RT 01-04 / RW 04'],
        ];

        foreach ($dusuns as $d) {
            Dusun::updateOrCreate(
                ['desa_id' => 1, 'nama_dusun' => $d['nama_dusun']],
                [
                    'kode_dusun' => $d['kode_dusun'],
                    'rt_rw' => $d['rt_rw'],
                    'status_aktif' => true,
                ]
            );
        }

        // 3. User Accounts
        $users = [
            [
                'name' => 'Super Admin System',
                'username' => 'superadmin',
                'nip' => '00000000 000000 0 000',
                'email' => 'superadmin@lentera.id',
                'phone' => '081200000001',
                'password' => Hash::make('SuperAdmin@2026!'),
                'role' => 'SUPER_ADMIN_SYSTEM',
                'dusun_akses' => 'ALL',
                'status_aktif' => true,
                'desa_id' => null,
            ],
            [
                'name' => 'Asep Ridwan, S.AP',
                'username' => 'admin.sukatani',
                'nip' => '19890415 201402 1 003',
                'email' => 'admin@sukatani.desa.id',
                'phone' => '081234567891',
                'password' => Hash::make('AdminSukatani@2026!'),
                'role' => 'SUPER_ADMIN',
                'dusun_akses' => 'ALL',
                'status_aktif' => true,
                'desa_id' => 1,
            ],
            [
                'name' => 'H. Asep Rohmat, S.IP',
                'username' => 'kades.sukatani',
                'nip' => '19760812 200604 1 008',
                'email' => 'kades@sukatani.desa.id',
                'phone' => '081122334455',
                'password' => Hash::make('KadesSukatani@2026!'),
                'role' => 'KEPALA_DESA',
                'dusun_akses' => 'ALL',
                'status_aktif' => true,
                'desa_id' => 1,
            ],
            [
                'name' => 'Agus Hidayat',
                'username' => 'kolektor.sukatani',
                'nip' => '-',
                'email' => 'agus@sukatani.desa.id',
                'phone' => '085712341111',
                'password' => Hash::make('KolektorSukatani@2026!'),
                'role' => 'KOLEKTOR',
                'dusun_akses' => 'DUSUN SUKATANI, DUSUN CIKUPA',
                'status_aktif' => true,
                'desa_id' => 1,
            ],
            [
                'name' => 'Dadang Supriatna',
                'username' => 'kolektor.pasirangin',
                'nip' => '-',
                'email' => 'dadang@sukatani.desa.id',
                'phone' => '085712342222',
                'password' => Hash::make('KolektorPasirangin@2026!'),
                'role' => 'KOLEKTOR',
                'dusun_akses' => 'DUSUN PASIRANGIN, DUSUN BABAKAN',
                'status_aktif' => true,
                'desa_id' => 1,
            ],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(
                ['username' => $u['username']],
                $u
            );
        }

        $kolektor1 = User::where('username', 'kolektor.sukatani')->first();
        $kolektor2 = User::where('username', 'kolektor.pasirangin')->first();
        $adminUser = User::where('username', 'admin.sukatani')->first();

        // 4. System Settings
        $settings = [
            'namaDesa' => 'Desa Sukatani',
            'kecamatan' => 'Cisurupan',
            'kabupaten' => 'Kabupaten Garut',
            'kodeDesa' => '32.05.040.001',
            'jabatanKades' => 'Kepala Desa',
            'namaKades' => 'H. Asep Rohmat, S.IP',
            'nipKades' => '19760812 200604 1 008',
            'jabatanPetugas' => 'Bendahara / Kolektor Utama PBB',
            'namaPetugas' => 'Agus Hidayat',
            'nipPetugas' => '-',
            'teleponDesa' => '(0262) 577102',
            'alamatDesa' => 'Jl. Raya Cisurupan No. 45, Desa Sukatani, Kec. Cisurupan, Garut',
            'tahunAktif' => 2026,
            'jatuhTempoBulan' => 8,
            'jatuhTempoTanggal' => 31,
            'pembulatanRibuan' => true,
            'enableFeeKolektorLuarDesa' => true,
            'feeKolektorLuarDesa' => 5000,
            'printerFormat' => 'thermal58',
            'tampilkanLogoKop' => true,
            'headerStruk' => "PEMERINTAH KABUPATEN GARUT\nKECAMATAN CISURUPAN - DESA SUKATANI",
            'footerStruk' => "Pajak Anda untuk Pembangunan Desa Sukatani yang Maju dan Mandiri",
            'cetakOtomatis' => true,
            'jumlahSalinan' => 1,
        ];

        foreach ($settings as $key => $val) {
            Setting::updateOrCreate(
                ['desa_id' => 1, 'key' => $key],
                ['value' => is_bool($val) ? ($val ? 'true' : 'false') : (string)$val, 'description' => "Pengaturan {$key}"]
            );
        }

        // 5. Data SPPT DHKP & Transaksi
        $names = [
            // Dusun Sukatani
            ['nama' => 'Hj. Siti Maryam', 'dusun' => 'DUSUN SUKATANI', 'nominal' => 450000, 'luas_b' => 320, 'luas_bg' => 120, 'lunas' => true],
            ['nama' => 'Drs. H. Tatang Sutisna', 'dusun' => 'DUSUN SUKATANI', 'nominal' => 850000, 'luas_b' => 600, 'luas_bg' => 250, 'lunas' => true],
            ['nama' => 'Ujang Suherman', 'dusun' => 'DUSUN SUKATANI', 'nominal' => 65000, 'luas_b' => 110, 'luas_bg' => 45, 'lunas' => true],
            ['nama' => 'Cecep Supriatna', 'dusun' => 'DUSUN SUKATANI', 'nominal' => 125000, 'luas_b' => 180, 'luas_bg' => 70, 'lunas' => false],
            ['nama' => 'Euis Komalasari', 'dusun' => 'DUSUN SUKATANI', 'nominal' => 75000, 'luas_b' => 125, 'luas_bg' => 50, 'lunas' => true],
            ['nama' => 'Asep Saepuloh', 'dusun' => 'DUSUN SUKATANI', 'nominal' => 950000, 'luas_b' => 750, 'luas_bg' => 300, 'lunas' => false],
            ['nama' => 'H. Mumuh Muhidin', 'dusun' => 'DUSUN SUKATANI', 'nominal' => 1200000, 'luas_b' => 950, 'luas_bg' => 420, 'lunas' => true],
            ['nama' => 'Iis Rohaeti', 'dusun' => 'DUSUN SUKATANI', 'nominal' => 45000, 'luas_b' => 90, 'luas_bg' => 36, 'lunas' => true],
            ['nama' => 'Yayan Sopian', 'dusun' => 'DUSUN SUKATANI', 'nominal' => 180000, 'luas_b' => 210, 'luas_bg' => 90, 'lunas' => false],
            ['nama' => 'Enok Nurhasanah', 'dusun' => 'DUSUN SUKATANI', 'nominal' => 320000, 'luas_b' => 280, 'luas_bg' => 110, 'lunas' => true],
            ['nama' => 'Dedi Mulyadi', 'dusun' => 'DUSUN SUKATANI', 'nominal' => 88000, 'luas_b' => 140, 'luas_bg' => 60, 'lunas' => true],
            ['nama' => 'Wawan Gunawan', 'dusun' => 'DUSUN SUKATANI', 'nominal' => 550000, 'luas_b' => 420, 'luas_bg' => 160, 'lunas' => false],

            // Dusun Cikupa
            ['nama' => 'Nana Suryana', 'dusun' => 'DUSUN CIKUPA', 'nominal' => 175000, 'luas_b' => 200, 'luas_bg' => 80, 'lunas' => true],
            ['nama' => 'Ade Solihin', 'dusun' => 'DUSUN CIKUPA', 'nominal' => 95000, 'luas_b' => 150, 'luas_bg' => 60, 'lunas' => true],
            ['nama' => 'H. Endang Kosasih', 'dusun' => 'DUSUN CIKUPA', 'nominal' => 780000, 'luas_b' => 550, 'luas_bg' => 220, 'lunas' => true],
            ['nama' => 'Neneng Hasanah', 'dusun' => 'DUSUN CIKUPA', 'nominal' => 52000, 'luas_b' => 100, 'luas_bg' => 40, 'lunas' => false],
            ['nama' => 'Rahmat Hidayat', 'dusun' => 'DUSUN CIKUPA', 'nominal' => 310000, 'luas_b' => 270, 'luas_bg' => 100, 'lunas' => true],
            ['nama' => 'Siti Aisyah', 'dusun' => 'DUSUN CIKUPA', 'nominal' => 140000, 'luas_b' => 190, 'luas_bg' => 75, 'lunas' => true],
            ['nama' => 'Iman Budiman', 'dusun' => 'DUSUN CIKUPA', 'nominal' => 890000, 'luas_b' => 680, 'luas_bg' => 280, 'lunas' => false],
            ['nama' => 'Tati Rohayati', 'dusun' => 'DUSUN CIKUPA', 'nominal' => 42000, 'luas_b' => 85, 'luas_bg' => 35, 'lunas' => true],
            ['nama' => 'Oman Rohman', 'dusun' => 'DUSUN CIKUPA', 'nominal' => 225000, 'luas_b' => 230, 'luas_bg' => 95, 'lunas' => true],
            ['nama' => 'Kiki Zakaria', 'dusun' => 'DUSUN CIKUPA', 'nominal' => 650000, 'luas_b' => 480, 'luas_bg' => 180, 'lunas' => false],

            // Dusun Pasirangin
            ['nama' => 'H. Aceng Fikri', 'dusun' => 'DUSUN PASIRANGIN', 'nominal' => 1100000, 'luas_b' => 850, 'luas_bg' => 380, 'lunas' => true],
            ['nama' => 'Maman Suratman', 'dusun' => 'DUSUN PASIRANGIN', 'nominal' => 85000, 'luas_b' => 135, 'luas_bg' => 55, 'lunas' => true],
            ['nama' => 'Ai Nurjanah', 'dusun' => 'DUSUN PASIRANGIN', 'nominal' => 135000, 'luas_b' => 185, 'luas_bg' => 70, 'lunas' => false],
            ['nama' => 'Budi Santoso', 'dusun' => 'DUSUN PASIRANGIN', 'nominal' => 420000, 'luas_b' => 340, 'luas_bg' => 130, 'lunas' => true],
            ['nama' => 'Entis Sutisna', 'dusun' => 'DUSUN PASIRANGIN', 'nominal' => 68000, 'luas_b' => 115, 'luas_bg' => 48, 'lunas' => true],
            ['nama' => 'Hj. Cucu Hayati', 'dusun' => 'DUSUN PASIRANGIN', 'nominal' => 720000, 'luas_b' => 520, 'luas_bg' => 210, 'lunas' => false],
            ['nama' => 'Agus Ramdani', 'dusun' => 'DUSUN PASIRANGIN', 'nominal' => 195000, 'luas_b' => 220, 'luas_bg' => 85, 'lunas' => true],
            ['nama' => 'Dewi Sartika', 'dusun' => 'DUSUN PASIRANGIN', 'nominal' => 58000, 'luas_b' => 105, 'luas_bg' => 42, 'lunas' => true],
            ['nama' => 'Jajang Karmana', 'dusun' => 'DUSUN PASIRANGIN', 'nominal' => 490000, 'luas_b' => 390, 'luas_bg' => 150, 'lunas' => false],
            ['nama' => 'Lilis Rosita', 'dusun' => 'DUSUN PASIRANGIN', 'nominal' => 82000, 'luas_b' => 130, 'luas_bg' => 52, 'lunas' => true],

            // Dusun Babakan
            ['nama' => 'Dadan Ramdani', 'dusun' => 'DUSUN BABAKAN', 'nominal' => 165000, 'luas_b' => 195, 'luas_bg' => 78, 'lunas' => true],
            ['nama' => 'H. Zaenal Muttaqin', 'dusun' => 'DUSUN BABAKAN', 'nominal' => 980000, 'luas_b' => 720, 'luas_bg' => 310, 'lunas' => true],
            ['nama' => 'Rina Marlina', 'dusun' => 'DUSUN BABAKAN', 'nominal' => 72000, 'luas_b' => 120, 'luas_bg' => 50, 'lunas' => false],
            ['nama' => 'Saepudin', 'dusun' => 'DUSUN BABAKAN', 'nominal' => 245000, 'luas_b' => 250, 'luas_bg' => 95, 'lunas' => true],
            ['nama' => 'Hj. Yoyoh Rokayah', 'dusun' => 'DUSUN BABAKAN', 'nominal' => 610000, 'luas_b' => 460, 'luas_bg' => 175, 'lunas' => false],
            ['nama' => 'Ahmad Fauzi', 'dusun' => 'DUSUN BABAKAN', 'nominal' => 89000, 'luas_b' => 140, 'luas_bg' => 58, 'lunas' => true],
            ['nama' => 'Irma Suryani', 'dusun' => 'DUSUN BABAKAN', 'nominal' => 340000, 'luas_b' => 290, 'luas_bg' => 115, 'lunas' => true],
            ['nama' => 'Ujang Bustomi', 'dusun' => 'DUSUN BABAKAN', 'nominal' => 1200000, 'luas_b' => 920, 'luas_bg' => 400, 'lunas' => false],
            ['nama' => 'Nunung Nurjanah', 'dusun' => 'DUSUN BABAKAN', 'nominal' => 55000, 'luas_b' => 100, 'luas_bg' => 40, 'lunas' => true],
            ['nama' => 'Kurniawan', 'dusun' => 'DUSUN BABAKAN', 'nominal' => 410000, 'luas_b' => 330, 'luas_bg' => 125, 'lunas' => false],
        ];

        $sttsCounter = 1;
        $dusunTotals = [];

        foreach ($names as $idx => $wp) {
            $seq = str_pad($idx + 1, 4, '0', STR_PAD_LEFT);
            $dusunCode = match ($wp['dusun']) {
                'DUSUN SUKATANI' => '001',
                'DUSUN CIKUPA' => '002',
                'DUSUN PASIRANGIN' => '003',
                default => '004',
            };
            $nop = "32.05.040.001.{$dusunCode}-{$seq}.0";

            $isKolektor1 = in_array($wp['dusun'], ['DUSUN SUKATANI', 'DUSUN CIKUPA']);
            $assignedKolektor = $isKolektor1 ? $kolektor1 : $kolektor2;

            $transaksiId = null;
            $tanggalBayar = null;

            if ($wp['lunas']) {
                $daysAgo = rand(5, 45);
                $tanggalBayar = Carbon::now()->subDays($daysAgo)->setHour(rand(8, 15))->setMinute(rand(10, 50));
                $sttsNum = 'STTS-2026-' . str_pad($sttsCounter++, 5, '0', STR_PAD_LEFT);

                $trx = TransactionRecord::create([
                    'desa_id' => 1,
                    'nomor_stts' => $sttsNum,
                    'tanggal_transaksi' => $tanggalBayar,
                    'operator_id' => $assignedKolektor?->id ?? $adminUser->id,
                    'total_pokok' => $wp['nominal'],
                    'total_denda' => 0,
                    'total_fee' => 2000,
                    'total_bayar' => $wp['nominal'] + 2000,
                    'metode_pembayaran' => 'TUNAI',
                    'status_void' => false,
                ]);

                $transaksiId = $trx->id;
            }

            DhkpRow::create([
                'desa_id' => 1,
                'nop' => $nop,
                'nama_wp' => $wp['nama'],
                'alamat_wp' => 'Kp. ' . ucwords(strtolower(str_replace('DUSUN ', '', $wp['dusun']))) . ' RT 02 RW 01',
                'alamat_op' => 'Desa Sukatani, Kec. Cisurupan',
                'dusun' => $wp['dusun'],
                'blok' => '00' . rand(1, 4),
                'rt_rw' => '02/01',
                'luas_bumi' => $wp['luas_b'],
                'luas_bangunan' => $wp['luas_bg'],
                'njop_bumi' => $wp['luas_b'] * 200000,
                'njop_bangunan' => $wp['luas_bg'] * 500000,
                'ketetapan_pbb' => $wp['nominal'],
                'denda' => 0,
                'fee_kolektor' => 2000,
                'total_bayar' => $wp['lunas'] ? ($wp['nominal'] + 2000) : 0,
                'status_bayar' => $wp['lunas'] ? 'LUNAS' : 'BELUM',
                'domisili' => 'DALAM',
                'tanggal_bayar' => $tanggalBayar,
                'kolektor_id' => $wp['lunas'] ? $assignedKolektor?->id : null,
                'transaksi_id' => $transaksiId,
                'tahun' => 2026,
            ]);

            // Accumulate totals
            if (!isset($dusunTotals[$wp['dusun']])) {
                $dusunTotals[$wp['dusun']] = ['pokok' => 0, 'realisasi' => 0, 'sppt' => 0, 'sppt_lunas' => 0];
            }
            $dusunTotals[$wp['dusun']]['pokok'] += $wp['nominal'];
            $dusunTotals[$wp['dusun']]['sppt'] += 1;
            if ($wp['lunas']) {
                $dusunTotals[$wp['dusun']]['realisasi'] += $wp['nominal'];
                $dusunTotals[$wp['dusun']]['sppt_lunas'] += 1;
            }
        }

        // 6. Dusun Targets
        foreach ($dusunTotals as $dusunName => $data) {
            DusunTarget::updateOrCreate(
                ['desa_id' => 1, 'nama_dusun' => $dusunName, 'tahun' => 2026],
                [
                    'target_pbb' => $data['pokok'],
                    'realisasi_pbb' => $data['realisasi'],
                ]
            );
        }

        // 7. Kolektor Targets (Performa Kolektor)
        if ($kolektor1) {
            $pokok1 = ($dusunTotals['DUSUN SUKATANI']['pokok'] ?? 0) + ($dusunTotals['DUSUN CIKUPA']['pokok'] ?? 0);
            $sppt1 = ($dusunTotals['DUSUN SUKATANI']['sppt'] ?? 0) + ($dusunTotals['DUSUN CIKUPA']['sppt'] ?? 0);

            KolektorTarget::updateOrCreate(
                ['desa_id' => 1, 'kolektor_id' => $kolektor1->id, 'tahun' => 2026],
                [
                    'target_nominal' => $pokok1,
                    'target_sppt' => $sppt1,
                    'catatan' => 'Performa penagihan sangat baik di Dusun Sukatani dan Cikupa.',
                ]
            );
        }

        if ($kolektor2) {
            $pokok2 = ($dusunTotals['DUSUN PASIRANGIN']['pokok'] ?? 0) + ($dusunTotals['DUSUN BABAKAN']['pokok'] ?? 0);
            $sppt2 = ($dusunTotals['DUSUN PASIRANGIN']['sppt'] ?? 0) + ($dusunTotals['DUSUN BABAKAN']['sppt'] ?? 0);

            KolektorTarget::updateOrCreate(
                ['desa_id' => 1, 'kolektor_id' => $kolektor2->id, 'tahun' => 2026],
                [
                    'target_nominal' => $pokok2,
                    'target_sppt' => $sppt2,
                    'catatan' => 'Fokus penagihan tahap 2 di wilayah Dusun Babakan.',
                ]
            );
        }

        $this->command?->info("🎉 SEEDING DEMO DESA SUKATANI SUKSES 100%!");
        $this->command?->info("   - Total SPPT: " . count($names));
        $this->command?->info("   - Dusun: Sukatani, Cikupa, Pasirangin, Babakan");
        $this->command?->info("   - Akun Admin: admin.sukatani (Pass: AdminSukatani@2026!)");
        $this->command?->info("   - Akun Kades: kades.sukatani (Pass: KadesSukatani@2026!)");
        $this->command?->info("   - Akun Kolektor: kolektor.sukatani & kolektor.pasirangin");
    }
}
