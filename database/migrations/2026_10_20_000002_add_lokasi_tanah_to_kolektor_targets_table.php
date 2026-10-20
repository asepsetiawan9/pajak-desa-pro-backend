<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kolektor_targets', function (Blueprint $table) {
            if (!Schema::hasColumn('kolektor_targets', 'lokasi_tanah')) {
                $table->string('lokasi_tanah')->nullable()->after('tahun');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kolektor_targets', function (Blueprint $table) {
            if (Schema::hasColumn('kolektor_targets', 'lokasi_tanah')) {
                $table->dropColumn('lokasi_tanah');
            }
        });
    }
};
