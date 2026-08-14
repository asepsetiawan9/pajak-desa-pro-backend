<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dusuns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('desa_id')->constrained('desas')->onDelete('cascade');
            $table->string('nama_dusun', 100);
            $table->string('kode_dusun', 20)->nullable();
            $table->string('rt_rw', 50)->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();

            $table->unique(['desa_id', 'nama_dusun']);
            $table->index(['desa_id', 'status_aktif']);
        });

        // Backfill data dusun dari dhkp_rows dan dusun_targets yang sudah ada
        try {
            $existingDusuns = DB::table('dhkp_rows')
                ->whereNotNull('desa_id')
                ->whereNotNull('dusun')
                ->where('dusun', '!=', '')
                ->select('desa_id', 'dusun')
                ->distinct()
                ->get();

            $now = now();
            foreach ($existingDusuns as $item) {
                $dusunName = trim((string)$item->dusun);
                if ($dusunName !== '') {
                    DB::table('dusuns')->updateOrInsert(
                        [
                            'desa_id' => $item->desa_id,
                            'nama_dusun' => $dusunName,
                        ],
                        [
                            'status_aktif' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }

            // Backfill juga dari dusun_targets
            $targetDusuns = DB::table('dusun_targets')
                ->whereNotNull('desa_id')
                ->whereNotNull('nama_dusun')
                ->where('nama_dusun', '!=', '')
                ->select('desa_id', 'nama_dusun')
                ->distinct()
                ->get();

            foreach ($targetDusuns as $item) {
                $dusunName = trim((string)$item->nama_dusun);
                if ($dusunName !== '') {
                    DB::table('dusuns')->updateOrInsert(
                        [
                            'desa_id' => $item->desa_id,
                            'nama_dusun' => $dusunName,
                        ],
                        [
                            'status_aktif' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            // Ignore if tables are empty
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dusuns');
    }
};
