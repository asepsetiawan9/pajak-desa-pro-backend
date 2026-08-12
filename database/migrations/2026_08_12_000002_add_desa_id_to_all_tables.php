<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Users Table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('desa_id')->nullable()->after('id')->constrained('desas')->onDelete('cascade');
        });

        // 2. Dhkp Rows Table
        Schema::table('dhkp_rows', function (Blueprint $table) {
            $table->foreignId('desa_id')->nullable()->after('id')->constrained('desas')->onDelete('cascade');
            $table->index(['desa_id', 'status_bayar', 'tahun']);
            $table->index(['desa_id', 'dusun']);
        });

        // 3. Transactions Table
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('desa_id')->nullable()->after('id')->constrained('desas')->onDelete('cascade');
            $table->index(['desa_id', 'tanggal_transaksi']);
        });

        // 4. Dusun Targets Table
        Schema::table('dusun_targets', function (Blueprint $table) {
            $table->foreignId('desa_id')->nullable()->after('id')->constrained('desas')->onDelete('cascade');
            $table->index(['desa_id', 'tahun']);
        });

        // 5. Settings Table
        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('desa_id')->nullable()->after('id')->constrained('desas')->onDelete('cascade');
        });

        // 6. Audit Logs Table
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('desa_id')->nullable()->after('id')->constrained('desas')->onDelete('cascade');
        });

        // Populate existing data with default desa_id = 1 (Desa Barudua) if desas exist
        if (Schema::hasTable('desas') && DB::table('desas')->where('id', 1)->exists()) {
            DB::table('users')->whereNull('desa_id')->update(['desa_id' => 1]);
            DB::table('dhkp_rows')->whereNull('desa_id')->update(['desa_id' => 1]);
            DB::table('transactions')->whereNull('desa_id')->update(['desa_id' => 1]);
            DB::table('dusun_targets')->whereNull('desa_id')->update(['desa_id' => 1]);
            DB::table('settings')->whereNull('desa_id')->update(['desa_id' => 1]);
            DB::table('audit_logs')->whereNull('desa_id')->update(['desa_id' => 1]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['desa_id']);
            $table->dropColumn('desa_id');
        });

        Schema::table('dhkp_rows', function (Blueprint $table) {
            $table->dropForeign(['desa_id']);
            $table->dropColumn('desa_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['desa_id']);
            $table->dropColumn('desa_id');
        });

        Schema::table('dusun_targets', function (Blueprint $table) {
            $table->dropForeign(['desa_id']);
            $table->dropColumn('desa_id');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropForeign(['desa_id']);
            $table->dropColumn('desa_id');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['desa_id']);
            $table->dropColumn('desa_id');
        });
    }
};
