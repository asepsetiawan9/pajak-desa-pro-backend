<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dhkp_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('dhkp_rows', 'nama_aktual')) {
                $table->string('nama_aktual')->nullable()->after('nama_wp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dhkp_rows', function (Blueprint $table) {
            if (Schema::hasColumn('dhkp_rows', 'nama_aktual')) {
                $table->dropColumn('nama_aktual');
            }
        });
    }
};
