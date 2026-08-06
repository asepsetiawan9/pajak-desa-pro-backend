<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_stts')->unique();
            $table->dateTime('tanggal_transaksi');
            $table->foreignId('operator_id')->constrained('users')->onDelete('cascade');
            $table->bigInteger('total_pokok')->default(0);
            $table->bigInteger('total_denda')->default(0);
            $table->bigInteger('total_fee')->default(0);
            $table->bigInteger('total_bayar')->default(0);
            $table->string('metode_pembayaran', 20)->default('CASH');
            $table->boolean('status_void')->default(false);
            $table->text('void_reason')->nullable();
            $table->dateTime('void_at')->nullable();
            $table->foreignId('void_by')->nullable()->constrained('users')->onDelete('set null');
            $table->json('metadata_kk')->nullable();
            $table->timestamps();

            $table->index('tanggal_transaksi');
            $table->index('status_void');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
