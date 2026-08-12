<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS settings_key_unique;');
        } else {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropUnique('settings_key_unique');
            });
        }
    }

    public function down(): void
    {
        // No-op
    }
};
