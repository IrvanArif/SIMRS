<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->string('no_kuitansi', 20)->unique();
            $table->foreignId('tagihan_id')->constrained('tagihan')->cascadeOnDelete();
            $table->string('metode', 20);
            $table->unsignedBigInteger('nominal');
            $table->unsignedBigInteger('kembalian')->default(0);
            $table->foreignId('kasir_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('waktu_bayar');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};
