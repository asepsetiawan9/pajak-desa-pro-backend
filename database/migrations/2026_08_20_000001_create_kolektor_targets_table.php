<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kolektor_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('desa_id')->nullable()->constrained('desas')->onDelete('cascade');
            $table->foreignId('kolektor_id')->constrained('users')->onDelete('cascade');
            $table->integer('tahun')->default(2026);
            $table->bigInteger('target_nominal')->default(0);
            $table->integer('target_sppt')->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['desa_id', 'kolektor_id', 'tahun']);
            $table->index('desa_id');
            $table->index('kolektor_id');
            $table->index('tahun');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kolektor_targets');
    }
};
