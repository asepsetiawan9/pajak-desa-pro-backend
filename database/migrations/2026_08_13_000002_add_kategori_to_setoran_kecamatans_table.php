<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('setoran_kecamatans', function (Blueprint $table) {
            $table->enum('kategori', [
                'SETOR_KECAMATAN',
                'KEGIATAN_DESA',
                'OPERASIONAL_DESA',
                'ADMINISTRASI',
                'LAINNYA'
            ])->default('SETOR_KECAMATAN')->after('nomor_bukti');

            $table->boolean('perlu_verifikasi_kecamatan')->default(true)->after('kategori');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('setoran_kecamatans', function (Blueprint $table) {
            $table->dropColumn(['kategori', 'perlu_verifikasi_kecamatan']);
        });
    }
};
