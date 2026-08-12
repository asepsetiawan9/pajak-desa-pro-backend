<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desas', function (Blueprint $table) {
            $table->id();
            $table->string('kode_desa', 10)->unique(); // 10 Digit (e.g. 3205120004)
            $table->string('nama_desa', 100);
            $table->string('nama_kecamatan', 100);
            $table->string('nama_kabupaten', 100);
            $table->string('nama_provinsi', 100)->default('Jawa Barat');
            $table->string('nama_kades', 100)->nullable();
            $table->string('nip_kades', 30)->nullable();
            $table->string('subdomain', 50)->unique();
            $table->string('logo_path', 255)->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desas');
    }
};
