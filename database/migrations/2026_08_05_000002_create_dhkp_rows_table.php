<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dhkp_rows', function (Blueprint $table) {
            $table->id();
            $table->string('nop', 50);
            $table->string('nama_wp');
            $table->string('alamat_wp')->nullable();
            $table->string('alamat_op')->nullable();
            $table->string('dusun', 50);
            $table->string('blok', 20);
            $table->string('rt_rw', 20)->nullable();
            $table->integer('luas_bumi')->default(0);
            $table->integer('luas_bangunan')->default(0);
            $table->bigInteger('njop_bumi')->default(0);
            $table->bigInteger('njop_bangunan')->default(0);
            $table->bigInteger('ketetapan_pbb')->default(0);
            $table->bigInteger('denda')->default(0);
            $table->bigInteger('fee_kolektor')->default(0);
            $table->bigInteger('total_bayar')->default(0);
            $table->string('status_bayar', 20)->default('BELUM_BAYAR'); // LUNAS, BELUM_BAYAR
            $table->string('domisili', 20)->default('DALAM_DESA'); // DALAM_DESA, LUAR_DESA
            $table->dateTime('tanggal_bayar')->nullable();
            $table->foreignId('kolektor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('transaksi_id')->nullable()->constrained('transactions')->onDelete('set null');
            $table->integer('tahun')->default(2026);
            $table->timestamps();

            // Indexing for ultra-fast query performance
            $table->index('nop');
            $table->index('dusun');
            $table->index('blok');
            $table->index('status_bayar');
            $table->index('tahun');
            $table->index('domisili');
            $table->index(['tahun', 'dusun', 'blok']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dhkp_rows');
    }
};
