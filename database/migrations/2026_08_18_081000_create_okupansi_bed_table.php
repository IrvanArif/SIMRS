<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('okupansi_bed', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rawat_inap_id')->constrained('rawat_inap')->cascadeOnDelete();
            $table->foreignId('bed_id')->constrained('bed');
            // Tarif kelas disalin saat penggalnya dibuka, supaya perubahan master
            // tidak mengubah biaya masa rawat yang sedang berjalan.
            $table->unsignedBigInteger('tarif_harian');
            $table->date('mulai');
            $table->date('selesai')->nullable();
            $table->foreignId('ditempatkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['rawat_inap_id', 'selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('okupansi_bed');
    }
};
