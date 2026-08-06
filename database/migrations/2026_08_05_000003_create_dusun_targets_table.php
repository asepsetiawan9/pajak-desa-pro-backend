<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dusun_targets', function (Blueprint $table) {
            $table->id();
            $table->string('nama_dusun');
            $table->integer('tahun')->default(2026);
            $table->bigInteger('target_pbb')->default(0);
            $table->bigInteger('realisasi_pbb')->default(0);
            $table->timestamps();

            $table->unique(['nama_dusun', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dusun_targets');
    }
};
