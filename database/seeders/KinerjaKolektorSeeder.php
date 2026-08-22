<?php

namespace Database\Seeders;

use App\Models\Desa;
use App\Models\DhkpRow;
use App\Models\KolektorTarget;
use App\Models\TransactionRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class KinerjaKolektorSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Memulai Seeder Kinerja Kolektor (Local Development)...');

        DB::beginTransaction();
        try {
            // 1. Pastikan Master Desa Tersedia
            $desaBarudua = Desa::firstOrCreate(
                ['id' => 1],
                [
                    'kode_desa' => '3205120004',
                    'nama_desa' => 'Desa Barudua',
                    'nama_kecamatan' => 'Malangbong',
                    'nama_kabupaten' => 'Garut',
                    'nama_provinsi' => 'Jawa Barat',
                    'nama_kades' => 'Endang Yana',
                    'nip_kades' => '197505122008011002',
                    'subdomain' => 'barudua',
                    'logo_path' => '/lentera-logo.png',
                    'status_aktif' => true,
                ]
            );

            $desaCisitu = Desa::firstOrCreate(
                ['id' => 2],
                [
                    'kode_desa' => '3205120005',
                    'nama_desa' => 'Desa Cisitu',
                    'nama_kecamatan' => 'Malangbong',
                    'nama_kabupaten' => 'Garut',
                    'nama_provinsi' => 'Jawa Barat',
                    'nama_kades' => 'H. Adang Sutisna',
                    'nip_kades' => '197803152009021001',
                    'subdomain' => 'cisitu',
                    'logo_path' => '/lentera-logo.png',
                    'status_aktif' => true,
                ]
            );

            // 2. Pastikan Kolektor Users Ada & Aktif
            $kolektorConfigs = [
                [
                    'username' => 'kolektor.balok',
                    'name' => 'Deden Sudrajat',
                    'email' => 'deden@barudua.desa.id',
                    'phone' => '085712349876',
                    'role' => 'KOLEKTOR',
                    'dusun_akses' => 'BALOK, CIDERES, Dusun 1',
                    'desa_id' => 1,
                    'target_nominal' => 25000000,
                    'target_sppt' => 300,
                    'target_realisasi_pct' => 1.05, // 105% -> LEGEND 🔥
                    'catatan' => 'Target Semester I wilayah Dusun 1 & Balok',
                ],
                [
                    'username' => 'kolektor.cideres',
                    'name' => 'Maman Suherman',
                    'email' => 'maman@barudua.desa.id',
                    'phone' => '081399887766',
                    'role' => 'KOLEKTOR',
                    'dusun_akses' => 'CIDERES, Dusun 2',
                    'desa_id' => 1,
                    'target_nominal' => 22000000,
                    'target_sppt' => 280,
                    'target_realisasi_pct' => 0.82, // 82% -> GOLD 🏆
                    'catatan' => 'Intensifikasi penagihan door-to-door Dusun Cideres & Dusun 2',
                ],
                [
                    'username' => 'kolektor.puncak',
                    'name' => 'Aep Saepudin',
                    'email' => 'aep@barudua.desa.id',
                    'phone' => '081311223344',
                    'role' => 'KOLEKTOR',
                    'dusun_akses' => 'PUNCAK SARI, CIPEDES, Dusun 3',
                    'desa_id' => 1,
                    'target_nominal' => 20000000,
                    'target_sppt' => 250,
                    'target_realisasi_pct' => 0.58, // 58% -> SILVER 🥈
                    'catatan' => 'Target awal tahun Dusun Puncak Sari & Dusun 3',
                ],
                [
                    'username' => 'kolektor.cisitu',
                    'name' => 'Kolektor Desa Cisitu',
                    'email' => 'kolektor@cisitu.desa.id',
                    'phone' => '082233445566',
                    'role' => 'KOLEKTOR',
                    'dusun_akses' => 'DUSUN UTARA, DUSUN SELATAN',
                    'desa_id' => 2,
                    'target_nominal' => 15000000,
                    'target_sppt' => 180,
                    'target_realisasi_pct' => 0.36, // 36% -> BRONZE 🥉
                    'catatan' => 'Pilot project penagihan digital Desa Cisitu',
                ],
            ];

            $tahun = 2026;
            $feePerSppt = 2000;

            foreach ($kolektorConfigs as $cfg) {
                // Upsert Kolektor User
                $user = User::updateOrCreate(
                    ['username' => $cfg['username']],
                    [
                        'name' => $cfg['name'],
                        'email' => $cfg['email'],
                        'phone' => $cfg['phone'],
                        'password' => Hash::make('password123'),
                        'role' => $cfg['role'],
                        'dusun_akses' => $cfg['dusun_akses'],
                        'status_aktif' => true,
                        'desa_id' => $cfg['desa_id'],
                    ]
                );

                // Upsert Kolektor Target
                KolektorTarget::withoutGlobalScopes()->updateOrCreate(
                    [
                        'desa_id' => $cfg['desa_id'],
                        'kolektor_id' => $user->id,
                        'tahun' => $tahun,
                    ],
                    [
                        'target_nominal' => $cfg['target_nominal'],
                        'target_sppt' => $cfg['target_sppt'],
                        'catatan' => $cfg['catatan'],
                    ]
                );

                $targetNominalRealisasi = (int) ($cfg['target_nominal'] * $cfg['target_realisasi_pct']);
                $targetSpptRealisasi = (int) ($cfg['target_sppt'] * $cfg['target_realisasi_pct']);

                $this->command->info("👤 Kolektor: {$cfg['name']} (ID: {$user->id}, Desa: {$cfg['desa_id']})");
                $this->command->info("   🎯 Target: Rp " . number_format($cfg['target_nominal'], 0, ',', '.') . " ({$cfg['target_sppt']} SPPT)");
                $this->command->info("   💰 Rencana Realisasi: Rp " . number_format($targetNominalRealisasi, 0, ',', '.') . " (~{$targetSpptRealisasi} SPPT)");

                // 3. Alokasikan / Buat Data Realisasi Pembayaran DHKP
                $existingRows = DhkpRow::withoutGlobalScopes()
                    ->where('desa_id', $cfg['desa_id'])
                    ->where('status_bayar', 'BELUM_BAYAR')
                    ->take($targetSpptRealisasi)
                    ->get();

                $neededRows = $targetSpptRealisasi - $existingRows->count();

                // Jika baris di DB lokal kurang, buat dummy baris baru
                if ($neededRows > 0) {
                    $newRows = [];
                    for ($i = 1; $i <= $neededRows; $i++) {
                        $nopSuffix = str_pad((string) rand(1, 99999), 5, '0', STR_PAD_LEFT);
                        $ketetapan = rand(25, 150) * 1000;
                        $newRows[] = [
                            'desa_id' => $cfg['desa_id'],
                            'nop' => "32.05.120.004.001-{$nopSuffix}.0",
                            'nama_wp' => "WAJIB PAJAK " . strtoupper($cfg['username']) . " #{$i}",
                            'alamat_wp' => "RT 0" . rand(1, 5) . " / RW 0" . rand(1, 3) . " Wilayah " . explode(',', $cfg['dusun_akses'])[0],
                            'alamat_op' => "Blok " . chr(65 + ($i % 6)) . " No. " . rand(1, 100),
                            'dusun' => trim(explode(',', $cfg['dusun_akses'])[0]),
                            'blok' => "Blok " . chr(65 + ($i % 6)),
                            'rt_rw' => "00" . rand(1, 5) . "/00" . rand(1, 3),
                            'luas_bumi' => rand(100, 500),
                            'luas_bangunan' => rand(36, 200),
                            'njop_bumi' => rand(100, 500) * 50000,
                            'njop_bangunan' => rand(36, 200) * 150000,
                            'ketetapan_pbb' => $ketetapan,
                            'denda' => 0,
                            'fee_kolektor' => $feePerSppt,
                            'total_bayar' => $ketetapan,
                            'status_bayar' => 'BELUM_BAYAR',
                            'domisili' => 'DALAM',
                            'tanggal_bayar' => null,
                            'kolektor_id' => null,
                            'transaksi_id' => null,
                            'tahun' => $tahun,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    DhkpRow::withoutGlobalScopes()->insert($newRows);

                    // Ambil ulang data
                    $existingRows = DhkpRow::withoutGlobalScopes()
                        ->where('desa_id', $cfg['desa_id'])
                        ->where('status_bayar', 'BELUM_BAYAR')
                        ->take($targetSpptRealisasi)
                        ->get();
                }

                // Update baris-baris terpilih menjadi LUNAS dengan tanggal bayar realistis (30 hari terakhir)
                $currentSum = 0;
                $batchIndex = 1;

                // Buat 10-15 transaksi batch untuk menampung riwayat transaksi
                $rowsList = $existingRows->chunk(ceil($existingRows->count() / 15));

                foreach ($rowsList as $chunkIndex => $chunk) {
                    $daysAgo = rand(1, 28);
                    $paymentTime = Carbon::now()->subDays($daysAgo)->subHours(rand(1, 10))->subMinutes(rand(1, 50));

                    $chunkPokok = $chunk->sum('ketetapan_pbb');
                    $chunkFee = $chunk->count() * $feePerSppt;
                    $chunkTotal = $chunkPokok;

                    // Buat Transaction Record
                    $trx = TransactionRecord::withoutGlobalScopes()->create([
                        'desa_id' => $cfg['desa_id'],
                        'nomor_stts' => 'STTS-' . $paymentTime->format('Ymd') . '-' . str_pad((string) rand(100, 999), 4, '0', STR_PAD_LEFT) . '-' . $user->id,
                        'tanggal_transaksi' => $paymentTime,
                        'operator_id' => $user->id,
                        'total_pokok' => $chunkPokok,
                        'total_denda' => 0,
                        'total_fee' => $chunkFee,
                        'total_bayar' => $chunkTotal,
                        'metode_pembayaran' => 'TUNAI',
                        'status_void' => false,
                    ]);

                    foreach ($chunk as $row) {
                        $row->status_bayar = 'LUNAS';
                        $row->tanggal_bayar = $paymentTime;
                        $row->kolektor_id = $user->id;
                        $row->transaksi_id = $trx->id;
                        $row->fee_kolektor = $feePerSppt;
                        $row->total_bayar = $row->ketetapan_pbb;
                        $row->save();

                        $currentSum += $row->ketetapan_pbb;
                    }
                }

                $realCount = DhkpRow::withoutGlobalScopes()
                    ->where('kolektor_id', $user->id)
                    ->where('status_bayar', 'LUNAS')
                    ->where('tahun', $tahun)
                    ->count();

                $realNominal = (int) DhkpRow::withoutGlobalScopes()
                    ->where('kolektor_id', $user->id)
                    ->where('status_bayar', 'LUNAS')
                    ->where('tahun', $tahun)
                    ->sum('ketetapan_pbb');

                // Set target proporsional agar persentase capaian persis sesuai skenario (LEGEND, GOLD, SILVER, BRONZE)
                $finalTargetNominal = $realNominal > 0
                    ? (int) (round(($realNominal / $cfg['target_realisasi_pct']) / 100000) * 100000)
                    : $cfg['target_nominal'];
                $finalTargetSppt = $realCount > 0
                    ? (int) (round(($realCount / $cfg['target_realisasi_pct']) / 10) * 10)
                    : $cfg['target_sppt'];

                KolektorTarget::withoutGlobalScopes()->updateOrCreate(
                    [
                        'desa_id' => $cfg['desa_id'],
                        'kolektor_id' => $user->id,
                        'tahun' => $tahun,
                    ],
                    [
                        'target_nominal' => $finalTargetNominal,
                        'target_sppt' => $finalTargetSppt,
                        'catatan' => $cfg['catatan'],
                    ]
                );

                $pct = $finalTargetNominal > 0 ? round(($realNominal / $finalTargetNominal) * 100, 1) : 0;

                $this->command->info("   🎯 Target Disesuaikan: Rp " . number_format($finalTargetNominal, 0, ',', '.') . " ({$finalTargetSppt} SPPT)");
                $this->command->info("   ✅ Realisasi Aktif: Rp " . number_format($realNominal, 0, ',', '.') . " ({$realCount} SPPT, {$pct}%)\n");
            }

            DB::commit();
            $this->command->info('🎉 Seeder Kinerja Kolektor Berhasil Dijalankan 100%!');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command->error('❌ Terjadi kesalahan saat seeding kinerja kolektor: ' . $e->getMessage());
            throw $e;
        }
    }
}
