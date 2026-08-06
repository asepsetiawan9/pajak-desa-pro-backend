<?php

namespace Database\Seeders;

use App\Models\DusunTarget;
use Illuminate\Database\Seeder;

class DusunTargetSeeder extends Seeder
{
    public function run(): void
    {
        $targets = [
            ['nama_dusun' => 'Balok', 'tahun' => 2026, 'target_pbb' => 45000000, 'realisasi_pbb' => 32000000],
            ['nama_dusun' => 'Cideres', 'tahun' => 2026, 'target_pbb' => 38000000, 'realisasi_pbb' => 28500000],
            ['nama_dusun' => 'Puncak Sari', 'tahun' => 2026, 'target_pbb' => 29000000, 'realisasi_pbb' => 19800000],
            ['nama_dusun' => 'Cipedes', 'tahun' => 2026, 'target_pbb' => 32000000, 'realisasi_pbb' => 24100000],
        ];

        foreach ($targets as $target) {
            DusunTarget::updateOrCreate(
                ['nama_dusun' => $target['nama_dusun'], 'tahun' => $target['tahun']],
                $target
            );
        }
    }
}
