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
        Schema::create('setoran_kecamatans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('desa_id')->nullable()->constrained('desas')->onDelete('cascade');
            $table->index('desa_id');

            $table->string('nomor_bukti', 50)->unique();
            $table->date('tanggal_setor');
            $table->unsignedSmallInteger('tahun')->default(2026);
            $table->decimal('nominal', 15, 2);
            $table->string('metode_setoran', 30)->default('TRANSFER'); // TRANSFER, TUNAI, LAINNYA
            $table->string('bank_tujuan', 100)->nullable();
            $table->string('nomor_referensi', 100)->nullable();
            
            $table->string('penyetor_nama', 100);
            $table->string('penyetor_jabatan', 100)->nullable();
            $table->string('penerima_kecamatan', 100)->nullable();
            $table->text('catatan_desa')->nullable();
            $table->longText('bukti_file')->nullable(); // Base64 or image text/URL

            $table->enum('status', ['PENDING', 'DITERIMA', 'DITOLAK'])->default('PENDING');
            $table->dateTime('tanggal_diterima')->nullable();
            $table->foreignId('penerima_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('catatan_kecamatan')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setoran_kecamatans');
    }
};
