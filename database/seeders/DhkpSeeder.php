<?php

namespace Database\Seeders;

use App\Models\DhkpRow;
use App\Models\DusunTarget;
use App\Models\TransactionRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DhkpSeeder extends Seeder
{
    public function run(): void
    {
        // Disable foreign key checks for clean cleanup on SQLite / MySQL
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        DhkpRow::truncate();
        TransactionRecord::truncate();
        DusunTarget::truncate();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        // Get user IDs for collectors & bendahara
        $kolektorBalok = User::where('username', 'kolektor.balok')->first()?->id ?? 3;
        $kolektorCideres = User::where('username', 'kolektor.cideres')->first()?->id ?? 4;
        $kolektorPuncak = User::where('username', 'kolektor.puncak')->first()?->id ?? 5;
        $adminDesa = User::where('username', 'admin.desa')->first()?->id ?? 2;

        $firstNames = [
            'H. Dedi', 'Hj. Neneh', 'Maman', 'Cecep', 'Euis', 'Ir. Totong', 'Asep', 'Deden', 'Ujang', 'Yayan',
            'Cucun', 'Teti', 'Jaja', 'Rohman', 'Wawan', 'Enjang', 'Kukun', 'Oman', 'Iwan', 'Imas',
            'Rina', 'Agus', 'Bambang', 'Sri', 'Siti', 'Endang', 'Yusuf', 'H. Asep', 'Hj. Aisyah', 'H. Agus',
            'Drs. Heri', 'Ade', 'Oleh', 'Entis', 'Popon', 'Neneng', 'Dede', 'Iip', 'Iis', 'Titin',
            'Dudung', 'Tatang', 'Yudi', 'Hardi', 'Nandang', 'Dadang', 'Lilis', 'Elis', 'Tati', 'Ai',
            'Wina', 'Titin', 'Usep', 'Opik', 'Kurnia', 'Ahmad', 'Saepudin', 'Mulyadi', 'Syarif', 'Iwan'
        ];

        $lastNames = [
            'Suhardi', 'Marliana', 'Sutrisno', 'Abdurrahman', 'Kurniawan', 'Ratnasari', 'Hermanto', 'Setiawan',
            'Kusnadi', 'Hidayat', 'Permana', 'Saputra', 'Mulyana', 'Rohman', 'Sujana', 'Suherman',
            'Kusdinar', 'Solihin', 'Suryana', 'Gunawan', 'Nurhayati', 'Rahayu', 'Pratama', 'Wibowo',
            'Santoso', 'Sudrajat', 'Sulaeman', 'Firmansyah', 'Ramdani', 'Arifin', 'Hermawan', 'Subagja'
        ];

        $outsideAddresses = [
            'JL. ASIAN AFRIKA NO 45 JAKARTA',
            'JL. SOEKARNO HATTA NO 120 BANDUNG',
            'JL. RE MARTADINATA NO 88 TASIKMALAYA',
            'JL. RAYA GARUT NO 12 CIZOPED',
            'JL. SUDIRMAN NO 102 JAKARTA SELATAN',
            'JL. PASTEUR NO 14 BANDUNG',
            'JL. JUANDA NO 77 BOGOR',
            'JL. SUCI NO 40 BANDUNG',
        ];

        $dusunsConfig = [
            [
                'name' => 'Balok',
                'code' => '001',
                'count' => 110,
                'collector' => $kolektorBalok,
                'bloks' => ['Blok 01', 'Blok 02', 'Blok 03', 'Blok 04'],
            ],
            [
                'name' => 'Cideres',
                'code' => '002',
                'count' => 100,
                'collector' => $kolektorCideres,
                'bloks' => ['Blok 01', 'Blok 02', 'Blok 03', 'Blok 04'],
            ],
            [
                'name' => 'Puncak Sari',
                'code' => '003',
                'count' => 95,
                'collector' => $kolektorPuncak,
                'bloks' => ['Blok 01', 'Blok 02', 'Blok 03'],
            ],
            [
                'name' => 'Cipedes',
                'code' => '004',
                'count' => 95,
                'collector' => $kolektorPuncak,
                'bloks' => ['Blok 01', 'Blok 02', 'Blok 03'],
            ],
        ];

        $paymentMethods = ['TUNAI', 'TUNAI', 'TUNAI', 'TRANSFER', 'QRIS'];
        $sttsCounter = 1;

        foreach ($dusunsConfig as $config) {
            $dusunName = $config['name'];
            $dusunCode = $config['code'];
            $count = $config['count'];
            $collectorId = $config['collector'];
            $bloks = $config['bloks'];

            for ($i = 1; $i <= $count; $i++) {
                $blok = $bloks[($i - 1) % count($bloks)];
                $blokNum = str_pad((int) substr($blok, -2), 3, '0', STR_PAD_LEFT);
                $seqStr = str_pad($i, 4, '0', STR_PAD_LEFT);

                // NOP format: 32.05.010.00{code}.{blokNum}-{seqStr}.0
                $nop = "32.05.010.{$dusunCode}.{$blokNum}-{$seqStr}.0";

                $firstName = $firstNames[($i * 7) % count($firstNames)];
                $lastName = $lastNames[($i * 13) % count($lastNames)];
                $namaWp = "{$firstName} {$lastName}";

                $isLuarDesa = ($i % 6 === 0); // ~16% luar desa
                $domisili = $isLuarDesa ? 'LUAR_DESA' : 'DALAM_DESA';

                if ($isLuarDesa) {
                    $alamatWp = $outsideAddresses[($i * 3) % count($outsideAddresses)];
                    $feeKolektor = 5000;
                } else {
                    $rt = str_pad(($i % 4) + 1, 3, '0', STR_PAD_LEFT);
                    $rw = str_pad(($i % 3) + 1, 3, '0', STR_PAD_LEFT);
                    $alamatWp = "KP. " . strtoupper($dusunName) . " RT {$rt} RW {$rw}";
                    $feeKolektor = 0;
                }

                $rtRw = str_pad(($i % 4) + 1, 3, '0', STR_PAD_LEFT) . '/' . str_pad(($i % 3) + 1, 3, '0', STR_PAD_LEFT);
                $alamatOp = "KP. " . strtoupper($dusunName) . " " . strtoupper($blok);

                // Land and Building area
                $luasBumi = 80 + (($i * 37) % 1200);
                $hasBuilding = ($i % 4 !== 0); // 75% has building
                $luasBangunan = $hasBuilding ? 36 + (($i * 19) % 220) : 0;

                $njopBumi = $luasBumi * (100000 + (($i * 15000) % 300000));
                $njopBangunan = $luasBangunan * (400000 + (($i * 25000) % 600000));

                // Ketetapan PBB: realistic rural range Rp 35.000 to Rp 750.000
                $baseKetetapan = 35000 + (int) (($njopBumi + $njopBangunan) * 0.0008);
                $ketetapanPbb = (int) (round($baseKetetapan / 1000) * 1000);

                // Status bayar (~55% LUNAS, ~45% BELUM_BAYAR)
                $isLunas = ($i % 2 === 1) || ($i % 5 === 0);
                $statusBayar = $isLunas ? 'LUNAS' : 'BELUM_BAYAR';

                $denda = 0;
                $tanggalBayar = null;
                $transaksiId = null;

                $currentDesaId = 1;
                if ($dusunName === 'Puncak Sari') {
                    $currentDesaId = 2;
                } elseif ($dusunName === 'Cipedes') {
                    $currentDesaId = 3;
                }

                if ($isLunas) {
                    // Spread payment dates from Jan 15 to Aug 05, 2026
                    $daysAgo = ($i * 2) % 200;
                    $payDate = Carbon::create(2026, 8, 5, 10, 0, 0)->subDays($daysAgo)->addMinutes(($i * 17) % 480);
                    $tanggalBayar = $payDate->toDateTimeString();

                    // Create transaction record for every 1-2 lunas rows
                    if ($i % 2 === 1 || $i === $count) {
                        $sttsNum = 'STTS-' . $payDate->format('Ymd') . '-' . str_pad($sttsCounter++, 4, '0', STR_PAD_LEFT);
                        $method = $paymentMethods[$i % count($paymentMethods)];

                        $transaction = TransactionRecord::create([
                            'desa_id' => $currentDesaId,
                            'nomor_stts' => $sttsNum,
                            'tanggal_transaksi' => $tanggalBayar,
                            'operator_id' => ($i % 7 === 0) ? $adminDesa : $collectorId,
                            'total_pokok' => $ketetapanPbb,
                            'total_denda' => $denda,
                            'total_fee' => $feeKolektor,
                            'total_bayar' => $ketetapanPbb + $denda + $feeKolektor,
                            'metode_pembayaran' => $method,
                            'status_void' => false,
                        ]);

                        $transaksiId = $transaction->id;
                    } else {
                        // Attach to previous transaction if available
                        $latestTx = TransactionRecord::latest('id')->first();
                        if ($latestTx) {
                            $transaksiId = $latestTx->id;
                            $latestTx->update([
                                'total_pokok' => $latestTx->total_pokok + $ketetapanPbb,
                                'total_denda' => $latestTx->total_denda + $denda,
                                'total_fee' => $latestTx->total_fee + $feeKolektor,
                                'total_bayar' => $latestTx->total_bayar + $ketetapanPbb + $denda + $feeKolektor,
                            ]);
                        }
                    }
                }

                DhkpRow::create([
                    'desa_id' => $currentDesaId,
                    'nop' => $nop,
                    'nama_wp' => $namaWp,
                    'alamat_wp' => $alamatWp,
                    'alamat_op' => $alamatOp,
                    'dusun' => $dusunName,
                    'blok' => $blok,
                    'rt_rw' => $rtRw,
                    'luas_bumi' => $luasBumi,
                    'luas_bangunan' => $luasBangunan,
                    'njop_bumi' => $njopBumi,
                    'njop_bangunan' => $njopBangunan,
                    'ketetapan_pbb' => $ketetapanPbb,
                    'denda' => $denda,
                    'fee_kolektor' => $feeKolektor,
                    'total_bayar' => $ketetapanPbb + $denda + $feeKolektor,
                    'status_bayar' => $statusBayar,
                    'domisili' => $domisili,
                    'tanggal_bayar' => $tanggalBayar,
                    'kolektor_id' => $isLunas ? $collectorId : null,
                    'transaksi_id' => $transaksiId,
                    'tahun' => 2026,
                ]);
            }
        }

        // Add 2 void transactions for testing VOID / Batal Transaksi functionality in mobile/web
        $sampleLunas = DhkpRow::where('status_bayar', 'LUNAS')->take(2)->get();
        if ($sampleLunas->count() >= 2) {
            $voidTx = TransactionRecord::create([
                'desa_id' => 1,
                'nomor_stts' => 'STTS-20260710-9999',
                'tanggal_transaksi' => '2026-07-10 14:00:00',
                'operator_id' => $kolektorBalok,
                'total_pokok' => 150000,
                'total_denda' => 0,
                'total_fee' => 0,
                'total_bayar' => 150000,
                'metode_pembayaran' => 'TUNAI',
                'status_void' => true,
                'void_reason' => 'Salah input NOP warganet',
                'void_at' => '2026-07-10 16:30:00',
                'void_by' => $adminDesa,
            ]);
        }

        // Sync DusunTarget records mathematically with the generated DHKP rows
        foreach (['Balok', 'Cideres', 'Puncak Sari', 'Cipedes'] as $dusunName) {
            $totalKetetapan = DhkpRow::where('dusun', $dusunName)->where('tahun', 2026)->sum('ketetapan_pbb');
            $totalRealisasi = DhkpRow::where('dusun', $dusunName)->where('tahun', 2026)->where('status_bayar', 'LUNAS')->sum('ketetapan_pbb');

            // Target set to 105% of ketetapan (realistic target set by Bapenda/Desa)
            $targetPbb = (int) (ceil(($totalKetetapan * 1.05) / 100000) * 100000);

            DusunTarget::updateOrCreate(
                ['desa_id' => 1, 'nama_dusun' => $dusunName, 'tahun' => 2026],
                [
                    'target_pbb' => $targetPbb,
                    'realisasi_pbb' => (int) $totalRealisasi,
                ]
            );
        }
    }
}
